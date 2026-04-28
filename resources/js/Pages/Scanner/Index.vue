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

const scanState = ref('Ready.');
const submitState = ref('Waiting for a successful scan.');

const pendingSubmission = ref(null);
const scanInProgress = ref(false);
const submitInProgress = ref(false);

const previewImageSrc = ref('');
const debugOverlaySrc = ref('');

const cameraGuideOverlayVisible = ref(false);
const cameraGuideStatus = ref('Camera idle.');
const cameraGuideHint = ref('Press Start to begin.');
const guideStabilityPct = ref(0);
const guideVisualState = ref('idle');

const mobileCameraActive = ref(false);
const mobileMenuOpen = ref(false);

const flashSupported = ref(false);
const flashEnabled = ref(false);

const isMobileViewport = () => window.matchMedia('(max-width: 1023px)').matches;

const flashButtonLabel = computed(() => {
    if (!cameraStream) return 'Flash unavailable';
    if (!flashSupported.value) return 'Flash unsupported';
    return flashEnabled.value ? 'Flash on' : 'Flash off';
});

const menuFlashButtonLabel = computed(() => {
    if (!cameraStream) return 'Flash unavailable';
    if (!flashSupported.value) return 'Flash unsupported';
    return flashEnabled.value ? 'Turn flash off' : 'Turn flash on';
});

const flashDisabled = computed(() => !cameraStream || !flashSupported.value);

const showConfirmPanel = computed(() => pendingSubmission.value !== null || submitState.value !== 'Waiting for a successful scan.');
const canSubmit = computed(() => pendingSubmission.value !== null && !submitInProgress.value);

let cameraStream = null;
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

const updateGuideStatus = (statusText, hintText, progress, state = 'searching') => {
    cameraGuideStatus.value = statusText;
    cameraGuideHint.value = hintText;
    guideStabilityPct.value = Math.max(0, Math.min(100, progress));
    setGuideVisualState(state);
};

const stopAlignmentWatcher = () => {
    if (alignmentInterval) {
        clearInterval(alignmentInterval);
        alignmentInterval = null;
    }

    stableFrameCount = 0;
    autoCaptureTriggered = false;
    autoCaptureCooldown = false;
    cooldownReleaseFrames = 0;
    cameraGuideOverlayVisible.value = false;
    updateGuideStatus('Camera idle.', 'Press Start to begin.', 0, 'idle');
};

const getActiveVideoTrack = () => (cameraStream ? cameraStream.getVideoTracks()[0] : null);

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
    if (!cameraStream) {
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
        closeMobileControlMenu();
        return;
    }

    mobileCameraActive.value = false;
    document.body.classList.remove('mobile-camera-active');
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

    const targetW = 360;
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
    if (!cameraStream || !cameraPreview.value || !captureCanvas.value) {
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

    alignmentInterval = window.setInterval(async () => {
        if (!cameraStream || autoCaptureTriggered || scanInProgress.value) {
            return;
        }

        const probe = estimateBallotAlignment();
        const baseProgress = Math.round(Math.max(0, Math.min(1, probe.confidence)) * 75);

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
            return;
        }

        if (!probe.aligned) {
            stableFrameCount = 0;
            updateGuideStatus('Waiting for valid ballot frame', probe.hint, baseProgress, 'searching');
            return;
        }

        stableFrameCount += 1;
        const stableProgress = Math.round((stableFrameCount / STABLE_FRAMES_REQUIRED) * 100);
        updateGuideStatus('Ballot aligned', `Hold still (${stableFrameCount}/${STABLE_FRAMES_REQUIRED})`, stableProgress, 'ready');

        if (stableFrameCount < STABLE_FRAMES_REQUIRED) {
            return;
        }

        autoCaptureTriggered = true;
        updateGuideStatus('Capturing...', 'Ballot detected. Capturing now.', 100, 'capturing');

        const blob = await captureFrameFromCamera();
        if (!blob) {
            stableFrameCount = 0;
            autoCaptureTriggered = false;
            updateGuideStatus('Align ballot to continue', 'Capture failed, hold still and try again.', 0, 'searching');
            return;
        }

        await runScan();

        stableFrameCount = 0;
        autoCaptureTriggered = false;
        autoCaptureCooldown = true;
        cooldownReleaseFrames = 0;
        updateGuideStatus('Auto-capture complete', 'Move ballot out of frame to arm next capture.', 100, 'ready');
    }, 260);
};

const startCamera = async () => {
    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'environment',
                width: { ideal: 1920 },
                height: { ideal: 1080 },
            },
            audio: false,
        });

        if (!cameraPreview.value) {
            scanState.value = 'Camera preview is unavailable.';
            return;
        }

        cameraPreview.value.srcObject = cameraStream;
        scanState.value = 'Camera is ready. Align ballot in the guide for auto-capture.';
        startAlignmentWatcher();
        setMobileCameraMode(true);
        detectTorchSupport();
    } catch (error) {
        scanState.value = `Camera error: ${error.message}`;
    }
};

const captureManual = async () => {
    const blob = await captureFrameFromCamera();
    if (blob) {
        stableFrameCount = 0;
        autoCaptureTriggered = false;
        autoCaptureCooldown = false;
        cooldownReleaseFrames = 0;
    }
};

const stopCamera = () => {
    if (cameraStream) {
        cameraStream.getTracks().forEach((track) => track.stop());
        cameraStream = null;
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
    if (scanInProgress.value) {
        return;
    }

    const file = capturedBlob || fileInput.value?.files?.[0];
    if (!file) {
        scanState.value = 'Choose or capture a ballot image first.';
        return;
    }

    scanInProgress.value = true;
    pendingSubmission.value = null;
    submitState.value = 'Waiting for a successful scan.';

    const formData = new FormData();
    formData.append('ballot_image', file, file.name || 'ballot.jpg');

    if (selectedElectionId.value) {
        formData.append('election_id', selectedElectionId.value);
    }

    if (ballotNumber.value.trim()) {
        formData.append('ballot_number', ballotNumber.value.trim());
    }

    scanState.value = 'Sending image to Laravel...';

    try {
        let finalScanUrl = props.scanUrl;
        if (debugMode.value) {
            const separator = finalScanUrl.includes('?') ? '&' : '?';
            finalScanUrl += `${separator}include_debug_image=true&include_debug_bubbles=true`;
        }

        const response = await fetch(finalScanUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken.value,
                Accept: 'application/json',
            },
            body: formData,
        });

        const payload = await parseJsonSafely(response);
        resultJsonText.value = JSON.stringify(payload, null, 2);

        setDebugOverlay(payload.debug_visualization_image || null);

        if (typeof payload.processed_preview_image === 'string' && payload.processed_preview_image.startsWith('data:image/')) {
            previewImageSrc.value = payload.processed_preview_image;
        }

        const preview = payload.scan_preview || null;
        const votes = (preview && Array.isArray(preview.detected_votes))
            ? preview.detected_votes
            : (payload.detected_votes || []);

        renderVoteList(votes);

        if (response.ok && preview && preview.can_submit && Array.isArray(preview.detected_votes) && preview.detected_votes.length > 0) {
            pendingSubmission.value = {
                image_hash: preview.image_hash,
                detected_votes: preview.detected_votes,
                election_id: selectedElectionId.value || null,
                ballot_number: ballotNumber.value.trim() || null,
            };

            submitState.value = 'Ready to submit. Please confirm.';
        } else {
            pendingSubmission.value = null;
            submitState.value = 'Cannot submit. Resolve scan warnings/errors first.';
        }

        scanState.value = response.ok ? 'Scan complete.' : 'Scan completed with an error response.';
    } catch (error) {
        resultJsonText.value = JSON.stringify({ success: false, message: error.message }, null, 2);
        detectedVotes.value = [];
        setDebugOverlay(null);
        scanState.value = 'Scan failed.';
        submitState.value = 'Scan failed. No submission payload available.';
        pendingSubmission.value = null;
    } finally {
        scanInProgress.value = false;
    }
};

const submitDetectedVotes = async () => {
    if (!pendingSubmission.value) {
        submitState.value = 'No validated scan data available to submit.';
        return;
    }

    const proceed = window.confirm('Submit this ballot and save votes to the database?');
    if (!proceed) {
        return;
    }

    submitInProgress.value = true;
    submitState.value = 'Submitting ballot...';

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
            submitState.value = `Saved ballot ${payload.ballot?.id ?? ''} with ${payload.votes_saved ?? 0} vote(s). Final saved votes are shown on the right.`;
            if (Array.isArray(payload.submitted_votes)) {
                renderVoteList(payload.submitted_votes);
            }

            pendingSubmission.value = null;
            capturedBlob = null;
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        } else {
            submitState.value = payload.message || 'Submission failed.';
        }
    } catch (error) {
        submitState.value = `Submission failed: ${error.message}`;
    } finally {
        submitInProgress.value = false;
    }
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

onMounted(() => {
    window.addEventListener('resize', handleResize);
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

    <div class="ui-page">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">Scanner</h1>
                <p class="text-sm text-gray-500 mt-1">Laravel is now calling the OMR service at {{ serviceUrl }}.</p>
            </div>
            <div class="text-sm text-gray-500">
                {{ positions.length }} position(s) · {{ layoutCount }} scan slot(s)
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 ui-card">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700 mb-1">Election</span>
                        <select v-model="selectedElectionId" class="block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Use current layout</option>
                            <option v-for="election in elections" :key="election.id" :value="String(election.id)">{{ election.label }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700 mb-1">Ballot number</span>
                        <input v-model="ballotNumber" type="text" class="block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Optional reference number" />
                    </label>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div id="mobileCameraShell" class="relative rounded-xl border border-slate-200 p-4 bg-slate-50">
                        <div id="mobileControlMenuContainer" class="hidden lg:hidden" v-show="mobileCameraActive">
                            <button type="button" id="mobileControlMenuBtn" class="hidden" @click="mobileMenuOpen = !mobileMenuOpen">Menu</button>
                            <div id="mobileControlMenuPanel" :class="mobileMenuOpen ? '' : 'hidden'">
                                <button type="button" class="mobile-control-item" @click="closeMobileControlMenu(); startCamera()">Start camera</button>
                                <button type="button" class="mobile-control-item" :disabled="flashDisabled" @click="closeMobileControlMenu(); toggleFlash()">{{ menuFlashButtonLabel }}</button>
                                <button type="button" class="mobile-control-item" @click="closeMobileControlMenu(); captureManual()">Capture frame</button>
                                <button type="button" class="mobile-control-item" @click="closeMobileControlMenu(); stopCamera()">Stop camera</button>
                                <button type="button" class="mobile-control-item" @click="closeMobileControlMenu(); setMobileCameraMode(false)">Exit fullscreen</button>
                            </div>
                        </div>

                        <div id="cameraCardHeader" class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="font-semibold text-gray-900">Camera</h3>
                                <p class="camera-help-text text-sm text-gray-500">Center the ballot in the guide. The scanner captures automatically once stable.</p>
                            </div>
                            <div class="flex flex-wrap gap-2 justify-end">
                                <button type="button" id="enterMobileCameraBtn" class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-white lg:hidden" @click="setMobileCameraMode(true)">Full screen</button>
                                <button type="button" id="startCameraBtn" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700" @click="startCamera">Start</button>
                                <button type="button" id="flashToggleBtn" class="inline-flex items-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-100 disabled:opacity-50" :disabled="flashDisabled" @click="toggleFlash">{{ flashButtonLabel }}</button>
                                <button type="button" id="captureBtn" class="inline-flex items-center rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900" @click="captureManual">Capture</button>
                                <button type="button" id="stopCameraBtn" class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-white" @click="stopCamera">Stop</button>
                                <button type="button" id="exitMobileCameraBtn" class="hidden items-center rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700" @click="setMobileCameraMode(false)">Close</button>
                            </div>
                        </div>

                        <div id="cameraStage" ref="cameraStage" class="relative overflow-hidden rounded-lg bg-black aspect-[4/3]">
                            <video ref="cameraPreview" id="cameraPreview" class="absolute inset-0 w-full h-full object-cover" autoplay playsinline muted></video>

                            <div id="cameraGuideOverlay" class="pointer-events-none absolute inset-0" :class="cameraGuideOverlayVisible ? '' : 'hidden'">
                                <div id="guideMaskTop" class="guide-mask absolute left-0 right-0 top-0 h-[6%]"></div>
                                <div id="guideMaskBottom" class="guide-mask absolute bottom-0 left-0 right-0 h-[6%]"></div>
                                <div id="guideMaskLeft" class="guide-mask absolute left-0 top-[6%] bottom-[6%] w-[8%] md:w-[19%]"></div>
                                <div id="guideMaskRight" class="guide-mask absolute right-0 top-[6%] bottom-[6%] w-[8%] md:w-[19%]"></div>

                                <div id="guideFrame" ref="guideFrame" class="absolute left-1/2 top-1/2 h-[88%] w-[84%] md:h-[84%] md:w-[62%] -translate-x-1/2 -translate-y-1/2 rounded-2xl border-2 transition-colors duration-200" :class="guideFrameClass">
                                    <div id="guideFrameGrid"></div>
                                    <div id="guideScanLine"></div>
                                    <div class="guide-corner top-left"></div>
                                    <div class="guide-corner top-right"></div>
                                    <div class="guide-corner bottom-left"></div>
                                    <div class="guide-corner bottom-right"></div>
                                </div>

                                <div data-guide-tip class="absolute left-3 right-3 top-3 rounded-lg bg-slate-900/65 px-3 py-2 text-xs text-slate-100">
                                    Keep the whole ballot inside the frame. Hold still for auto-capture.
                                </div>
                            </div>
                        </div>

                        <canvas ref="captureCanvas" class="hidden"></canvas>
                        <canvas ref="analysisCanvas" class="hidden"></canvas>

                        <div id="cameraStatusPanel" class="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <div class="flex items-center justify-between gap-3">
                                <div id="cameraGuideStatus" class="text-sm font-medium text-slate-700">{{ cameraGuideStatus }}</div>
                                <div id="cameraGuideHint" class="text-xs text-slate-500">{{ cameraGuideHint }}</div>
                            </div>
                            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                                <div id="guideStabilityBar" class="h-full rounded-full transition-all duration-150" :class="guideBarClass" :style="{ width: `${guideStabilityPct}%` }"></div>
                            </div>
                        </div>

                        <div class="camera-help-text mt-3 text-sm text-gray-500">Automatic mode captures and starts scanning once alignment is stable. Manual capture remains available.</div>
                    </div>

                    <div class="rounded-xl border border-slate-200 p-4 bg-white">
                        <h3 class="font-semibold text-gray-900 mb-3">Upload fallback</h3>
                        <input ref="fileInput" type="file" accept="image/*" class="block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-white hover:file:bg-indigo-700" @change="onFileChange" />
                        <div class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3">
                            <img v-if="previewImageSrc" :src="previewImageSrc" alt="Ballot preview" class="w-full rounded-md object-contain max-h-72" />
                            <div v-else class="text-sm text-slate-500">The latest captured or uploaded image will appear here.</div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60" :disabled="scanInProgress" @click="runScan">Scan with OMR</button>
                    <span class="text-sm text-gray-500">{{ scanState }}</span>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="debugMode" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-gray-600">Debug mode (includes detailed bubble measurements)</span>
                    </label>
                </div>

                <div v-if="showConfirmPanel" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <h3 class="font-semibold text-amber-900">Confirm before final submit</h3>
                    <p class="text-sm text-amber-800 mt-1">Review detected votes first. Submit will save one ballot record and vote rows permanently.</p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <button type="button" class="inline-flex items-center rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-60" :disabled="!canSubmit" @click="submitDetectedVotes">Confirm and Submit Ballot</button>
                        <span class="text-sm text-amber-800">{{ submitState }}</span>
                    </div>
                </div>
            </div>

            <div class="ui-card">
                <div>
                    <h3 class="font-semibold text-gray-900">Scan result</h3>
                    <p class="text-sm text-gray-500">Responses are proxied through Laravel.</p>
                </div>
                <div class="mt-3 rounded-lg bg-slate-950 p-4 text-sm text-slate-100 h-72 overflow-auto">
                    <pre class="whitespace-pre-wrap break-all">{{ resultJsonText }}</pre>
                </div>
                <div class="mt-4">
                    <h4 class="font-medium text-gray-900 mb-2">Detected votes</h4>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li v-if="!detectedVotes.length" class="text-slate-500">No votes detected.</li>
                        <li v-for="(vote, index) in detectedVotes" :key="`vote-${index}-${vote.candidate_id ?? 'x'}`" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            {{ vote.candidate_name || 'Candidate' }}{{ vote.candidate_party ? `, ${vote.candidate_party}` : '' }} · {{ vote.position_name || `Position ${vote.position_id}` }}{{ Number.isFinite(Number(vote.confidence)) ? ` · Confidence ${(Number(vote.confidence) * 100).toFixed(1)}%` : '' }}
                        </li>
                    </ul>
                </div>

                <div class="mt-4">
                    <h4 class="font-medium text-gray-900 mb-2">Scanner debug overlay (temporary)</h4>
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3">
                        <img v-if="debugOverlaySrc" :src="debugOverlaySrc" alt="Scanner debug overlay" class="w-full rounded-md object-contain max-h-80" />
                        <div v-else class="text-sm text-slate-500">No debug overlay yet.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ui-card">
            <h3 class="font-semibold text-gray-900 mb-3">Current layout preview</h3>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <template v-if="positions.length">
                    <div v-for="position in positions" :key="position.id" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="text-sm font-semibold text-gray-900">{{ position.name }}</div>
                            <div class="text-xs text-indigo-700 text-right shrink-0">Vote for up to {{ Math.max(1, Number(position.votes_allowed ?? 1)) }} candidate(s)</div>
                        </div>
                        <div class="mt-2 space-y-1 text-sm text-gray-600">
                            <div v-for="candidate in position.candidates" :key="candidate.id">{{ candidate.name }}</div>
                        </div>
                    </div>
                </template>
                <div v-else class="text-sm text-gray-500">No active positions or candidates are available yet.</div>
            </div>
        </div>
    </div>
</template>

<style>
@keyframes ballot-scan-sweep {
    0% {
        transform: translateY(8%);
        opacity: 0.35;
    }
    45% {
        opacity: 0.9;
    }
    100% {
        transform: translateY(88%);
        opacity: 0.25;
    }
}

#guideFrame {
    overflow: hidden;
    backdrop-filter: blur(0.4px);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.04) 0%, rgba(255, 255, 255, 0) 36%, rgba(255, 255, 255, 0.04) 100%);
}

#guideScanLine {
    position: absolute;
    left: 8%;
    right: 8%;
    height: 2px;
    border-radius: 9999px;
    background: linear-gradient(90deg, rgba(16, 185, 129, 0), rgba(52, 211, 153, 0.95), rgba(16, 185, 129, 0));
    box-shadow: 0 0 10px rgba(52, 211, 153, 0.6);
    animation: ballot-scan-sweep 2.15s ease-in-out infinite;
}

.guide-corner {
    position: absolute;
    width: 2rem;
    height: 2rem;
    border-color: rgb(196 181 253);
    border-style: solid;
    border-width: 0;
    filter: drop-shadow(0 0 4px rgba(196, 181, 253, 0.4));
}

.guide-corner.top-left {
    left: 0;
    top: 0;
    border-left-width: 4px;
    border-top-width: 4px;
    border-top-left-radius: 0.8rem;
}

.guide-corner.top-right {
    right: 0;
    top: 0;
    border-right-width: 4px;
    border-top-width: 4px;
    border-top-right-radius: 0.8rem;
}

.guide-corner.bottom-left {
    left: 0;
    bottom: 0;
    border-left-width: 4px;
    border-bottom-width: 4px;
    border-bottom-left-radius: 0.8rem;
}

.guide-corner.bottom-right {
    right: 0;
    bottom: 0;
    border-right-width: 4px;
    border-bottom-width: 4px;
    border-bottom-right-radius: 0.8rem;
}

#guideFrameGrid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(to bottom, rgba(148, 163, 184, 0.18) 1px, transparent 1px),
        linear-gradient(to right, rgba(148, 163, 184, 0.12) 1px, transparent 1px);
    background-size: 100% 24%, 22% 100%;
    opacity: 0.22;
}

@media (max-width: 1023px) {
    body.mobile-camera-active {
        overflow: hidden;
        touch-action: none;
    }

    body.mobile-camera-active #mobileCameraShell {
        position: fixed;
        inset: 0;
        z-index: 70;
        margin: 0;
        border: 0;
        border-radius: 0;
        background: transparent;
        padding: 0;
        box-shadow: none;
    }

    body.mobile-camera-active #cameraCardHeader {
        display: none;
    }

    body.mobile-camera-active #mobileControlMenuContainer {
        display: block;
        position: fixed;
        right: 0.75rem;
        top: calc(env(safe-area-inset-top, 0px) + 0.75rem);
        z-index: 76;
    }

    body.mobile-camera-active #mobileControlMenuBtn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        background: rgba(15, 23, 42, 0.88);
        color: #e2e8f0;
        border: 1px solid rgba(148, 163, 184, 0.4);
        backdrop-filter: blur(8px);
        padding: 0.5rem 0.85rem;
        font-size: 0.8125rem;
        font-weight: 600;
    }

    body.mobile-camera-active #mobileControlMenuPanel {
        margin-top: 0.5rem;
        min-width: 160px;
        border-radius: 0.75rem;
        border: 1px solid rgba(148, 163, 184, 0.35);
        background: rgba(15, 23, 42, 0.92);
        box-shadow: 0 12px 24px rgba(2, 6, 23, 0.45);
        overflow: hidden;
    }

    body.mobile-camera-active .mobile-control-item {
        width: 100%;
        text-align: left;
        padding: 0.65rem 0.85rem;
        color: #e2e8f0;
        font-size: 0.875rem;
        border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        background: transparent;
    }

    body.mobile-camera-active .mobile-control-item:last-child {
        border-bottom: 0;
    }

    body.mobile-camera-active .mobile-control-item:active {
        background: rgba(148, 163, 184, 0.2);
    }

    body.mobile-camera-active .mobile-control-item[disabled] {
        opacity: 0.45;
    }

    body.mobile-camera-active #cameraStage {
        position: fixed;
        inset: 0;
        height: 100dvh;
        min-height: 100dvh;
        border-radius: 0;
        aspect-ratio: auto;
    }

    body.mobile-camera-active #cameraPreview {
        object-fit: cover;
        object-position: center center;
    }

    body.mobile-camera-active #cameraGuideOverlay {
        --guide-top-inset: 6%;
        --guide-side-inset: 8%;
    }

    body.mobile-camera-active .guide-mask {
        background: rgba(2, 6, 23, 0.16);
    }

    body.mobile-camera-active #guideMaskTop,
    body.mobile-camera-active #guideMaskBottom {
        height: var(--guide-top-inset);
    }

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
        bottom: var(--guide-top-inset);
        width: auto;
        height: auto;
        transform: none;
    }

    body.mobile-camera-active #guideFrameGrid {
        opacity: 0.16;
    }

    body.mobile-camera-active #guideScanLine {
        animation-duration: 1.85s;
    }

    body.mobile-camera-active #cameraGuideOverlay [data-guide-tip] {
        background: rgba(15, 23, 42, 0.55);
        top: calc(env(safe-area-inset-top, 0px) + 0.75rem);
        right: 4.75rem;
    }

    body.mobile-camera-active #cameraGuideStatus,
    body.mobile-camera-active #cameraGuideHint,
    body.mobile-camera-active .camera-help-text {
        color: #e2e8f0;
    }

    body.mobile-camera-active #cameraStatusPanel {
        position: fixed;
        left: 0.5rem;
        right: 0.5rem;
        bottom: calc(env(safe-area-inset-bottom, 0px) + 0.5rem);
        z-index: 74;
        margin-top: 0;
        border-color: rgba(148, 163, 184, 0.35);
        background: rgba(15, 23, 42, 0.85);
    }

    body.mobile-camera-active #mobileCameraShell .camera-help-text,
    body.mobile-camera-active #enterMobileCameraBtn,
    body.mobile-camera-active #startCameraBtn,
    body.mobile-camera-active #flashToggleBtn,
    body.mobile-camera-active #captureBtn,
    body.mobile-camera-active #stopCameraBtn,
    body.mobile-camera-active #exitMobileCameraBtn {
        display: none;
    }
}

@media (min-width: 640px) and (max-width: 1023px) {
    body.mobile-camera-active #cameraGuideOverlay {
        --guide-side-inset: 12%;
    }
}
</style>
