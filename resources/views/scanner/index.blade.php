<x-app-layout>
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

            body.mobile-camera-active #mobileCameraOverlayScrim {
                display: none;
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

            body.mobile-camera-active #mobileCameraShell .camera-help-text {
                display: none;
            }

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

    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Scanner') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Laravel is now calling the OMR service at {{ $serviceUrl }}.</p>
            </div>
            <div class="text-sm text-gray-500">
                {{ $positions->count() }} position(s) · {{ $layoutCount }} scan slot(s)
            </div>
        </div>
    </x-slot>

    <div class="py-12 bg-gradient-to-b from-slate-50 to-white">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-xl border border-slate-100">
                    <div class="p-6 space-y-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="block text-sm font-medium text-gray-700 mb-1">Election</span>
                                <select id="election_id" class="block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Use current layout</option>
                                    @foreach ($elections as $election)
                                        <option value="{{ $election->id }}">{{ $election->label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="block text-sm font-medium text-gray-700 mb-1">Ballot number</span>
                                <input id="ballot_number" type="text" class="block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Optional reference number">
                            </label>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div id="mobileCameraShell" class="relative rounded-xl border border-slate-200 p-4 bg-slate-50">
                                <div id="mobileCameraOverlayScrim" class="pointer-events-none absolute inset-0 hidden rounded-xl"></div>
                                <div id="mobileControlMenuContainer" class="hidden lg:hidden">
                                    <button type="button" id="mobileControlMenuBtn" class="hidden">Menu</button>
                                    <div id="mobileControlMenuPanel" class="hidden">
                                        <button type="button" id="menuStartCameraBtn" class="mobile-control-item">Start camera</button>
                                        <button type="button" id="menuFlashToggleBtn" class="mobile-control-item" disabled>Flash unavailable</button>
                                        <button type="button" id="menuCaptureBtn" class="mobile-control-item">Capture frame</button>
                                        <button type="button" id="menuStopCameraBtn" class="mobile-control-item">Stop camera</button>
                                        <button type="button" id="menuExitFullscreenBtn" class="mobile-control-item">Exit fullscreen</button>
                                    </div>
                                </div>
                                <div id="cameraCardHeader" class="flex items-center justify-between mb-3">
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Camera</h3>
                                        <p class="camera-help-text text-sm text-gray-500">Center the ballot in the guide. The scanner captures automatically once stable.</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2 justify-end">
                                        <button type="button" id="enterMobileCameraBtn" class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-white lg:hidden">Full screen</button>
                                        <button type="button" id="startCameraBtn" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Start</button>
                                        <button type="button" id="flashToggleBtn" class="inline-flex items-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-100 disabled:opacity-50" disabled>Flash unavailable</button>
                                        <button type="button" id="captureBtn" class="inline-flex items-center rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900">Capture</button>
                                        <button type="button" id="stopCameraBtn" class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-white">Stop</button>
                                        <button type="button" id="exitMobileCameraBtn" class="hidden items-center rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700">Close</button>
                                    </div>
                                </div>
                                <div id="cameraStage" class="relative overflow-hidden rounded-lg bg-black aspect-[4/3]">
                                    <video id="cameraPreview" class="absolute inset-0 w-full h-full object-cover" autoplay playsinline muted></video>
                                    <div id="cameraGuideOverlay" class="pointer-events-none absolute inset-0 hidden">
                                        <div id="guideMaskTop" class="guide-mask absolute left-0 right-0 top-0 h-[6%]"></div>
                                        <div id="guideMaskBottom" class="guide-mask absolute bottom-0 left-0 right-0 h-[6%]"></div>
                                        <div id="guideMaskLeft" class="guide-mask absolute left-0 top-[6%] bottom-[6%] w-[8%] md:w-[19%]"></div>
                                        <div id="guideMaskRight" class="guide-mask absolute right-0 top-[6%] bottom-[6%] w-[8%] md:w-[19%]"></div>
                                        <div id="guideFrame" class="absolute left-1/2 top-1/2 h-[88%] w-[84%] md:h-[84%] md:w-[62%] -translate-x-1/2 -translate-y-1/2 rounded-2xl border-2 border-amber-300/95 transition-colors duration-200">
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
                                <canvas id="captureCanvas" class="hidden"></canvas>
                                <canvas id="analysisCanvas" class="hidden"></canvas>
                                <div id="cameraStatusPanel" class="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <div id="cameraGuideStatus" class="text-sm font-medium text-slate-700">Camera idle.</div>
                                        <div id="cameraGuideHint" class="text-xs text-slate-500">Press Start to begin.</div>
                                    </div>
                                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                                        <div id="guideStabilityBar" class="h-full w-0 rounded-full bg-amber-400 transition-all duration-150"></div>
                                    </div>
                                </div>
                                <div class="camera-help-text mt-3 text-sm text-gray-500">Automatic mode captures and starts scanning once alignment is stable. Manual capture remains available.</div>
                            </div>

                            <div class="rounded-xl border border-slate-200 p-4 bg-white">
                                <h3 class="font-semibold text-gray-900 mb-3">Upload fallback</h3>
                                <input id="ballot_image" type="file" accept="image/*" class="block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-white hover:file:bg-indigo-700">
                                <div class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3">
                                    <img id="previewImage" alt="Ballot preview" class="hidden w-full rounded-md object-contain max-h-72">
                                    <div id="previewPlaceholder" class="text-sm text-slate-500">The latest captured or uploaded image will appear here.</div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" id="scanBtn" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Scan with OMR</button>
                            <span id="scanState" class="text-sm text-gray-500">Ready.</span>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" id="debugMode" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-gray-600">Debug mode (includes detailed bubble measurements)</span>
                            </label>
                        </div>

                        <div id="confirmPanel" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <h3 class="font-semibold text-amber-900">Confirm before final submit</h3>
                            <p class="text-sm text-amber-800 mt-1">Review detected votes first. Submit will save one ballot record and vote rows permanently.</p>
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <button type="button" id="submitBtn" class="inline-flex items-center rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Confirm and Submit Ballot</button>
                                <span id="submitState" class="text-sm text-amber-800">Waiting for a successful scan.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-slate-100">
                    <div class="p-6 space-y-4">
                        <div>
                            <h3 class="font-semibold text-gray-900">Scan result</h3>
                            <p class="text-sm text-gray-500">Responses are proxied through Laravel.</p>
                        </div>
                        <div class="rounded-lg bg-slate-950 p-4 text-sm text-slate-100 h-72 overflow-auto">
                            <pre id="resultJson" class="whitespace-pre-wrap break-all">No scan yet.</pre>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Detected votes</h4>
                            <ul id="voteList" class="space-y-2 text-sm text-gray-600"></ul>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Scanner debug overlay (temporary)</h4>
                            <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3">
                                <img id="debugOverlayImage" alt="Scanner debug overlay" class="hidden w-full rounded-md object-contain max-h-80">
                                <div id="debugOverlayPlaceholder" class="text-sm text-slate-500">No debug overlay yet.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-slate-100">
                <div class="p-6">
                    <h3 class="font-semibold text-gray-900 mb-3">Current layout preview</h3>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @forelse ($positions as $position)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="text-sm font-semibold text-gray-900">{{ $position->name }}</div>
                                    <div class="text-xs text-indigo-700 text-right shrink-0">Vote for up to {{ max(1, (int) ($position->votes_allowed ?? 1)) }} candidate(s)</div>
                                </div>
                                <div class="mt-2 space-y-1 text-sm text-gray-600">
                                    @foreach ($position->candidates as $candidate)
                                        <div>{{ $candidate->name }}</div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-gray-500">No active positions or candidates are available yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const scanUrl = @json(route('scanner.scan'));
            const submitUrl = @json(route('scanner.submit'));
            const csrfToken = @json(csrf_token());
            const resultJson = document.getElementById('resultJson');
            const voteList = document.getElementById('voteList');
            const scanState = document.getElementById('scanState');
            const confirmPanel = document.getElementById('confirmPanel');
            const submitBtn = document.getElementById('submitBtn');
            const submitState = document.getElementById('submitState');
            const fileInput = document.getElementById('ballot_image');
            const previewImage = document.getElementById('previewImage');
            const previewPlaceholder = document.getElementById('previewPlaceholder');
            const debugOverlayImage = document.getElementById('debugOverlayImage');
            const debugOverlayPlaceholder = document.getElementById('debugOverlayPlaceholder');
            const cameraPreview = document.getElementById('cameraPreview');
            const cameraStage = document.getElementById('cameraStage');
            const captureCanvas = document.getElementById('captureCanvas');
            const electionSelect = document.getElementById('election_id');
            const ballotNumber = document.getElementById('ballot_number');
            const startCameraBtn = document.getElementById('startCameraBtn');
            const flashToggleBtn = document.getElementById('flashToggleBtn');
            const captureBtn = document.getElementById('captureBtn');
            const stopCameraBtn = document.getElementById('stopCameraBtn');
            const scanBtn = document.getElementById('scanBtn');
            const debugMode = document.getElementById('debugMode');
            const cameraGuideOverlay = document.getElementById('cameraGuideOverlay');
            const guideFrame = document.getElementById('guideFrame');
            const cameraGuideStatus = document.getElementById('cameraGuideStatus');
            const cameraGuideHint = document.getElementById('cameraGuideHint');
            const guideStabilityBar = document.getElementById('guideStabilityBar');
            const analysisCanvas = document.getElementById('analysisCanvas');
            const enterMobileCameraBtn = document.getElementById('enterMobileCameraBtn');
            const exitMobileCameraBtn = document.getElementById('exitMobileCameraBtn');
            const mobileControlMenuContainer = document.getElementById('mobileControlMenuContainer');
            const mobileControlMenuBtn = document.getElementById('mobileControlMenuBtn');
            const mobileControlMenuPanel = document.getElementById('mobileControlMenuPanel');
            const menuStartCameraBtn = document.getElementById('menuStartCameraBtn');
            const menuFlashToggleBtn = document.getElementById('menuFlashToggleBtn');
            const menuCaptureBtn = document.getElementById('menuCaptureBtn');
            const menuStopCameraBtn = document.getElementById('menuStopCameraBtn');
            const menuExitFullscreenBtn = document.getElementById('menuExitFullscreenBtn');

            let cameraStream = null;
            let capturedBlob = null;
            let pendingSubmission = null;
            let scanInProgress = false;
            let alignmentInterval = null;
            let stableFrameCount = 0;
            let autoCaptureTriggered = false;
            let autoCaptureCooldown = false;
            let cooldownReleaseFrames = 0;
            let flashSupported = false;
            let flashEnabled = false;

            const STABLE_FRAMES_REQUIRED = 6;
            const isMobileViewport = () => window.matchMedia('(max-width: 1023px)').matches;
            const getActiveVideoTrack = () => cameraStream ? cameraStream.getVideoTracks()[0] : null;
            const updateFlashButtons = () => {
                if (!cameraStream) {
                    flashToggleBtn.textContent = 'Flash unavailable';
                    flashToggleBtn.disabled = true;
                    menuFlashToggleBtn.textContent = 'Flash unavailable';
                    menuFlashToggleBtn.disabled = true;
                    return;
                }

                if (!flashSupported) {
                    flashToggleBtn.textContent = 'Flash unsupported';
                    flashToggleBtn.disabled = true;
                    menuFlashToggleBtn.textContent = 'Flash unsupported';
                    menuFlashToggleBtn.disabled = true;
                    return;
                }

                if (flashEnabled) {
                    flashToggleBtn.textContent = 'Flash on';
                    menuFlashToggleBtn.textContent = 'Turn flash off';
                } else {
                    flashToggleBtn.textContent = 'Flash off';
                    menuFlashToggleBtn.textContent = 'Turn flash on';
                }

                flashToggleBtn.disabled = false;
                menuFlashToggleBtn.disabled = false;
            };
            const detectTorchSupport = () => {
                const track = getActiveVideoTrack();
                flashSupported = false;

                if (track && typeof track.getCapabilities === 'function') {
                    const capabilities = track.getCapabilities();
                    flashSupported = Boolean(capabilities && capabilities.torch);
                }

                if (!flashSupported) {
                    flashEnabled = false;
                }

                updateFlashButtons();
            };
            const setTorchState = async (enabled) => {
                const track = getActiveVideoTrack();
                if (!track || !flashSupported) {
                    return false;
                }

                await track.applyConstraints({
                    advanced: [{ torch: Boolean(enabled) }],
                });

                flashEnabled = Boolean(enabled);
                updateFlashButtons();
                return true;
            };
            const toggleFlash = async () => {
                if (!cameraStream) {
                    scanState.textContent = 'Start the camera before toggling flash.';
                    return;
                }

                if (!flashSupported) {
                    scanState.textContent = 'Flash is not supported on this device camera.';
                    return;
                }

                try {
                    const nextState = !flashEnabled;
                    await setTorchState(nextState);
                    scanState.textContent = nextState ? 'Flash enabled.' : 'Flash disabled.';
                } catch (error) {
                    scanState.textContent = `Flash error: ${error.message}`;
                }
            };
            const closeMobileControlMenu = () => {
                mobileControlMenuPanel.classList.add('hidden');
                mobileControlMenuBtn.setAttribute('aria-expanded', 'false');
            };
            const setMobileCameraMode = (enabled) => {
                if (enabled && isMobileViewport()) {
                    document.body.classList.add('mobile-camera-active');
                    closeMobileControlMenu();
                    return;
                }
                document.body.classList.remove('mobile-camera-active');
                closeMobileControlMenu();
            };

            const setGuideVisualState = (state) => {
                if (!guideFrame) {
                    return;
                }

                const palette = {
                    idle: {
                        frame: 'border-amber-300/95',
                        bar: 'bg-amber-400',
                    },
                    searching: {
                        frame: 'border-amber-300/95',
                        bar: 'bg-amber-400',
                    },
                    ready: {
                        frame: 'border-emerald-300/95',
                        bar: 'bg-emerald-500',
                    },
                    capturing: {
                        frame: 'border-sky-300/95',
                        bar: 'bg-sky-500',
                    },
                };

                const current = palette[state] || palette.idle;
                guideFrame.classList.remove('border-amber-300/95', 'border-emerald-300/95', 'border-sky-300/95');
                guideStabilityBar.classList.remove('bg-amber-400', 'bg-emerald-500', 'bg-sky-500');
                guideFrame.classList.add(current.frame);
                guideStabilityBar.classList.add(current.bar);
            };

            const updateGuideStatus = (statusText, hintText, progress, state = 'searching') => {
                cameraGuideStatus.textContent = statusText;
                cameraGuideHint.textContent = hintText;
                guideStabilityBar.style.width = `${Math.max(0, Math.min(100, progress))}%`;
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
                updateGuideStatus('Camera idle.', 'Press Start to begin.', 0, 'idle');
            };

            enterMobileCameraBtn.addEventListener('click', () => {
                setMobileCameraMode(true);
            });

            exitMobileCameraBtn.addEventListener('click', () => {
                setMobileCameraMode(false);
            });

            mobileControlMenuBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                const isHidden = mobileControlMenuPanel.classList.contains('hidden');
                if (isHidden) {
                    mobileControlMenuPanel.classList.remove('hidden');
                    mobileControlMenuBtn.setAttribute('aria-expanded', 'true');
                    return;
                }
                closeMobileControlMenu();
            });

            menuStartCameraBtn.addEventListener('click', () => {
                closeMobileControlMenu();
                startCameraBtn.click();
            });

            menuFlashToggleBtn.addEventListener('click', async () => {
                closeMobileControlMenu();
                await toggleFlash();
            });

            menuCaptureBtn.addEventListener('click', () => {
                closeMobileControlMenu();
                captureBtn.click();
            });

            menuStopCameraBtn.addEventListener('click', () => {
                closeMobileControlMenu();
                stopCameraBtn.click();
            });

            menuExitFullscreenBtn.addEventListener('click', () => {
                closeMobileControlMenu();
                setMobileCameraMode(false);
            });

            document.addEventListener('click', (event) => {
                if (!document.body.classList.contains('mobile-camera-active')) {
                    return;
                }
                if (mobileControlMenuContainer.contains(event.target)) {
                    return;
                }
                closeMobileControlMenu();
            });

            flashToggleBtn.addEventListener('click', async () => {
                await toggleFlash();
            });

            window.addEventListener('resize', () => {
                if (!isMobileViewport()) {
                    setMobileCameraMode(false);
                }
            });

            const estimateBallotAlignment = () => {
                const frameW = cameraPreview.videoWidth || 0;
                const frameH = cameraPreview.videoHeight || 0;

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

                analysisCanvas.width = analysisW;
                analysisCanvas.height = analysisH;
                const ctx = analysisCanvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(cameraPreview, 0, 0, analysisW, analysisH);

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
                if (!cameraStream) {
                    scanState.textContent = 'Start the camera before capturing.';
                    resolve(null);
                    return;
                }

                const sourceW = cameraPreview.videoWidth || 1280;
                const sourceH = cameraPreview.videoHeight || 960;

                const computeGuideCropRect = () => {
                    const stageRect = cameraStage?.getBoundingClientRect();
                    const guideRect = guideFrame?.getBoundingClientRect();

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

                captureCanvas.width = sw;
                captureCanvas.height = sh;

                const context = captureCanvas.getContext('2d');
                context.drawImage(
                    cameraPreview,
                    sx,
                    sy,
                    sw,
                    sh,
                    0,
                    0,
                    captureCanvas.width,
                    captureCanvas.height
                );

                captureCanvas.toBlob((blob) => {
                    if (!blob) {
                        scanState.textContent = 'Unable to capture the current frame.';
                        resolve(null);
                        return;
                    }

                    setPreviewFromBlob(blob);
                    scanState.textContent = 'Guide area captured. Ready to scan.';
                    resolve(blob);
                }, 'image/jpeg', 0.95);
            });

            const startAlignmentWatcher = () => {
                stopAlignmentWatcher();
                cameraGuideOverlay.classList.remove('hidden');
                updateGuideStatus('Searching for ballot...', 'Center the full ballot inside the frame.', 0, 'searching');

                alignmentInterval = window.setInterval(async () => {
                    if (!cameraStream || autoCaptureTriggered || scanInProgress) {
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

            const renderVoteList = (votes) => {
                voteList.innerHTML = '';

                if (!Array.isArray(votes) || votes.length === 0) {
                    voteList.innerHTML = '<li class="text-slate-500">No votes detected.</li>';
                    return;
                }

                votes.forEach((vote) => {
                    const item = document.createElement('li');
                    item.className = 'rounded-lg border border-slate-200 bg-slate-50 px-3 py-2';
                    const positionLabel = vote.position_name || `Position ${vote.position_id}`;
                    const partyLabel = vote.candidate_party ? `, ${vote.candidate_party}` : '';
                    const confidenceValue = Number(vote.confidence);
                    const confidenceLabel = Number.isFinite(confidenceValue)
                        ? ` · Confidence ${(confidenceValue * 100).toFixed(1)}%`
                        : '';
                    item.textContent = `${vote.candidate_name || 'Candidate'}${partyLabel} · ${positionLabel}${confidenceLabel}`;
                    voteList.appendChild(item);
                });
            };

            const setDebugOverlay = (imageDataUrl) => {
                if (typeof imageDataUrl === 'string' && imageDataUrl.startsWith('data:image/')) {
                    debugOverlayImage.src = imageDataUrl;
                    debugOverlayImage.classList.remove('hidden');
                    debugOverlayPlaceholder.classList.add('hidden');
                    return;
                }

                debugOverlayImage.src = '';
                debugOverlayImage.classList.add('hidden');
                debugOverlayPlaceholder.classList.remove('hidden');
            };

            const setPreviewFromBlob = (blob) => {
                if (!blob) {
                    return;
                }

                const url = URL.createObjectURL(blob);
                previewImage.src = url;
                previewImage.classList.remove('hidden');
                previewPlaceholder.classList.add('hidden');
                capturedBlob = blob;
            };

            fileInput.addEventListener('change', () => {
                const file = fileInput.files?.[0];
                if (!file) {
                    return;
                }

                setPreviewFromBlob(file);
                pendingSubmission = null;
                confirmPanel.classList.add('hidden');
                submitState.textContent = 'Waiting for a successful scan.';
                setDebugOverlay(null);
            });

            startCameraBtn.addEventListener('click', async () => {
                try {
                    cameraStream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'environment',
                            width: { ideal: 1920 },
                            height: { ideal: 1080 },
                        },
                        audio: false,
                    });

                    cameraPreview.srcObject = cameraStream;
                    scanState.textContent = 'Camera is ready. Align ballot in the guide for auto-capture.';
                    startAlignmentWatcher();
                    setMobileCameraMode(true);
                    detectTorchSupport();
                } catch (error) {
                    scanState.textContent = `Camera error: ${error.message}`;
                }
            });

            captureBtn.addEventListener('click', async () => {
                const blob = await captureFrameFromCamera();
                if (blob) {
                    stableFrameCount = 0;
                    autoCaptureTriggered = false;
                    autoCaptureCooldown = false;
                    cooldownReleaseFrames = 0;
                }
            });

            stopCameraBtn.addEventListener('click', () => {
                if (cameraStream) {
                    cameraStream.getTracks().forEach((track) => track.stop());
                    cameraStream = null;
                }

                flashEnabled = false;
                flashSupported = false;
                updateFlashButtons();

                cameraPreview.srcObject = null;
                cameraGuideOverlay.classList.add('hidden');
                stopAlignmentWatcher();
                setMobileCameraMode(false);
                scanState.textContent = 'Camera stopped.';
            });

            updateFlashButtons();

            const runScan = async () => {
                if (scanInProgress) {
                    return;
                }

                const file = capturedBlob || fileInput.files?.[0];

                if (!file) {
                    scanState.textContent = 'Choose or capture a ballot image first.';
                    return;
                }

                scanInProgress = true;

                const formData = new FormData();
                formData.append('ballot_image', file, file.name || 'ballot.jpg');

                if (electionSelect.value) {
                    formData.append('election_id', electionSelect.value);
                }

                if (ballotNumber.value.trim()) {
                    formData.append('ballot_number', ballotNumber.value.trim());
                }

                scanState.textContent = 'Sending image to Laravel...';
                scanBtn.disabled = true;
                submitBtn.disabled = true;
                pendingSubmission = null;
                confirmPanel.classList.add('hidden');
                submitState.textContent = 'Waiting for a successful scan.';

                try {
                    // Build scan URL with debug query parameters if enabled
                    let finalScanUrl = scanUrl;
                    if (debugMode.checked) {
                        const separator = scanUrl.includes('?') ? '&' : '?';
                        finalScanUrl = scanUrl + separator + 'include_debug_image=true&include_debug_bubbles=true';
                    }

                    const response = await fetch(finalScanUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const payload = await response.json();

                    resultJson.textContent = JSON.stringify(payload, null, 2);
                    setDebugOverlay(payload.debug_visualization_image || null);
                    if (typeof payload.processed_preview_image === 'string' && payload.processed_preview_image.startsWith('data:image/')) {
                        previewImage.src = payload.processed_preview_image;
                        previewImage.classList.remove('hidden');
                        previewPlaceholder.classList.add('hidden');
                    }

                    const preview = payload.scan_preview || null;
                    const votes = (preview && Array.isArray(preview.detected_votes))
                        ? preview.detected_votes
                        : (payload.detected_votes || []);
                    renderVoteList(votes);

                    if (response.ok && preview && preview.can_submit && Array.isArray(preview.detected_votes) && preview.detected_votes.length > 0) {
                        pendingSubmission = {
                            image_hash: preview.image_hash,
                            detected_votes: preview.detected_votes,
                            election_id: electionSelect.value || null,
                            ballot_number: ballotNumber.value.trim() || null,
                        };

                        confirmPanel.classList.remove('hidden');
                        submitBtn.disabled = false;
                        submitState.textContent = 'Ready to submit. Please confirm.';
                    } else {
                        pendingSubmission = null;
                        confirmPanel.classList.remove('hidden');
                        submitBtn.disabled = true;
                        submitState.textContent = 'Cannot submit. Resolve scan warnings/errors first.';
                    }

                    scanState.textContent = response.ok ? 'Scan complete.' : 'Scan completed with an error response.';
                } catch (error) {
                    resultJson.textContent = JSON.stringify({ success: false, message: error.message }, null, 2);
                    voteList.innerHTML = '<li class="text-red-600">Unable to reach the scanner endpoint.</li>';
                    setDebugOverlay(null);
                    scanState.textContent = 'Scan failed.';
                    confirmPanel.classList.remove('hidden');
                    submitBtn.disabled = true;
                    submitState.textContent = 'Scan failed. No submission payload available.';
                } finally {
                    scanInProgress = false;
                    scanBtn.disabled = false;
                }
            };

            scanBtn.addEventListener('click', async () => {
                await runScan();
            });

            submitBtn.addEventListener('click', async () => {
                if (!pendingSubmission) {
                    submitState.textContent = 'No validated scan data available to submit.';
                    return;
                }

                const proceed = window.confirm('Submit this ballot and save votes to the database?');
                if (!proceed) {
                    return;
                }

                submitBtn.disabled = true;
                submitState.textContent = 'Submitting ballot...';

                try {
                    const response = await fetch(submitUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(pendingSubmission),
                    });

                    const payload = await response.json();
                    resultJson.textContent = JSON.stringify(payload, null, 2);

                    if (response.ok && payload.success) {
                        submitState.textContent = `Saved ballot ${payload.ballot?.id ?? ''} with ${payload.votes_saved ?? 0} vote(s). Final saved votes are shown on the right.`;
                        if (Array.isArray(payload.submitted_votes)) {
                            renderVoteList(payload.submitted_votes);
                        }
                        pendingSubmission = null;
                        fileInput.value = '';
                        capturedBlob = null;
                    } else {
                        submitState.textContent = payload.message || 'Submission failed.';
                        submitBtn.disabled = false;
                    }
                } catch (error) {
                    submitState.textContent = `Submission failed: ${error.message}`;
                    submitBtn.disabled = false;
                }
            });
        })();
    </script>
</x-app-layout>