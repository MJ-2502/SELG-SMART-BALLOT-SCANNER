<x-app-layout>
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
                            <div class="rounded-xl border border-slate-200 p-4 bg-slate-50">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <h3 class="font-semibold text-gray-900">Camera</h3>
                                        <p class="text-sm text-gray-500">Use your device camera or upload a ballot image.</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2 justify-end">
                                        <button type="button" id="startCameraBtn" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Start</button>
                                        <button type="button" id="captureBtn" class="inline-flex items-center rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900">Capture</button>
                                        <button type="button" id="stopCameraBtn" class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-white">Stop</button>
                                    </div>
                                </div>
                                <video id="cameraPreview" class="w-full rounded-lg bg-black aspect-[4/3] object-cover" autoplay playsinline muted></video>
                                <canvas id="captureCanvas" class="hidden"></canvas>
                                <div class="mt-3 text-sm text-gray-500">Capture sends a snapshot to Laravel, which forwards it to the OMR service.</div>
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
                        <div class="rounded-lg bg-slate-950 p-4 text-sm text-slate-100 min-h-64 overflow-auto">
                            <pre id="resultJson" class="whitespace-pre-wrap">No scan yet.</pre>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Detected votes</h4>
                            <ul id="voteList" class="space-y-2 text-sm text-gray-600"></ul>
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
                                <div class="text-sm font-semibold text-gray-900">{{ $position->name }}</div>
                                <div class="text-xs text-indigo-700 mt-1">Vote for up to {{ max(1, (int) ($position->votes_allowed ?? 1)) }} candidate(s)</div>
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
            const cameraPreview = document.getElementById('cameraPreview');
            const captureCanvas = document.getElementById('captureCanvas');
            const electionSelect = document.getElementById('election_id');
            const ballotNumber = document.getElementById('ballot_number');
            const startCameraBtn = document.getElementById('startCameraBtn');
            const captureBtn = document.getElementById('captureBtn');
            const stopCameraBtn = document.getElementById('stopCameraBtn');
            const scanBtn = document.getElementById('scanBtn');

            let cameraStream = null;
            let capturedBlob = null;
            let pendingSubmission = null;

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
            });

            startCameraBtn.addEventListener('click', async () => {
                try {
                    cameraStream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' },
                        audio: false,
                    });

                    cameraPreview.srcObject = cameraStream;
                    scanState.textContent = 'Camera is ready.';
                } catch (error) {
                    scanState.textContent = `Camera error: ${error.message}`;
                }
            });

            captureBtn.addEventListener('click', () => {
                if (!cameraStream) {
                    scanState.textContent = 'Start the camera before capturing.';
                    return;
                }

                captureCanvas.width = cameraPreview.videoWidth || 1280;
                captureCanvas.height = cameraPreview.videoHeight || 960;

                const context = captureCanvas.getContext('2d');
                context.drawImage(cameraPreview, 0, 0, captureCanvas.width, captureCanvas.height);

                captureCanvas.toBlob((blob) => {
                    if (!blob) {
                        scanState.textContent = 'Unable to capture the current frame.';
                        return;
                    }

                    setPreviewFromBlob(blob);
                    scanState.textContent = 'Frame captured. Ready to scan.';
                }, 'image/jpeg', 0.95);
            });

            stopCameraBtn.addEventListener('click', () => {
                if (cameraStream) {
                    cameraStream.getTracks().forEach((track) => track.stop());
                    cameraStream = null;
                }

                cameraPreview.srcObject = null;
                scanState.textContent = 'Camera stopped.';
            });

            scanBtn.addEventListener('click', async () => {
                const file = capturedBlob || fileInput.files?.[0];

                if (!file) {
                    scanState.textContent = 'Choose or capture a ballot image first.';
                    return;
                }

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
                    const response = await fetch(scanUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const payload = await response.json();

                    resultJson.textContent = JSON.stringify(payload, null, 2);
                    voteList.innerHTML = '';

                    const votes = payload.detected_votes || [];
                    if (votes.length === 0) {
                        voteList.innerHTML = '<li class="text-slate-500">No votes detected.</li>';
                    } else {
                        votes.forEach((vote) => {
                            const item = document.createElement('li');
                            item.className = 'rounded-lg border border-slate-200 bg-slate-50 px-3 py-2';
                            item.textContent = `${vote.candidate_name || 'Candidate'} · Position ${vote.position_id} · Confidence ${(vote.confidence * 100).toFixed(1)}%`;
                            voteList.appendChild(item);
                        });
                    }

                    const preview = payload.scan_preview || null;
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
                    scanState.textContent = 'Scan failed.';
                    confirmPanel.classList.remove('hidden');
                    submitBtn.disabled = true;
                    submitState.textContent = 'Scan failed. No submission payload available.';
                } finally {
                    scanBtn.disabled = false;
                }
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
                        submitState.textContent = `Saved ballot ${payload.ballot?.id ?? ''} with ${payload.votes_saved ?? 0} vote(s).`;
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