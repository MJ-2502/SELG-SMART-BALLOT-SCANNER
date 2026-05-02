<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    elections: {
        type: Array,
        default: () => [],
    },
    positions: {
        type: Array,
        default: () => [],
    },
    serviceUrl: {
        type: String,
        default: '',
    },
    layoutCount: {
        type: Number,
        default: 0,
    },
    scanUrl: {
        type: String,
        required: true,
    },
    submitUrl: {
        type: String,
        required: true,
    },
});

const page = usePage();
const csrfToken = computed(() => page.props.csrf_token ?? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '');

const fileInput = ref(null);
const cameraPreview = ref(null);
const cameraStage = ref(null);
const guideFrame = ref(null);
const captureCanvas = ref(null);
const analysisCanvas = ref(null);

const selectedElectionId = ref('');
const ballotNumber = ref('');
const debugMode = ref(false);

const resultJsonText = ref('No scan yet.');
const detectedVotes = ref([]);

// ── Scan phase state machine ─────────────────────────────────────────
// idle → scanning → validating → submitting → submitted → idle
// rescan from validating → idle (re-arms camera)
const scanPhase = ref('idle'); // 'idle' | 'scanning' | 'validating' | 'submitting' | 'submitted'
const validationError = ref(''); // error message shown inside the modal
const lastSubmittedBallot = ref(null); // { ballot_number, votes_saved, ballot_id } after success

const scanState = ref('Ready.');
const submitState = ref('Waiting for a successful scan.');

const pendingSubmission = ref(null);
const scanInProgress = ref(false);
const submitInProgress = ref(false);

const previewImageSrc = ref('');
const debugOverlaySrc = ref('');
const debugBubbles = ref([]);
const debugPayload = ref(null);

const cameraGuideOverlayVisible = ref(false);
const cameraGuideStatus = ref('Camera idle.');
const cameraGuideHint = ref('Press Start to begin.');
const guideStabilityPct = ref(0);
const guideVisualState = ref('idle');

const mobileCameraActive = ref(false);
const mobileMenuOpen = ref(false);
const autoScanMode = ref(true);
const capturePreviewOpen = ref(false); // shows deskewed image while OMR scan runs

const flashSupported = ref(false);
const flashEnabled = ref(false);

const isMobileViewport = () => window.matchMedia('(max-width: 1023px)').matches;

const flashButtonLabel = computed(() => {
    if (!cameraStream.value) return 'Flash unavailable';
    if (!flashSupported.value) return 'Flash unsupported';
    return flashEnabled.value ? 'Flash on' : 'Flash off';
});

const menuFlashButtonLabel = computed(() => {
    if (!cameraStream.value) return 'Flash unavailable';
    if (!flashSupported.value) return 'Flash unsupported';
    return flashEnabled.value ? 'Turn flash off' : 'Turn flash on';
});

const flashDisabled = computed(() => !cameraStream.value || !flashSupported.value);
const cameraActive = computed(() => !!cameraStream.value);

// Ballot number is required before scanning
const ballotNumberMissing = computed(() => !ballotNumber.value.trim());

// Positions that have no detected vote (used to block submit)
const undervotedPositions = computed(() => {
    if (!pendingSubmission.value) return [];
    const votedPositionIds = new Set(
        (pendingSubmission.value.detected_votes || []).map(v => v.position_id)
    );
    return props.positions.filter(p => !votedPositionIds.has(p.id));
});

const canSubmitBallot = computed(() =>
    scanPhase.value === 'validating' &&
    pendingSubmission.value !== null &&
    undervotedPositions.value.length === 0
);

const cameraStream = ref(null);
let capturedBlob = null;
let alignmentInterval = null;
let stableFrameCount = 0;
let autoCaptureTriggered = false;
let autoCaptureCooldown = false;
let cooldownReleaseFrames = 0;
let previewObjectUrl = null;
let debugObjectUrl = null;

const STABLE_FRAMES_REQUIRED = 6;

const clearObjectUrl = (kind) => {
    if (kind === 'preview' && previewObjectUrl) {
        URL.revokeObjectURL(previewObjectUrl);
        previewObjectUrl = null;
    }

    if (kind === 'debug' && debugObjectUrl) {
        URL.revokeObjectURL(debugObjectUrl);
        debugObjectUrl = null;
    }
};

const setGuideVisualState = (state) => {
    guideVisualState.value = state;
};

const guideTipVisible = ref(false);
const statusPanelExpanded = ref(false);

const updateGuideStatus = (statusText, hintText, progress, state = 'searching') => {
    cameraGuideStatus.value = statusText;
    cameraGuideHint.value = hintText;
    guideStabilityPct.value = Math.max(0, Math.min(100, progress));
    setGuideVisualState(state);
};

const stopAlignmentWatcher = () => {
    if (alignmentInterval) {
        clearTimeout(alignmentInterval);
        alignmentInterval = null;
    }

    stableFrameCount = 0;
    autoCaptureTriggered = false;
    autoCaptureCooldown = false;
    cooldownReleaseFrames = 0;
    cameraGuideOverlayVisible.value = false;
    updateGuideStatus('Camera idle.', 'Press Start to begin.', 0, 'idle');
};

const getActiveVideoTrack = () => (cameraStream.value ? cameraStream.value.getVideoTracks()[0] : null);

const detectTorchSupport = () => {
    const track = getActiveVideoTrack();
    flashSupported.value = false;

    if (track && typeof track.getCapabilities === 'function') {
        const capabilities = track.getCapabilities();
        flashSupported.value = Boolean(capabilities && capabilities.torch);
    }

    if (!flashSupported.value) {
        flashEnabled.value = false;
    }
};

const setTorchState = async (enabled) => {
    const track = getActiveVideoTrack();
    if (!track || !flashSupported.value) {
        return false;
    }

    await track.applyConstraints({
        advanced: [{ torch: Boolean(enabled) }],
    });

    flashEnabled.value = Boolean(enabled);
    return true;
};

const toggleFlash = async () => {
    if (!cameraStream.value) {
        scanState.value = 'Start the camera before toggling flash.';
        return;
    }

    if (!flashSupported.value) {
        scanState.value = 'Flash is not supported on this device camera.';
        return;
    }

    try {
        const nextState = !flashEnabled.value;
        await setTorchState(nextState);
        scanState.value = nextState ? 'Flash enabled.' : 'Flash disabled.';
    } catch (error) {
        scanState.value = `Flash error: ${error.message}`;
    }
};

const closeMobileControlMenu = () => {
    mobileMenuOpen.value = false;
};

const setMobileCameraMode = (enabled) => {
    if (enabled && isMobileViewport()) {
        mobileCameraActive.value = true;
        document.body.classList.add('mobile-camera-active');
        document.body.style.overflow = ''; // clear any leftover overflow from dashboard sheet
        closeMobileControlMenu();
        return;
    }

    mobileCameraActive.value = false;
    document.body.classList.remove('mobile-camera-active');
    document.body.style.overflow = ''; // always restore scroll
    closeMobileControlMenu();
};

const parseJsonSafely = async (response) => {
    try {
        return await response.json();
    } catch (error) {
        return {
            success: false,
            message: 'Invalid JSON response from server.',
        };
    }
};

const renderVoteList = (votes) => {
    if (!Array.isArray(votes)) {
        detectedVotes.value = [];
        return;
    }

    detectedVotes.value = votes;
};

const setDebugOverlay = (imageDataUrl) => {
    if (typeof imageDataUrl === 'string' && imageDataUrl.startsWith('data:image/')) {
        clearObjectUrl('debug');
        debugOverlaySrc.value = imageDataUrl;
        return;
    }

    clearObjectUrl('debug');
    debugOverlaySrc.value = '';
};

const setPreviewFromBlob = (blob) => {
    if (!blob) {
        return;
    }

    clearObjectUrl('preview');
    previewObjectUrl = URL.createObjectURL(blob);
    previewImageSrc.value = previewObjectUrl;
    capturedBlob = blob;
};

const onFileChange = () => {
    const file = fileInput.value?.files?.[0];
    if (!file) {
        return;
    }

    setPreviewFromBlob(file);
    pendingSubmission.value = null;
    submitState.value = 'Waiting for a successful scan.';
    setDebugOverlay(null);
    debugBubbles.value = [];
    debugPayload.value = null;
};

const estimateBallotAlignment = () => {
    const previewEl = cameraPreview.value;
    const analysisEl = analysisCanvas.value;
    if (!previewEl || !analysisEl) {
        return {
            aligned: false,
            confidence: 0,
            hint: 'Waiting for camera frames...',
        };
    }

    const frameW = previewEl.videoWidth || 0;
    const frameH = previewEl.videoHeight || 0;

    if (!frameW || !frameH) {
        return {
            aligned: false,
            confidence: 0,
            hint: 'Waiting for camera frames...',
        };
    }

    const targetW = 160; // Low-res is enough for alignment heuristics — 4.5× less pixel work
    const scale = targetW / frameW;
    const analysisW = targetW;
    const analysisH = Math.max(1, Math.round(frameH * scale));

    analysisEl.width = analysisW;
    analysisEl.height = analysisH;

    const ctx = analysisEl.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
        return {
            aligned: false,
            confidence: 0,
            hint: 'Unable to initialize analysis context.',
        };
    }

    ctx.drawImage(previewEl, 0, 0, analysisW, analysisH);

    const frame = ctx.getImageData(0, 0, analysisW, analysisH);
    const data = frame.data;
    const luminance = new Float32Array(analysisW * analysisH);

    let idx = 0;
    for (let i = 0; i < data.length; i += 4) {
        luminance[idx] = (0.299 * data[i]) + (0.587 * data[i + 1]) + (0.114 * data[i + 2]);
        idx += 1;
    }

    const guide = {
        x1: Math.floor(analysisW * 0.19),
        x2: Math.floor(analysisW * 0.81),
        y1: Math.floor(analysisH * 0.08),
        y2: Math.floor(analysisH * 0.92),
    };

    let insideCount = 0;
    let outsideCount = 0;
    let insideSum = 0;
    let outsideSum = 0;
    let insideSq = 0;
    let edgeInside = 0;
    let edgeOutside = 0;

    for (let y = 1; y < analysisH - 1; y += 1) {
        for (let x = 1; x < analysisW - 1; x += 1) {
            const p = (y * analysisW) + x;
            const gx = Math.abs(luminance[p + 1] - luminance[p - 1]);
            const gy = Math.abs(luminance[p + analysisW] - luminance[p - analysisW]);
            const edgeMag = gx + gy;
            const isInside = x >= guide.x1 && x <= guide.x2 && y >= guide.y1 && y <= guide.y2;

            if (isInside) {
                insideCount += 1;
                insideSum += luminance[p];
                insideSq += luminance[p] * luminance[p];
                if (edgeMag > 36) {
                    edgeInside += 1;
                }
            } else {
                outsideCount += 1;
                outsideSum += luminance[p];
                if (edgeMag > 36) {
                    edgeOutside += 1;
                }
            }
        }
    }

    if (insideCount === 0 || outsideCount === 0) {
        return {
            aligned: false,
            confidence: 0,
            hint: 'Unable to read guide area.',
        };
    }

    const insideMean = insideSum / insideCount;
    const outsideMean = outsideSum / outsideCount;
    const insideVariance = Math.max(0, (insideSq / insideCount) - (insideMean * insideMean));
    const insideStd = Math.sqrt(insideVariance);
    const insideEdgeDensity = edgeInside / insideCount;
    const outsideEdgeDensity = edgeOutside / outsideCount;

    const guideW = Math.max(1, (guide.x2 - guide.x1) + 1);
    const guideH = Math.max(1, (guide.y2 - guide.y1) + 1);
    const paperThreshold = Math.max(132, Math.min(210, insideMean - 10));

    let brightCount = 0;
    let minBrightX = analysisW;
    let minBrightY = analysisH;
    let maxBrightX = -1;
    let maxBrightY = -1;

    for (let y = guide.y1; y <= guide.y2; y += 1) {
        for (let x = guide.x1; x <= guide.x2; x += 1) {
            const p = (y * analysisW) + x;
            if (luminance[p] >= paperThreshold) {
                brightCount += 1;
                if (x < minBrightX) minBrightX = x;
                if (x > maxBrightX) maxBrightX = x;
                if (y < minBrightY) minBrightY = y;
                if (y > maxBrightY) maxBrightY = y;
            }
        }
    }

    const paperCoverage = brightCount / insideCount;
    const hasPaperBox = brightCount > (insideCount * 0.28);

    let bboxWidthRatio = 0;
    let bboxHeightRatio = 0;
    let bboxFill = 0;
    let bboxCenterOffsetX = 1;
    let bboxCenterOffsetY = 1;
    let bboxAspect = 0;

    if (hasPaperBox && maxBrightX >= minBrightX && maxBrightY >= minBrightY) {
        const bboxW = (maxBrightX - minBrightX) + 1;
        const bboxH = (maxBrightY - minBrightY) + 1;
        const bboxArea = Math.max(1, bboxW * bboxH);
        bboxWidthRatio = bboxW / guideW;
        bboxHeightRatio = bboxH / guideH;
        bboxFill = brightCount / bboxArea;
        bboxAspect = bboxW / Math.max(1, bboxH);

        const guideCenterX = (guide.x1 + guide.x2) / 2;
        const guideCenterY = (guide.y1 + guide.y2) / 2;
        const bboxCenterX = (minBrightX + maxBrightX) / 2;
        const bboxCenterY = (minBrightY + maxBrightY) / 2;

        bboxCenterOffsetX = Math.abs(bboxCenterX - guideCenterX) / guideW;
        bboxCenterOffsetY = Math.abs(bboxCenterY - guideCenterY) / guideH;
    }

    const brightEnough = insideMean >= 120;
    const texturedEnough = insideStd >= 16;
    const hasPrintEdges = insideEdgeDensity >= 0.075 && insideEdgeDensity <= 0.32;
    const centeredContrast = (insideMean - outsideMean) >= 10;
    const insideDominates = insideEdgeDensity > (outsideEdgeDensity * 1.08);
    const hasPaperCoverage = paperCoverage >= 0.42 && paperCoverage <= 0.96;
    const hasRectangularPaper = bboxWidthRatio >= 0.56 && bboxHeightRatio >= 0.72;
    const paperWellCentered = bboxCenterOffsetX <= 0.13 && bboxCenterOffsetY <= 0.11;
    const paperCompact = bboxFill >= 0.54;
    const paperAspectOk = bboxAspect >= 0.46 && bboxAspect <= 0.92;

    const confidence = (
        (brightEnough ? 0.16 : 0)
        + (texturedEnough ? 0.10 : 0)
        + (hasPrintEdges ? 0.12 : 0)
        + (centeredContrast ? 0.10 : 0)
        + (insideDominates ? 0.10 : 0)
        + (hasPaperCoverage ? 0.16 : 0)
        + (hasRectangularPaper ? 0.12 : 0)
        + (paperWellCentered ? 0.08 : 0)
        + (paperCompact ? 0.04 : 0)
        + (paperAspectOk ? 0.02 : 0)
    );

    let hint = 'Move ballot into the guide.';
    if (!brightEnough) {
        hint = 'Improve lighting or move closer to the ballot.';
    } else if (!hasPaperCoverage) {
        hint = 'Place the whole ballot sheet inside the frame.';
    } else if (!hasRectangularPaper || !paperAspectOk) {
        hint = 'Hold the ballot flat and keep all edges visible.';
    } else if (!paperWellCentered) {
        hint = 'Center the ballot in the guide.';
    } else if (!hasPrintEdges) {
        hint = 'Fill the frame with the full ballot page.';
    } else if (!centeredContrast) {
        hint = 'Center the ballot inside the guide frame.';
    } else if (!insideDominates) {
        hint = 'Reduce background clutter around the ballot.';
    } else {
        hint = 'Great. Hold still...';
    }

    return {
        aligned: confidence >= 0.84,
        confidence,
        hint,
    };
};

const captureFrameFromCamera = () => new Promise((resolve) => {
    if (!cameraStream.value || !cameraPreview.value || !captureCanvas.value) {
        scanState.value = 'Start the camera before capturing.';
        resolve(null);
        return;
    }

    const sourceW = cameraPreview.value.videoWidth || 1280;
    const sourceH = cameraPreview.value.videoHeight || 960;

    const computeGuideCropRect = () => {
        const stageRect = cameraStage.value?.getBoundingClientRect();
        const guideRect = guideFrame.value?.getBoundingClientRect();

        if (
            !stageRect
            || !guideRect
            || stageRect.width <= 0
            || stageRect.height <= 0
            || guideRect.width <= 0
            || guideRect.height <= 0
        ) {
            return {
                sx: 0,
                sy: 0,
                sw: sourceW,
                sh: sourceH,
            };
        }

        const displayW = stageRect.width;
        const displayH = stageRect.height;
        const scale = Math.max(displayW / sourceW, displayH / sourceH);
        const renderedW = sourceW * scale;
        const renderedH = sourceH * scale;
        const cropOffsetX = (renderedW - displayW) / 2;
        const cropOffsetY = (renderedH - displayH) / 2;

        const gx1 = Math.max(0, Math.min(displayW, guideRect.left - stageRect.left));
        const gy1 = Math.max(0, Math.min(displayH, guideRect.top - stageRect.top));
        const gx2 = Math.max(0, Math.min(displayW, guideRect.right - stageRect.left));
        const gy2 = Math.max(0, Math.min(displayH, guideRect.bottom - stageRect.top));

        const guideDisplayW = Math.max(1, gx2 - gx1);
        const guideDisplayH = Math.max(1, gy2 - gy1);

        let sx = Math.floor((gx1 + cropOffsetX) / scale);
        let sy = Math.floor((gy1 + cropOffsetY) / scale);
        let sw = Math.floor(guideDisplayW / scale);
        let sh = Math.floor(guideDisplayH / scale);

        sx = Math.max(0, Math.min(sourceW - 1, sx));
        sy = Math.max(0, Math.min(sourceH - 1, sy));
        sw = Math.max(2, Math.min(sourceW - sx, sw));
        sh = Math.max(2, Math.min(sourceH - sy, sh));

        return { sx, sy, sw, sh };
    };

    const { sx, sy, sw, sh } = computeGuideCropRect();

    captureCanvas.value.width = sw;
    captureCanvas.value.height = sh;

    const context = captureCanvas.value.getContext('2d');
    if (!context) {
        scanState.value = 'Unable to capture frame context.';
        resolve(null);
        return;
    }

    context.drawImage(
        cameraPreview.value,
        sx,
        sy,
        sw,
        sh,
        0,
        0,
        captureCanvas.value.width,
        captureCanvas.value.height,
    );

    captureCanvas.value.toBlob((blob) => {
        if (!blob) {
            scanState.value = 'Unable to capture the current frame.';
            resolve(null);
            return;
        }

        setPreviewFromBlob(blob);
        scanState.value = 'Guide area captured. Ready to scan.';
        resolve(blob);
    }, 'image/jpeg', 0.95);
});

const startAlignmentWatcher = () => {
    stopAlignmentWatcher();
    cameraGuideOverlayVisible.value = true;
    updateGuideStatus('Searching for ballot...', 'Center the full ballot inside the frame.', 0, 'searching');

    // Adaptive polling via setTimeout — slows down when idle to reduce CPU heat
    // Fast (260ms) only when actively counting stable frames
    // Slow (700ms) when idle, in cooldown, manual mode, or modal open
    const FAST_MS = 260;
    const SLOW_MS = 700;

    const tick = async () => {
        if (!alignmentInterval) return; // watcher was stopped

        // Modal open or scan running — pause analysis entirely
        if (
            scanPhase.value === 'validating' ||
            scanPhase.value === 'submitting' ||
            scanPhase.value === 'submitted' ||
            scanInProgress.value
        ) {
            alignmentInterval = setTimeout(tick, SLOW_MS);
            return;
        }

        if (!cameraStream.value || autoCaptureTriggered) {
            alignmentInterval = setTimeout(tick, SLOW_MS);
            return;
        }

        const probe = estimateBallotAlignment();
        const baseProgress = Math.round(Math.max(0, Math.min(1, probe.confidence)) * 75);

        // Cooldown — waiting for ballot removal, poll slowly
        if (autoCaptureCooldown) {
            if (!probe.aligned) {
                cooldownReleaseFrames += 1;
                if (cooldownReleaseFrames >= 3) {
                    autoCaptureCooldown = false;
                    cooldownReleaseFrames = 0;
                }
            } else {
                cooldownReleaseFrames = 0;
            }
            updateGuideStatus('Auto-capture complete', 'Move ballot out of frame to arm next capture.', 100, 'ready');
            alignmentInterval = setTimeout(tick, SLOW_MS);
            return;
        }

        // Manual mode — show feedback, never auto-trigger, poll slowly
        if (!autoScanMode.value) {
            if (probe.aligned) {
                updateGuideStatus('Ballot aligned', 'Manual mode — tap Capture to scan.', baseProgress, 'ready');
            } else {
                updateGuideStatus('Waiting for valid ballot frame', probe.hint, baseProgress, 'searching');
            }
            alignmentInterval = setTimeout(tick, SLOW_MS);
            return;
        }

        // Not aligned — reset stable count, poll slowly
        if (!probe.aligned) {
            stableFrameCount = 0;
            updateGuideStatus('Waiting for valid ballot frame', probe.hint, baseProgress, 'searching');
            alignmentInterval = setTimeout(tick, SLOW_MS);
            return;
        }

        // Aligned — count stable frames at fast rate
        stableFrameCount += 1;
        const stableProgress = Math.round((stableFrameCount / STABLE_FRAMES_REQUIRED) * 100);
        updateGuideStatus('Ballot aligned', `Hold still (${stableFrameCount}/${STABLE_FRAMES_REQUIRED})`, stableProgress, 'ready');

        if (stableFrameCount < STABLE_FRAMES_REQUIRED) {
            alignmentInterval = setTimeout(tick, FAST_MS);
            return;
        }

        // Stable long enough — capture
        autoCaptureTriggered = true;
        updateGuideStatus('Capturing...', 'Ballot detected. Capturing now.', 100, 'capturing');

        const blob = await captureFrameFromCamera();
        if (!blob) {
            stableFrameCount = 0;
            autoCaptureTriggered = false;
            updateGuideStatus('Align ballot to continue', 'Capture failed, hold still and try again.', 0, 'searching');
            alignmentInterval = setTimeout(tick, FAST_MS);
            return;
        }

        await runScan();

        stableFrameCount = 0;
        autoCaptureTriggered = false;
        autoCaptureCooldown = true;
        cooldownReleaseFrames = 0;
        updateGuideStatus('Auto-capture complete', 'Move ballot out of frame to arm next capture.', 100, 'ready');
        alignmentInterval = setTimeout(tick, SLOW_MS);
    };

    alignmentInterval = setTimeout(tick, SLOW_MS);
};

const startCamera = async () => {
    if (ballotNumberMissing.value) {
        scanState.value = 'Enter the ballot number before starting the camera.';
        return;
    }
    try {
        cameraStream.value = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'environment',
                width:  { ideal: 1280 },
                height: { ideal: 960 },
            },
            audio: false,
        });

        if (!cameraPreview.value) {
            scanState.value = 'Camera preview is unavailable.';
            return;
        }

        cameraPreview.value.srcObject = cameraStream.value;
        scanState.value = 'Camera is ready. Align ballot in the guide for auto-capture.';
        startAlignmentWatcher();
        setMobileCameraMode(true);
        detectTorchSupport();
    } catch (error) {
        scanState.value = `Camera error: ${error.message}`;
    }
};

const captureManual = async () => {
    if (scanInProgress.value) return;

    updateGuideStatus('Capturing...', 'Hold still.', 100, 'capturing');
    const blob = await captureFrameFromCamera();

    if (!blob) {
        updateGuideStatus('Capture failed', 'Try again.', 0, 'searching');
        return;
    }

    // Reset stable-frame counters so auto-mode doesn't re-trigger immediately
    stableFrameCount = 0;
    autoCaptureTriggered = false;
    autoCaptureCooldown = false;
    cooldownReleaseFrames = 0;

    // Open the capture preview overlay so the facilitator sees the image
    // while the OMR scan runs in the background
    capturePreviewOpen.value = true;

    // Trigger scan immediately — same pipeline as auto mode
    await runScan();
};

const stopCamera = () => {
    if (cameraStream.value) {
        cameraStream.value.getTracks().forEach((track) => track.stop());
        cameraStream.value = null;
    }

    flashEnabled.value = false;
    flashSupported.value = false;

    if (cameraPreview.value) {
        cameraPreview.value.srcObject = null;
    }

    stopAlignmentWatcher();
    setMobileCameraMode(false);
    scanState.value = 'Camera stopped.';
};

const runScan = async () => {
    if (scanInProgress.value) return;

    const file = capturedBlob || fileInput.value?.files?.[0];
    if (!file) {
        scanState.value = 'Choose or capture a ballot image first.';
        return;
    }

    scanInProgress.value = true;
    scanPhase.value = 'scanning';
    pendingSubmission.value = null;
    validationError.value = '';
    submitState.value = 'Waiting for a successful scan.';

    const formData = new FormData();
    formData.append('ballot_image', file, file.name || 'ballot.jpg');
    if (selectedElectionId.value) formData.append('election_id', selectedElectionId.value);
    if (ballotNumber.value.trim()) formData.append('ballot_number', ballotNumber.value.trim());

    scanState.value = 'Sending image to OMR service...';

    try {
        let finalScanUrl = props.scanUrl;
        if (debugMode.value) {
            const sep = finalScanUrl.includes('?') ? '&' : '?';
            finalScanUrl += `${sep}include_debug_image=true&include_debug_bubbles=true`;
        }

        const response = await fetch(finalScanUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken.value, Accept: 'application/json' },
            body: formData,
        });

        const payload = await parseJsonSafely(response);
        resultJsonText.value = JSON.stringify(payload, null, 2);
        setDebugOverlay(payload.debug_visualization_image || null);
        debugBubbles.value = Array.isArray(payload.debug_bubbles) ? payload.debug_bubbles : [];
        debugPayload.value = payload;

        if (typeof payload.processed_preview_image === 'string' && payload.processed_preview_image.startsWith('data:image/')) {
            previewImageSrc.value = payload.processed_preview_image;
        }

        const preview = payload.scan_preview || null;
        const votes = (preview && Array.isArray(preview.detected_votes))
            ? preview.detected_votes
            : (payload.detected_votes || []);

        renderVoteList(votes);

        if (response.status === 409) {
            validationError.value = payload.message || 'This ballot image was already submitted.';
            scanPhase.value = 'validating';
            pendingSubmission.value = null;
        } else if (response.ok && preview?.can_submit && Array.isArray(preview.detected_votes) && preview.detected_votes.length > 0) {
            pendingSubmission.value = {
                image_hash: preview.image_hash,
                detected_votes: preview.detected_votes,
                election_id: selectedElectionId.value || null,
                ballot_number: ballotNumber.value.trim() || null,
            };
            validationError.value = '';
            scanPhase.value = 'validating';
            stopAlignmentWatcher();
        } else {
            validationError.value = payload.message || 'Scan could not produce a submittable result.';
            scanPhase.value = 'validating';
            pendingSubmission.value = null;
        }

        // Validation modal is now open — dismiss the capture preview
        capturePreviewOpen.value = false;

        scanState.value = response.ok ? 'Scan complete.' : 'Scan completed with errors.';
    } catch (error) {
        resultJsonText.value = JSON.stringify({ success: false, message: error.message }, null, 2);
        detectedVotes.value = [];
        setDebugOverlay(null);
        debugBubbles.value = [];
        debugPayload.value = { success: false, message: error.message };
        scanState.value = 'Scan failed.';
        validationError.value = `Scan failed: ${error.message}`;
        scanPhase.value = 'validating';
        pendingSubmission.value = null;
        capturePreviewOpen.value = false;
    } finally {
        scanInProgress.value = false;
    }
};

const submitDetectedVotes = async () => {
    if (!pendingSubmission.value || scanPhase.value !== 'validating') return;

    scanPhase.value = 'submitting';
    validationError.value = '';

    try {
        const response = await fetch(props.submitUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken.value,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(pendingSubmission.value),
        });

        const payload = await parseJsonSafely(response);
        resultJsonText.value = JSON.stringify(payload, null, 2);

        if (response.ok && payload.success) {
            lastSubmittedBallot.value = {
                ballot_number: payload.ballot?.ballot_number ?? pendingSubmission.value.ballot_number ?? '—',
                ballot_id: payload.ballot?.id ?? null,
                votes_saved: payload.votes_saved ?? 0,
                submitted_votes: payload.submitted_votes ?? [],
            };
            if (Array.isArray(payload.submitted_votes)) renderVoteList(payload.submitted_votes);
            pendingSubmission.value = null;
            scanPhase.value = 'submitted';
        } else {
            // Map known error codes to human-readable messages
            if (response.status === 409) {
                validationError.value = 'This ballot was already submitted. Scan the next ballot.';
            } else if (response.status === 422) {
                const errors = payload.errors?.join(' ') || payload.message;
                validationError.value = errors || 'Validation failed. Please rescan.';
            } else {
                validationError.value = payload.message || 'Submission failed. Please try again.';
            }
            scanPhase.value = 'validating';
        }
    } catch (error) {
        validationError.value = `Submission failed: ${error.message}`;
        scanPhase.value = 'validating';
    }
};

// Reset everything and re-arm camera for the next ballot
const scanNextBallot = () => {
    // Auto-increment: parse current ballot number as int and add 1
    // Falls back to empty string if it wasn't a number
    const current = parseInt(lastSubmittedBallot.value?.ballot_number ?? ballotNumber.value, 10);
    const next = Number.isFinite(current) ? String(current + 1) : '';

    scanPhase.value = 'idle';
    pendingSubmission.value = null;
    validationError.value = '';
    lastSubmittedBallot.value = null;
    capturePreviewOpen.value = false;
    ballotNumber.value = next;
    capturedBlob = null;
    detectedVotes.value = [];
    resultJsonText.value = 'No scan yet.';
    submitState.value = 'Waiting for a successful scan.';
    scanState.value = 'Ready.';
    clearObjectUrl('preview');
    clearObjectUrl('debug');
    previewImageSrc.value = '';
    debugOverlaySrc.value = '';
    debugBubbles.value = [];
    debugPayload.value = null;
    if (fileInput.value) fileInput.value.value = '';
    // Re-arm the alignment watcher if camera is still running
    if (cameraStream.value) startAlignmentWatcher();
};

// Rescan: dismiss modal, keep camera open, clear result
const rescanBallot = () => {
    scanPhase.value = 'idle';
    pendingSubmission.value = null;
    validationError.value = '';
    capturedBlob = null;
    capturePreviewOpen.value = false;
    detectedVotes.value = [];
    previewImageSrc.value = '';
    debugOverlaySrc.value = '';
    debugBubbles.value = [];
    debugPayload.value = null;
    if (fileInput.value) fileInput.value.value = '';
    if (cameraStream.value) startAlignmentWatcher();
};

const guideFrameClass = computed(() => {
    if (guideVisualState.value === 'ready') return 'border-emerald-300/95';
    if (guideVisualState.value === 'capturing') return 'border-sky-300/95';
    return 'border-amber-300/95';
});

const guideBarClass = computed(() => {
    if (guideVisualState.value === 'ready') return 'bg-emerald-500';
    if (guideVisualState.value === 'capturing') return 'bg-sky-500';
    return 'bg-amber-400';
});

const handleResize = () => {
    if (!isMobileViewport()) {
        setMobileCameraMode(false);
    }
};

onMounted(async () => {
    window.addEventListener('resize', handleResize);

    // Read URL params — set by the dashboard "Start Scanning" link
    const params = new URLSearchParams(window.location.search);

    const electionIdParam = params.get('election_id');
    if (electionIdParam) {
        selectedElectionId.value = electionIdParam;
    }

    // ?autostart=1 → automatically start camera and enter fullscreen
    // The dashboard link uses: /scanner?election_id=X&autostart=1
    const autostart = params.get('autostart') === '1';
    if (autostart && isMobileViewport()) {
        // Small delay so the DOM and camera permission dialog settle
        await new Promise((resolve) => setTimeout(resolve, 400));
        await startCamera();
        // startCamera already calls setMobileCameraMode(true) on success
    }
});

onBeforeUnmount(() => {
    stopCamera();
    clearObjectUrl('preview');
    clearObjectUrl('debug');
    window.removeEventListener('resize', handleResize);
    document.body.classList.remove('mobile-camera-active');
});
</script>

<template>
    <Head title="Scanner" />

    <div class="ui-page max-w-lg mx-auto px-0 sm:px-4">

        <!-- Page header -->
        <div v-show="!mobileCameraActive" class="px-4 sm:px-0 pt-4 pb-2">
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Ballot Scanner</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ positions.length }} position(s) · {{ layoutCount }} slot(s)</p>
        </div>

        <!-- ── STEP 1: Ballot number ── -->
        <div v-show="!mobileCameraActive" class="px-4 sm:px-0 mt-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <label class="block">
                    <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-700 mb-2">
                        <i class="bi bi-file-text text-indigo-500 text-base leading-none" aria-hidden="true"></i>
                        Ballot Number
                        <span class="ml-auto text-xs font-normal text-red-500">Required</span>
                    </span>
                    <input
                        v-model="ballotNumber"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="Enter ballot number printed on the ballot"
                        class="block w-full rounded-xl border-slate-300 text-base focus:border-indigo-500 focus:ring-indigo-500 placeholder:text-slate-400"
                        :class="ballotNumber.trim() ? 'border-emerald-400 bg-emerald-50/40' : ''"
                    />
                </label>
                <label v-if="elections.length > 1" class="block mt-3">
                    <span class="block text-sm font-semibold text-slate-700 mb-2">Election</span>
                    <select v-model="selectedElectionId" class="block w-full rounded-xl border-slate-300 text-base focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Use current layout</option>
                        <option v-for="election in elections" :key="election.id" :value="String(election.id)">{{ election.label }}</option>
                    </select>
                </label>
            </div>
        </div>

        <!-- ── STEP 2: Camera ── -->
        <div class="mt-3 px-4 sm:px-0">
            <div id="mobileCameraShell" class="relative rounded-2xl border border-slate-200 overflow-hidden bg-slate-50 shadow-sm">

                <!-- Fullscreen top bar -->
                <div id="fsTopBar">
                    <button type="button" id="fsFlashBtn" :disabled="flashDisabled" @click="toggleFlash">
                        <i class="bi bi-lightning-charge-fill text-[1.1rem] leading-none" aria-hidden="true"></i>
                        <span>{{ flashEnabled ? 'Flash on' : 'Flash' }}</span>
                    </button>
                    <button type="button" id="fsModeBtn"
                        :class="autoScanMode ? 'fs-mode-auto' : 'fs-mode-manual'"
                        @click="autoScanMode = !autoScanMode; if (autoScanMode) { autoCaptureCooldown = false; cooldownReleaseFrames = 0; stableFrameCount = 0; }">
                        <span id="fsModeIndicator"></span>
                        <span>{{ autoScanMode ? 'Auto' : 'Manual' }}</span>
                    </button>
                    <button type="button" id="fsInfoBtn" @click="guideTipVisible = !guideTipVisible">
                        <i class="bi bi-info-circle text-[1.1rem] leading-none" aria-hidden="true"></i>
                    </button>
                    <button type="button" id="fsExitBtn" @click="stopCamera">
                        <i class="bi bi-x-lg text-[0.9rem] leading-none" aria-hidden="true"></i>
                        <span>Exit</span>
                    </button>
                </div>

                <!-- Normal card header -->
                <div id="cameraCardHeader" class="flex items-center justify-between px-4 pt-3 pb-2 gap-2">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Camera</p>
                        <p class="camera-help-text text-xs text-slate-500">Align ballot in frame for auto-capture</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="enterMobileCameraBtn"
                            class="rounded-xl border border-slate-200 bg-white p-2 text-slate-600 active:bg-slate-50 lg:hidden"
                            @click="setMobileCameraMode(true)">
                            <i class="bi bi-arrows-fullscreen text-base leading-none" aria-hidden="true"></i>
                        </button>
                        <button v-if="!cameraActive" type="button" id="startCameraBtn"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white active:bg-indigo-700 disabled:opacity-50"
                            :disabled="ballotNumberMissing"
                            :title="ballotNumberMissing ? 'Enter ballot number first' : ''"
                            @click="startCamera">
                            Start Camera
                        </button>
                        <button v-else type="button" id="stopCameraBtn"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 active:bg-slate-50"
                            @click="stopCamera">
                            Stop
                        </button>
                        <button v-show="cameraActive" type="button" id="flashToggleBtn"
                            class="rounded-xl border p-2 text-xs font-semibold transition-colors disabled:opacity-40"
                            :class="flashEnabled ? 'border-amber-400 bg-amber-400 text-slate-900' : 'border-slate-200 bg-white text-slate-600'"
                            :disabled="flashDisabled"
                            @click="toggleFlash">
                            <i class="bi bi-lightning-charge-fill text-base leading-none" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <!-- Camera viewport — portrait ratio for ballot -->
                <div id="cameraStage" ref="cameraStage" class="relative bg-black" style="aspect-ratio: 3/4;">
                    <video ref="cameraPreview" id="cameraPreview" class="absolute inset-0 w-full h-full object-cover" autoplay playsinline muted></video>

                    <div id="cameraGuideOverlay" class="pointer-events-none absolute inset-0" :class="cameraGuideOverlayVisible ? '' : 'hidden'">
                        <div id="guideMaskTop"    class="guide-mask absolute left-0 right-0 top-0 h-[6%]"></div>
                        <div id="guideMaskBottom" class="guide-mask absolute bottom-0 left-0 right-0 h-[6%]"></div>
                        <div id="guideMaskLeft"   class="guide-mask absolute left-0 top-[6%] bottom-[6%] w-[8%]"></div>
                        <div id="guideMaskRight"  class="guide-mask absolute right-0 top-[6%] bottom-[6%] w-[8%]"></div>
                        <div id="guideFrame" ref="guideFrame"
                            class="absolute left-[8%] right-[8%] top-[6%] bottom-[6%] rounded-2xl border-2 transition-colors duration-200"
                            :class="guideFrameClass">
                            <div id="guideFrameGrid"></div>
                            <div id="guideScanLine"></div>
                            <div class="guide-corner top-left"></div>
                            <div class="guide-corner top-right"></div>
                            <div class="guide-corner bottom-left"></div>
                            <div class="guide-corner bottom-right"></div>
                        </div>
                        <div id="guideTip" v-show="guideTipVisible"
                            class="absolute left-4 right-4 top-3 rounded-xl bg-slate-900/70 px-3 py-2 text-xs text-white text-center"
                            style="pointer-events:none;">
                            Keep the whole ballot inside the frame. Hold still for auto-capture.
                        </div>
                    </div>

                    <!-- Idle empty state — tap anywhere to start camera -->
                    <button
                        v-if="!cameraActive"
                        type="button"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-3 w-full bg-slate-900/50 transition-colors active:bg-slate-900/70"
                        :disabled="ballotNumberMissing"
                        @click="startCamera">
                        <div class="flex flex-col items-center gap-2"
                            :class="ballotNumberMissing ? 'opacity-40' : 'opacity-100'">
                            <!-- Pulsing ring when ready -->
                            <div class="relative">
                                <div v-if="!ballotNumberMissing"
                                    class="absolute inset-0 rounded-full bg-indigo-500/30 animate-ping scale-150">
                                </div>
                                <div class="relative rounded-full p-4"
                                    :class="ballotNumberMissing ? 'bg-slate-700/50' : 'bg-indigo-600/80'">
                                    <i class="bi bi-camera text-[2rem] text-white leading-none" aria-hidden="true"></i>
                                </div>
                            </div>
                            <p class="text-sm font-semibold text-white drop-shadow">
                                {{ ballotNumberMissing ? 'Enter ballot number first' : 'Tap to start camera' }}
                            </p>
                            <p v-if="!ballotNumberMissing" class="text-xs text-slate-300">
                                or tap "Start Camera" above
                            </p>
                        </div>
                    </button>
                </div>

                <canvas ref="captureCanvas" class="hidden"></canvas>
                <canvas ref="analysisCanvas" class="hidden"></canvas>

                <!-- Status pill -->
                <div id="cameraStatusPanel"
                    class="mx-3 my-2 rounded-xl border border-slate-200 bg-white px-3 py-2 cursor-pointer"
                    @click="statusPanelExpanded = !statusPanelExpanded">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="inline-block h-2 w-2 rounded-full flex-shrink-0 transition-colors"
                            :class="{
                                'bg-emerald-500': guideVisualState === 'ready',
                                'bg-sky-400 animate-pulse': guideVisualState === 'capturing',
                                'bg-amber-400': guideVisualState === 'searching',
                                'bg-slate-300': guideVisualState === 'idle',
                            }"></span>
                        <span class="text-xs font-medium text-slate-700 truncate flex-1">{{ cameraGuideStatus }}</span>
                        <div class="w-14 h-1 rounded-full bg-slate-200 flex-shrink-0 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-150" :class="guideBarClass" :style="{ width: `${guideStabilityPct}%` }"></div>
                        </div>
                        <i class="bi bi-chevron-down text-slate-400 text-xs leading-none transition-transform" :class="statusPanelExpanded ? 'rotate-180' : ''" aria-hidden="true"></i>
                    </div>
                    <div v-show="statusPanelExpanded" class="mt-1 pl-4 text-xs text-slate-500">{{ cameraGuideHint }}</div>
                </div>

                <!-- Fullscreen bottom bar -->
                <div id="fsBottomBar">
                    <div id="fsActionRow">
                        <!-- Inline ballot number input — replaces non-functional History button -->
                        <div id="fsBallotInput">
                            <label id="fsBallotLabel">Ballot No.</label>
                            <input
                                id="fsBallotField"
                                v-model="ballotNumber"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="—"
                                maxlength="12"
                            />
                        </div>
                        <button type="button" id="fsShutterBtn" @click="captureManual" :disabled="!cameraActive || ballotNumberMissing">
                            <span id="fsShutterInner"></span>
                        </button>
                        <button type="button" class="fs-side-btn" title="Preview"
                            :class="!previewImageSrc ? 'opacity-40' : ''"
                            :disabled="!previewImageSrc"
                            @click="capturePreviewOpen = true">
                            <div class="fs-side-btn-icon-wrap">
                                <i class="bi bi-image text-[1.4rem] leading-none" aria-hidden="true"></i>
                                <span v-if="previewImageSrc" class="fs-badge">1</span>
                            </div>
                            <span>Preview</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── STEP 3: Actions ── -->
        <div v-show="!mobileCameraActive" class="px-4 sm:px-0 mt-3 pb-8 space-y-3">

            <!-- Ballot number required warning -->
            <div v-if="ballotNumberMissing" class="flex items-center gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <i class="bi bi-exclamation-triangle text-base flex-shrink-0 leading-none" aria-hidden="true"></i>
                Enter the ballot number before scanning.
            </div>

            <!-- Primary scan button -->
            <button type="button"
                class="w-full flex items-center justify-center gap-2.5 rounded-2xl py-4 text-base font-bold text-white shadow-sm transition-colors disabled:opacity-50"
                :class="scanInProgress ? 'bg-emerald-700' : 'bg-emerald-600 active:bg-emerald-700'"
                :disabled="scanInProgress || ballotNumberMissing"
                @click="runScan">
                <i v-if="scanInProgress" class="bi bi-arrow-repeat text-xl animate-spin leading-none" aria-hidden="true"></i>
                <i v-else class="bi bi-upc-scan text-xl leading-none" aria-hidden="true"></i>
                {{ scanInProgress ? 'Scanning…' : 'Scan with OMR' }}
            </button>

            <!-- Scan state feedback -->
            <p v-if="scanState && scanState !== 'Ready.'" class="text-xs text-center"
                :class="['fail','error','Error'].some(w => scanState.includes(w)) ? 'text-red-500' : 'text-slate-400'">
                {{ scanState }}
            </p>

            <!-- Upload fallback — collapsed -->
            <details class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
                <summary class="flex items-center gap-2 px-4 py-3.5 text-sm font-medium text-slate-600 cursor-pointer select-none list-none active:bg-slate-50">
                    <i class="bi bi-upload text-base text-slate-400 leading-none" aria-hidden="true"></i>
                    Upload image instead
                    <i class="bi bi-chevron-down text-base text-slate-400 ml-auto leading-none" aria-hidden="true"></i>
                </summary>
                <div class="px-4 pb-4 pt-2 border-t border-slate-100">
                    <input ref="fileInput" type="file" accept="image/*"
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-3 file:py-2 file:text-white file:text-xs file:font-semibold"
                        @change="onFileChange" />
                    <div v-if="previewImageSrc" class="mt-3 rounded-xl overflow-hidden border border-slate-200">
                        <img :src="previewImageSrc" alt="Ballot preview" class="w-full object-contain max-h-56" />
                    </div>
                </div>
            </details>

            <!-- Debug -->
            <label class="inline-flex items-center gap-2 text-xs text-slate-400 cursor-pointer px-1">
                <input v-model="debugMode" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                Debug mode
            </label>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         CAPTURE PREVIEW OVERLAY — shown after manual shutter tap
         Displays the deskewed image while OMR scan runs,
         or lets facilitator review it on demand via Preview button.
         Same visual style as the validation modal.
         ════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
        <div v-if="capturePreviewOpen && scanPhase !== 'validating' && scanPhase !== 'submitting' && scanPhase !== 'submitted'"
            class="fixed inset-0 z-[90] flex items-end sm:items-center justify-center p-0 sm:p-4"
            role="dialog" aria-modal="true">

            <!-- Backdrop -->
            <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm"
                @click="capturePreviewOpen = false"></div>

            <!-- Panel -->
            <div class="relative w-full sm:max-w-sm bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl flex flex-col max-h-[88dvh] overflow-hidden">

                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 flex-shrink-0">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Captured Image</h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Ballot #<span class="font-medium text-slate-700">{{ ballotNumber || '—' }}</span>
                            <span v-if="scanInProgress" class="ml-2 text-indigo-500 font-medium animate-pulse">· Scanning…</span>
                        </p>
                    </div>
                    <button type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"
                        @click="capturePreviewOpen = false">
                        <i class="bi bi-x-lg text-xl leading-none" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- Deskewed image -->
                <div class="flex-1 min-h-0 bg-slate-950 flex items-center justify-center p-3 overflow-hidden">
                    <img v-if="previewImageSrc"
                        :src="previewImageSrc"
                        alt="Deskewed ballot"
                        class="max-h-full w-full object-contain rounded-lg" />
                    <!-- Scanning spinner overlay -->
                    <div v-if="scanInProgress"
                        class="absolute inset-0 flex flex-col items-center justify-center bg-slate-900/60 rounded-lg gap-3">
                        <svg class="h-10 w-10 animate-spin text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        <p class="text-sm text-white font-medium">Running OMR scan…</p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-4 border-t border-slate-200 flex gap-3 flex-shrink-0">
                    <button type="button"
                        class="flex-1 flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white py-3 text-sm font-medium text-slate-700 active:bg-slate-50"
                        @click="rescanBallot(); capturePreviewOpen = false">
                        <i class="bi bi-arrow-counterclockwise text-base leading-none" aria-hidden="true"></i>
                        Retake
                    </button>
                    <button type="button"
                        class="flex-1 flex items-center justify-center gap-2 rounded-xl py-3 text-sm font-semibold text-white disabled:opacity-50"
                        :class="scanInProgress ? 'bg-slate-400 cursor-not-allowed' : 'bg-indigo-600 active:bg-indigo-700'"
                        :disabled="scanInProgress"
                        @click="capturePreviewOpen = false; runScan()">
                        <i class="bi bi-upc-scan text-base leading-none" aria-hidden="true"></i>
                        {{ scanInProgress ? 'Scanning…' : 'Scan Again' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════
             VALIDATION MODAL — shown after a successful OMR scan
             ════════════════════════════════════════════════════════════ -->
        <div v-if="scanPhase === 'validating' || scanPhase === 'submitting'"
            class="fixed inset-0 z-[95] flex items-end sm:items-center justify-center p-0 sm:p-4"
            role="dialog" aria-modal="true" aria-labelledby="validationModalTitle">

            <!-- Backdrop -->
            <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" @click="rescanBallot"></div>

            <!-- Panel -->
            <div class="relative w-full sm:max-w-2xl bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl flex flex-col max-h-[92dvh] overflow-hidden">

                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 flex-shrink-0">
                    <div>
                        <h2 id="validationModalTitle" class="text-base font-semibold text-slate-900">Review Scan Result</h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Ballot #<span class="font-medium text-slate-700">{{ ballotNumber || '—' }}</span>
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="rescanBallot">
                        <i class="bi bi-x-lg text-xl leading-none" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="flex flex-col sm:flex-row gap-0 overflow-hidden flex-1 min-h-0">

                    <!-- Left: ballot image preview -->
                    <div class="sm:w-48 lg:w-56 flex-shrink-0 border-b sm:border-b-0 sm:border-r border-slate-200 bg-slate-950 flex items-center justify-center p-3">
                        <img v-if="previewImageSrc" :src="previewImageSrc" alt="Captured ballot" class="max-h-48 sm:max-h-full w-full object-contain rounded-md" />
                        <div v-else class="text-xs text-slate-500 text-center">No preview available</div>
                    </div>

                    <!-- Right: votes list -->
                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3 min-h-0">

                        <!-- Error banner -->
                        <div v-if="validationError" class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700">
                            <i class="bi bi-exclamation-circle text-base flex-shrink-0 mt-0.5 leading-none" aria-hidden="true"></i>
                            {{ validationError }}
                        </div>

                        <!-- Undervote warning -->
                        <div v-if="undervotedPositions.length" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-800">
                            <p class="font-semibold mb-1">⚠ No vote detected for:</p>
                            <ul class="list-disc list-inside space-y-0.5">
                                <li v-for="pos in undervotedPositions" :key="pos.id">{{ pos.name }}</li>
                            </ul>
                            <p class="mt-1.5 text-xs">Submission is blocked until all positions have a detected vote. Rescan to retry.</p>
                        </div>

                        <!-- Votes grouped by position -->
                        <template v-if="pendingSubmission?.detected_votes?.length">
                            <div v-for="position in positions" :key="position.id">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">{{ position.name }}</p>
                                <template v-if="pendingSubmission.detected_votes.filter(v => v.position_id === position.id).length">
                                    <div v-for="vote in pendingSubmission.detected_votes.filter(v => v.position_id === position.id)"
                                        :key="vote.candidate_id"
                                        class="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm">
                                        <div class="flex items-center gap-2">
                                            <i class="bi bi-check-circle-fill text-emerald-600 text-base flex-shrink-0 leading-none" aria-hidden="true"></i>
                                            <span class="font-medium text-slate-800">{{ vote.candidate_name }}</span>
                                            <span v-if="vote.candidate_party" class="text-xs text-slate-500">· {{ vote.candidate_party }}</span>
                                        </div>
                                        <span v-if="Number.isFinite(Number(vote.confidence))"
                                            class="text-xs font-medium px-1.5 py-0.5 rounded-full"
                                            :class="Number(vote.confidence) >= 0.7 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                            {{ (Number(vote.confidence) * 100).toFixed(0) }}%
                                        </span>
                                    </div>
                                </template>
                                <div v-else class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 flex items-center gap-2">
                                    <i class="bi bi-x-circle text-base flex-shrink-0 leading-none" aria-hidden="true"></i>
                                    No vote detected
                                </div>
                            </div>
                        </template>
                        <div v-else-if="!validationError" class="text-sm text-slate-500 text-center py-4">No votes were detected in this scan.</div>

                        <!-- Debug details (only when debug mode is enabled) -->
                        <details v-if="debugMode" class="rounded-lg border border-slate-200 bg-slate-50">
                            <summary class="flex items-center justify-between gap-2 px-3 py-2 text-xs font-semibold text-slate-600 cursor-pointer">
                                Debug details
                                <i class="bi bi-chevron-down text-xs text-slate-400" aria-hidden="true"></i>
                            </summary>
                            <div class="px-3 pb-3 pt-2 space-y-3 text-xs text-slate-600">
                                <div v-if="debugOverlaySrc" class="space-y-2">
                                    <p class="text-[0.7rem] uppercase tracking-wide text-slate-400">Debug overlay</p>
                                    <img :src="debugOverlaySrc" alt="Debug overlay" class="w-full rounded-md border border-slate-200 bg-white" />
                                </div>
                                <div v-if="debugBubbles.length" class="space-y-2">
                                    <p class="text-[0.7rem] uppercase tracking-wide text-slate-400">Bubble metrics ({{ debugBubbles.length }})</p>
                                    <pre class="max-h-48 overflow-auto rounded-md border border-slate-200 bg-white p-2 text-[0.7rem] leading-relaxed text-slate-700">{{ JSON.stringify(debugBubbles, null, 2) }}</pre>
                                </div>
                                <div v-if="debugPayload" class="space-y-2">
                                    <p class="text-[0.7rem] uppercase tracking-wide text-slate-400">Raw response</p>
                                    <pre class="max-h-64 overflow-auto rounded-md border border-slate-200 bg-white p-2 text-[0.7rem] leading-relaxed text-slate-700">{{ resultJsonText }}</pre>
                                </div>
                                <p v-if="!debugOverlaySrc && !debugBubbles.length" class="text-[0.7rem] text-slate-400">
                                    No debug data returned. Make sure debug mode is enabled before scanning.
                                </p>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- Footer actions -->
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-t border-slate-200 bg-slate-50 flex-shrink-0">
                    <button type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        :disabled="scanPhase === 'submitting'"
                        @click="rescanBallot">
                        <i class="bi bi-arrow-counterclockwise text-base leading-none" aria-hidden="true"></i>
                        Rescan
                    </button>
                    <button type="button"
                        class="inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors disabled:opacity-60"
                        :class="scanPhase === 'submitting' ? 'bg-indigo-700 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700'"
                        :disabled="!canSubmitBallot || scanPhase === 'submitting'"
                        @click="submitDetectedVotes">
                        <i v-if="scanPhase === 'submitting'" class="bi bi-arrow-repeat text-base animate-spin leading-none" aria-hidden="true"></i>
                        <i v-else class="bi bi-check-lg text-base leading-none" aria-hidden="true"></i>
                        {{ scanPhase === 'submitting' ? 'Submitting…' : 'Submit Ballot' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════
             SUCCESS MODAL — shown after a ballot is submitted
             ════════════════════════════════════════════════════════════ -->
        <div v-if="scanPhase === 'submitted' && lastSubmittedBallot"
            class="fixed inset-0 z-[90] flex items-end sm:items-center justify-center p-0 sm:p-4"
            role="dialog" aria-modal="true">

            <!-- Backdrop -->
            <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"></div>

            <!-- Panel -->
            <div class="relative w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl overflow-hidden">

                <!-- Green success banner -->
                <div class="bg-emerald-600 px-5 py-6 text-center">
                    <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-white/20">
                        <i class="bi bi-check-lg text-3xl text-white leading-none" aria-hidden="true"></i>
                    </div>
                    <h2 class="text-lg font-bold text-white">Ballot Submitted!</h2>
                    <p class="mt-1 text-sm text-emerald-100">Votes have been recorded successfully.</p>
                </div>

                <!-- Details -->
                <div class="px-5 py-5 space-y-3">
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
                        <span class="text-sm text-slate-500">Ballot number</span>
                        <span class="text-sm font-semibold text-slate-800">#{{ lastSubmittedBallot.ballot_number }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
                        <span class="text-sm text-slate-500">Votes recorded</span>
                        <span class="text-sm font-semibold text-emerald-700">{{ lastSubmittedBallot.votes_saved }} vote{{ lastSubmittedBallot.votes_saved !== 1 ? 's' : '' }}</span>
                    </div>
                    <!-- Submitted votes summary -->
                    <div class="rounded-lg border border-slate-200 divide-y divide-slate-100 overflow-hidden">
                        <div v-for="vote in lastSubmittedBallot.submitted_votes" :key="`sv-${vote.candidate_id}`"
                            class="flex items-center justify-between px-4 py-2.5 bg-white text-sm">
                            <span class="text-slate-700 font-medium">{{ vote.candidate_name }}</span>
                            <span class="text-xs text-slate-400">{{ vote.position_name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Action -->
                <div class="px-5 pb-6 space-y-3">
                    <!-- Next ballot number preview -->
                    <div class="flex items-center justify-between rounded-xl bg-indigo-50 border border-indigo-100 px-4 py-3">
                        <div>
                            <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide">Next ballot</p>
                            <p class="text-sm font-bold text-indigo-900 mt-0.5">
                                #{{ Number.isFinite(parseInt(lastSubmittedBallot?.ballot_number, 10))
                                    ? parseInt(lastSubmittedBallot.ballot_number, 10) + 1
                                    : '—' }}
                                <span class="text-xs font-normal text-indigo-400 ml-1">(auto-filled)</span>
                            </p>
                        </div>
                        <i class="bi bi-arrow-right text-indigo-300 text-xl leading-none" aria-hidden="true"></i>
                    </div>
                    <button type="button"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm"
                        @click="scanNextBallot">
                        <i class="bi bi-upc-scan text-base leading-none" aria-hidden="true"></i>
                        Scan Next Ballot
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style>
/* Hide old mobile menu artefacts  */
#mobileControlMenuContainer { display: none; }
#mobileControlMenuBtn       { display: none; }

/* Fullscreen chrome: hidden by default */
#fsTopBar,
#fsBottomBar { display: none; }

/* FULLSCREEN MODE  (mobile only, body.mobile-camera-active)*/

@media (max-width: 1023px) {

    body.mobile-camera-active {
        overflow: hidden;
        background: #000;
    }

    /* ── Hide ALL layout chrome: app bar, sidebar, nav, page wrappers ── */
    /* Targets common Laravel/Inertia AdminLayout patterns                */
    body.mobile-camera-active nav,
    body.mobile-camera-active header,
    body.mobile-camera-active aside,
    body.mobile-camera-active [class*="sidebar"],
    body.mobile-camera-active [class*="navbar"],
    body.mobile-camera-active [class*="topbar"],
    body.mobile-camera-active [class*="app-bar"],
    body.mobile-camera-active [id*="sidebar"],
    body.mobile-camera-active [id*="navbar"],
    body.mobile-camera-active [id*="topbar"] {
        display: none !important;
    }

    /* Flatten any layout wrappers so #mobileCameraShell can go fixed/fullscreen */
    body.mobile-camera-active main,
    body.mobile-camera-active [class*="main-content"],
    body.mobile-camera-active [class*="page-content"],
    body.mobile-camera-active [class*="content-wrapper"],
    body.mobile-camera-active .ui-page {
        padding: 0 !important;
        margin: 0 !important;
        max-width: none !important;
        overflow: visible !important;
        background: #000 !important;
    }

    /* Stretch the shell to fill the screen — locked, immune to reflow */
    body.mobile-camera-active #mobileCameraShell {
        position: fixed;
        inset: 0;
        z-index: 70;
        margin: 0;
        padding: 0;
        border: none;
        border-radius: 0;
        background: #000;
        width: 100dvw;
        height: 100dvh;
        display: grid;
        grid-template-rows: auto 1fr auto;
        grid-template-columns: 1fr;
        grid-template-areas:
            "topbar"
            "stage"
            "bottombar";
        overflow: hidden;
    }

    /* Hide the normal card header */
    body.mobile-camera-active #cameraCardHeader { display: none; }

    /* Top bar */
    body.mobile-camera-active #fsTopBar {
        grid-area: topbar;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: calc(env(safe-area-inset-top, 0px) + 0.6rem) 1rem 0.6rem;
        z-index: 76;
        gap: 0.5rem;
    }

    /* Flash button */
    body.mobile-camera-active #fsFlashBtn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: #f1f5f9;
        font-size: 0.8rem;
        font-weight: 600;
        background: none;
        border: none;
        padding: 0.4rem 0.6rem;
        border-radius: 0.5rem;
        opacity: 1;
        transition: opacity .15s;
    }
    body.mobile-camera-active #fsFlashBtn[disabled] { opacity: 0.4; }
    body.mobile-camera-active #fsFlashBtn svg { width: 1.1rem; height: 1.1rem; }

    /* Auto/Manual pill */
    body.mobile-camera-active #fsModeBtn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: rgba(30,41,59,0.72);
        border: 1px solid rgba(148,163,184,0.3);
        backdrop-filter: blur(8px);
        color: #f1f5f9;
        font-size: 0.8rem;
        font-weight: 700;
        padding: 0.35rem 0.85rem;
        border-radius: 9999px;
        letter-spacing: 0.01em;
    }
    body.mobile-camera-active #fsModeIndicator {
        display: inline-block;
        width: 0.55rem;
        height: 0.55rem;
        border-radius: 50%;
        background: #3b82f6;
        box-shadow: 0 0 6px #3b82f6;
    }

    /* Exit button */
    body.mobile-camera-active #fsExitBtn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: #f1f5f9;
        font-size: 0.8rem;
        font-weight: 600;
        background: rgba(30,41,59,0.72);
        border: 1px solid rgba(148,163,184,0.3);
        backdrop-filter: blur(8px);
        padding: 0.35rem 0.75rem;
        border-radius: 0.5rem;
    }
    body.mobile-camera-active #fsExitBtn svg { width: 0.9rem; height: 0.9rem; }

    /* ── Camera stage occupies the stage grid area — never reflowing ── */
    body.mobile-camera-active #cameraStage {
        grid-area: stage;
        width: 100%;
        height: 100%;
        min-height: 0;
        border-radius: 0;
        aspect-ratio: unset;
        position: relative;
        overflow: hidden;
    }
    body.mobile-camera-active #cameraPreview {
        object-fit: cover;
        object-position: center;
    }

    /* ── Status panel — pinned just above the bottom bar ── */
    body.mobile-camera-active #cameraStatusPanel {
        grid-area: bottombar;
        align-self: start;
        justify-self: stretch;
        position: relative;
        transform: translateY(-100%);
        margin: 0 0.75rem 0.5rem;
        z-index: 78;
        border-color: rgba(148,163,184,0.25);
        background: rgba(15,23,42,0.78);
        backdrop-filter: blur(12px);
        pointer-events: auto;
        cursor: pointer;
        user-select: none;
        -webkit-user-select: none;
    }
    body.mobile-camera-active #cameraGuideStatus { color: #e2e8f0; }
    body.mobile-camera-active #cameraGuideHint   { color: #94a3b8; }
    body.mobile-camera-active .guide-stability-track { background: rgba(148,163,184,0.2); }

    /* ── Bottom bar ─────────────────────────────────────────────────── */
    body.mobile-camera-active #fsBottomBar {
        grid-area: bottombar;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0;
        padding: 0.5rem 1rem calc(env(safe-area-inset-bottom, 0px) + 0.75rem);
        z-index: 76;
    }

    /* PHOTO / SCAN / VIDEO tabs */
    body.mobile-camera-active #fsModeTabs {
        display: flex;
        gap: 1.5rem;
        margin-bottom: 0.9rem;
    }
    body.mobile-camera-active .fs-tab {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        color: rgba(203,213,225,0.55);
    }
    body.mobile-camera-active .fs-tab-active {
        color: #f59e0b;
    }

    /* Action row */
    body.mobile-camera-active #fsActionRow {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 0 0.5rem;
    }

    /* ── Inline ballot number input ── */
    body.mobile-camera-active #fsBallotInput {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.2rem;
        background: rgba(30,41,59,0.7);
        border: 1px solid rgba(148,163,184,0.28);
        backdrop-filter: blur(8px);
        border-radius: 0.75rem;
        padding: 0.4rem 0.6rem;
        width: 3.6rem;
        aspect-ratio: 1;
        justify-content: center;
        transition: border-color 0.15s, background 0.15s;
    }
    body.mobile-camera-active #fsBallotInput:focus-within {
        border-color: rgba(99,102,241,0.7);
        background: rgba(30,41,59,0.9);
    }
    body.mobile-camera-active #fsBallotLabel {
        font-size: 0.55rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: rgba(148,163,184,0.8);
        line-height: 1;
    }
    body.mobile-camera-active #fsBallotField {
        background: transparent;
        border: none;
        outline: none;
        color: #f1f5f9;
        font-size: 0.8rem;
        font-weight: 700;
        width: 100%;
        text-align: center;
        padding: 0;
        line-height: 1.2;
    }
    body.mobile-camera-active #fsBallotField::placeholder {
        color: rgba(148,163,184,0.4);
        font-weight: 400;
    }

    /* Side buttons (history / results) */
    body.mobile-camera-active .fs-side-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.25rem;
        background: rgba(30,41,59,0.7);
        border: 1px solid rgba(148,163,184,0.28);
        backdrop-filter: blur(8px);
        border-radius: 0.75rem;
        padding: 0.5rem 0.75rem;
        color: #cbd5e1;
        font-size: 0.625rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        width: 3.6rem;
        aspect-ratio: 1;
        justify-content: center;
        position: relative;
    }
    body.mobile-camera-active .fs-side-btn svg { width: 1.4rem; height: 1.4rem; stroke-width: 1.5; }
    body.mobile-camera-active .fs-side-btn-icon-wrap { position: relative; display: inline-flex; }

    /* Badge on results button */
    body.mobile-camera-active .fs-badge {
        position: absolute;
        top: -0.35rem;
        right: -0.45rem;
        background: #3b82f6;
        color: #fff;
        font-size: 0.6rem;
        font-weight: 800;
        border-radius: 9999px;
        min-width: 1rem;
        height: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 0.18rem;
        box-shadow: 0 0 0 2px rgba(0,0,0,0.5);
    }

    /* ── Big shutter button ─────────────────────────────────────────── */
    body.mobile-camera-active #fsShutterBtn {
        width: 4.5rem;
        height: 4.5rem;
        border-radius: 50%;
        border: 3px solid rgba(255,255,255,0.85);
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        transition: transform .1s, opacity .15s;
        flex-shrink: 0;
    }
    body.mobile-camera-active #fsShutterBtn:active { transform: scale(0.93); }
    body.mobile-camera-active #fsShutterBtn[disabled] { opacity: 0.4; }
    body.mobile-camera-active #fsShutterInner {
        width: 3.6rem;
        height: 3.6rem;
        border-radius: 50%;
        background: #3b82f6;
        box-shadow: 0 0 18px rgba(59,130,246,0.65);
        transition: background .15s;
    }
    body.mobile-camera-active #fsShutterBtn:active #fsShutterInner {
        background: #2563eb;
    }

    /* ── Hide normal buttons in fullscreen ──────────────────────────── */
    body.mobile-camera-active #enterMobileCameraBtn,
    body.mobile-camera-active #startCameraBtn,
    body.mobile-camera-active #stopCameraBtn,
    body.mobile-camera-active #flashToggleBtn,
    body.mobile-camera-active #captureBtn,
    body.mobile-camera-active #exitMobileCameraBtn,
    body.mobile-camera-active .camera-help-text { display: none; }

    /* ── Guide overlay adjustments ──────────────────────────────────── */
    body.mobile-camera-active #cameraGuideOverlay {
        --guide-top-inset: 6%;
        --guide-side-inset: 8%;
    }
    body.mobile-camera-active .guide-mask { background: rgba(2,6,23,0.16); }
    body.mobile-camera-active #guideMaskTop,
    body.mobile-camera-active #guideMaskBottom { height: var(--guide-top-inset); }
    body.mobile-camera-active #guideMaskLeft,
    body.mobile-camera-active #guideMaskRight {
        top: var(--guide-top-inset);
        bottom: var(--guide-top-inset);
        width: var(--guide-side-inset);
    }
    body.mobile-camera-active #guideFrame {
        left: var(--guide-side-inset);
        right: var(--guide-side-inset);
        top: var(--guide-top-inset);
        bottom: calc(var(--guide-top-inset) + 1rem);
        width: auto;
        height: auto;
        transform: none;
    }
    body.mobile-camera-active #guideFrameGrid { opacity: 0.16; }
    body.mobile-camera-active #guideScanLine { animation-duration: 1.85s; }
    body.mobile-camera-active #guideTip {
        background: rgba(15,23,42,0.55);
        top: 0.75rem;
        left: 3rem;
        right: 3rem;
        text-align: center;
    }
    body.mobile-camera-active #cameraGuideStatus,
    body.mobile-camera-active #cameraGuideHint { color: #e2e8f0; }
}

@media (min-width: 640px) and (max-width: 1023px) {
    body.mobile-camera-active #cameraGuideOverlay { --guide-side-inset: 12%; }
}

/* ═══ Scan-line & guide corners (always) ══════════════════════════════ */
@keyframes ballot-scan-sweep {
    0%   { transform: translateY(8%);  opacity: 0.35; }
    45%  { opacity: 0.9; }
    100% { transform: translateY(88%); opacity: 0.25; }
}

#guideFrame {
    overflow: hidden;
    backdrop-filter: blur(0.4px);
    background: linear-gradient(180deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0) 36%, rgba(255,255,255,0.04) 100%);
}

#guideScanLine {
    position: absolute;
    left: 8%; right: 8%;
    height: 2px;
    border-radius: 9999px;
    background: linear-gradient(90deg, rgba(16,185,129,0), rgba(52,211,153,.95), rgba(16,185,129,0));
    box-shadow: 0 0 10px rgba(52,211,153,.6);
    animation: ballot-scan-sweep 2.15s ease-in-out infinite;
}

.guide-corner {
    position: absolute;
    width: 2rem; height: 2rem;
    border-color: rgb(147,197,253);
    border-style: solid;
    border-width: 0;
    filter: drop-shadow(0 0 5px rgba(147,197,253,.5));
}
.guide-corner.top-left    { left:0; top:0; border-left-width:3px; border-top-width:3px; border-top-left-radius:.8rem; }
.guide-corner.top-right   { right:0; top:0; border-right-width:3px; border-top-width:3px; border-top-right-radius:.8rem; }
.guide-corner.bottom-left { left:0; bottom:0; border-left-width:3px; border-bottom-width:3px; border-bottom-left-radius:.8rem; }
.guide-corner.bottom-right{ right:0; bottom:0; border-right-width:3px; border-bottom-width:3px; border-bottom-right-radius:.8rem; }

#guideFrameGrid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(to bottom, rgba(148,163,184,.18) 1px, transparent 1px),
        linear-gradient(to right,  rgba(148,163,184,.12) 1px, transparent 1px);
    background-size: 100% 24%, 22% 100%;
    opacity: .22;
}

/* ─── Guide tip (plain v-show, toggled by top-bar ⓘ button) ─────────── */
#guideTip { pointer-events: none; }

@media (max-width: 1023px) {
    /* Info button in fullscreen top bar */
    body.mobile-camera-active #fsInfoBtn {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        border: 1px solid rgba(148,163,184,0.3);
        background: rgba(30,41,59,0.72);
        color: #f1f5f9;
        padding: 0;
        flex-shrink: 0;
    }
    body.mobile-camera-active #fsInfoBtn svg {
        width: 1.1rem;
        height: 1.1rem;
    }
}
</style>