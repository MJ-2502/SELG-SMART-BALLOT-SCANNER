@php
    $paperSize = strtoupper((string) config('ballot.paper.size', 'A4'));
    $paperWidthMm = $paperSize === 'LETTER' ? 215.9 : 210;
    $paperHeightMm = $paperSize === 'LETTER' ? 279.4 : 297;
@endphp

@extends('layouts.app')

@section('content')
<div class="card">
    <h1>Ballot Layout</h1>
    <p>Set how many ballots should be printable for one election. The system assigns a unique ballot number per election to prevent duplication.</p>

    @if (session('status'))
        <div class="mb-12" style="color:#065f46; background:#ecfdf5; border:1px solid #a7f3d0; padding:10px; border-radius:8px;">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-12" style="color:#991b1b; background:#fef2f2; border:1px solid #fecaca; padding:10px; border-radius:8px;">
            <ul style="margin:0; padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($elections->isEmpty())
        <div class="mb-12" style="color:#92400e; background:#fffbeb; border:1px solid #fde68a; padding:10px; border-radius:8px;">
            No elections found. Please add at least one election record first.
        </div>
    @else
        <form action="{{ route('admin.ballot-layout.generate') }}" method="POST">
            @csrf

            <div class="mb-12">
                <label for="election_id">Election</label><br>
                <select id="election_id" name="election_id" required>
                    <option value="">Select election</option>
                    @foreach ($elections as $election)
                        <option value="{{ $election->id }}" @selected(old('election_id') == $election->id)>
                            {{ $election->label }} (Generated: {{ $election->ballots_count }})
                        </option>
                    @endforeach
                </select>
                @error('election_id') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="mb-12">
                <label for="print_count">Printable ballot count</label><br>
                <input id="print_count" type="number" name="print_count" min="1" max="5000" value="{{ old('print_count', 50) }}" required>
                <p style="margin-top:6px; color:#6b7280; font-size:14px;">
                    Example: entering 200 means this election should have 200 uniquely numbered printable ballots.
                </p>
                @error('print_count') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="mb-12">
                <label for="per_sheet">Ballots per paper</label><br>
                <select id="per_sheet" name="per_sheet">
                    <option value="1" @selected(old('per_sheet', 1) == 1)>1 ballot per sheet</option>
                    <option value="2" @selected(old('per_sheet') == 2)>2 ballots per sheet</option>
                    <option value="4" @selected(old('per_sheet') == 4)>4 ballots per sheet</option>
                </select>
                <p style="margin-top:6px; color:#6b7280; font-size:14px;">
                    Choose 4 to print four ballots on one paper.
                </p>
                @error('per_sheet') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="mb-12">
                <label for="scale_percent">Ballot scale (%)</label><br>
                <input id="scale_percent" type="number" name="scale_percent" min="40" max="100" value="{{ old('scale_percent', 100) }}" required>
                <p style="margin-top:6px; color:#6b7280; font-size:14px;">
                    Lower value means smaller ballots. For 4-up printing, try 55 to 70.
                </p>
                @error('scale_percent') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Generate Ballots and Open Print Layout</button>
            </div>
        </form>

        <div style="margin-top:14px; border:1px solid #e5e7eb; border-radius:8px; padding:10px;">
            <h3 style="margin:0 0 6px; font-size:15px;">Live Print Layout Preview</h3>
            <p style="margin:0 0 10px; color:#6b7280; font-size:13px;">
                Paper: {{ $paperSize }} ({{ $paperWidthMm }}mm × {{ $paperHeightMm }}mm). This preview keeps the same paper ratio as actual print output.
            </p>
            <div id="layout-preview-sheet" style="--preview-scale:1; width:min(420px, 100%); aspect-ratio: {{ $paperWidthMm }} / {{ $paperHeightMm }}; margin:0 auto; padding:8px; display:grid; gap:8px; background:#f9fafb; border:1px solid #d1d5db; border-radius:8px;">
                <div class="preview-slot" data-slot="1" style="display:flex; align-items:center; justify-content:center; border:1px dashed #9ca3af; background:#fff; overflow:hidden; min-height:0;">
                    <div class="preview-mini" style="width:calc(100% * var(--preview-scale)); height:calc(100% * var(--preview-scale)); border:1px solid #111827; background:repeating-linear-gradient(180deg, #fff, #fff 14px, #f3f4f6 14px, #f3f4f6 15px);"></div>
                </div>
                <div class="preview-slot" data-slot="2" style="display:none; align-items:center; justify-content:center; border:1px dashed #9ca3af; background:#fff; overflow:hidden; min-height:0;">
                    <div class="preview-mini" style="width:calc(100% * var(--preview-scale)); height:calc(100% * var(--preview-scale)); border:1px solid #111827; background:repeating-linear-gradient(180deg, #fff, #fff 14px, #f3f4f6 14px, #f3f4f6 15px);"></div>
                </div>
                <div class="preview-slot" data-slot="3" style="display:none; align-items:center; justify-content:center; border:1px dashed #9ca3af; background:#fff; overflow:hidden; min-height:0;">
                    <div class="preview-mini" style="width:calc(100% * var(--preview-scale)); height:calc(100% * var(--preview-scale)); border:1px solid #111827; background:repeating-linear-gradient(180deg, #fff, #fff 14px, #f3f4f6 14px, #f3f4f6 15px);"></div>
                </div>
                <div class="preview-slot" data-slot="4" style="display:none; align-items:center; justify-content:center; border:1px dashed #9ca3af; background:#fff; overflow:hidden; min-height:0;">
                    <div class="preview-mini" style="width:calc(100% * var(--preview-scale)); height:calc(100% * var(--preview-scale)); border:1px solid #111827; background:repeating-linear-gradient(180deg, #fff, #fff 14px, #f3f4f6 14px, #f3f4f6 15px);"></div>
                </div>
            </div>
        </div>
    @endif
</div>

<div class="card" style="margin-top:16px;">
    <h2 style="margin-top:0;">Current Ballot Content Preview</h2>
    <p style="margin-top:0; color:#6b7280;">This preview is based on active candidates grouped by position.</p>

    @forelse ($positions as $position)
        <div class="mb-12" style="border:1px solid #e5e7eb; border-radius:8px; padding:10px;">
            <div style="font-weight:700;">{{ $position->name }}</div>
            <ul style="margin:8px 0 0; padding-left:18px;">
                @forelse ($position->candidates as $candidate)
                    <li>{{ $candidate->name }} @if($candidate->party)({{ $candidate->party }})@endif</li>
                @empty
                    <li style="color:#6b7280;">No active candidates for this position.</li>
                @endforelse
            </ul>
        </div>
    @empty
        <p>No positions found yet.</p>
    @endforelse
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const perSheetInput = document.getElementById('per_sheet');
        const scaleInput = document.getElementById('scale_percent');
        const previewSheet = document.getElementById('layout-preview-sheet');
        const slots = Array.from(document.querySelectorAll('.preview-slot'));

        if (!perSheetInput || !scaleInput || !previewSheet || slots.length === 0) {
            return;
        }

        const applyPreview = () => {
            const perSheet = Number(perSheetInput.value || 1);
            const scalePercent = Number(scaleInput.value || 100);
            const normalizedScale = Math.max(40, Math.min(100, scalePercent)) / 100;

            if (perSheet === 4) {
                previewSheet.style.gridTemplateColumns = '1fr 1fr';
                previewSheet.style.gridTemplateRows = '1fr 1fr';
            } else if (perSheet === 2) {
                previewSheet.style.gridTemplateColumns = '1fr';
                previewSheet.style.gridTemplateRows = '1fr 1fr';
            } else {
                previewSheet.style.gridTemplateColumns = '1fr';
                previewSheet.style.gridTemplateRows = '1fr';
            }

            slots.forEach((slot, index) => {
                slot.style.display = index < perSheet ? 'flex' : 'none';
            });

            previewSheet.style.setProperty('--preview-scale', normalizedScale.toString());
        };

        perSheetInput.addEventListener('change', applyPreview);
        scaleInput.addEventListener('input', applyPreview);
        applyPreview();
    });
</script>
@endsection
