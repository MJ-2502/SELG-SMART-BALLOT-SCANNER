@php
    $paperMargin = (int) config('ballot.paper.margin_mm', 10);
    $markerSize = (int) config('ballot.anchor.size_mm', 6);
    $markerOffset = (int) config('ballot.anchor.offset_mm', 6);
    $bubbleSize = (int) config('ballot.bubble.diameter_mm', 5);
    $paperSize = strtoupper((string) config('ballot.paper.size', 'A4'));
    $paperWidthMm = $paperSize === 'LETTER' ? 215.9 : 210;
    $paperHeightMm = $paperSize === 'LETTER' ? 279.4 : 297;
    $scale = max(0.4, min(1, ((int) $scalePercent) / 100));
    $densityBySheet = match ((int) $perSheet) {
        4 => 0.72,
        2 => 0.88,
        default => 1,
    };
    $contentScale = max(0.55, min(1, $scale * $densityBySheet));
    $positionCount = $positions->count();
    $candidateCounts = $positions->map(fn ($position) => $position->candidates->count());
    $maxCandidatesPerPosition = (int) ($candidateCounts->max() ?? 0);
    $totalCandidates = (int) $candidateCounts->sum();
    $densityMode = match (true) {
        $maxCandidatesPerPosition >= 14 || $totalCandidates >= 50 || $positionCount >= 7 => 'ultra',
        $maxCandidatesPerPosition >= 9 || $totalCandidates >= 32 || $positionCount >= 5 => 'dense',
        default => 'normal',
    };
    $denseContentScale = max(0.5, min(1, $contentScale * 0.88));
    $ultraContentScale = max(0.45, min(1, $contentScale * 0.78));
    $sheets = $ballots->chunk((int) $perSheet);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ballot Print - {{ $election->label }}</title>
    <style>
        :root {
            --paper-margin: {{ $paperMargin }}mm;
            --marker-size: {{ $markerSize }}mm;
            --marker-offset: {{ $markerOffset }}mm;
            --bubble-size: {{ $bubbleSize }}mm;
            --paper-width-mm: {{ $paperWidthMm }};
            --paper-height-mm: {{ $paperHeightMm }};
            --ballot-scale: {{ $scale }};
            --base-content-scale: {{ $contentScale }};
            --content-scale: {{ $contentScale }};
            --text-color: #111827;
            --muted-color: #6b7280;
            --line-color: #d1d5db;
            --font-scale: 1.2;
        }

        @page {
            size: calc(var(--paper-width-mm) * 1mm) calc(var(--paper-height-mm) * 1mm);
            margin: 0;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: var(--text-color);
            background: #f3f4f6;
        }

        body.density-dense {
            --content-scale: {{ $denseContentScale }};
        }

        body.density-ultra {
            --content-scale: {{ $ultraContentScale }};
        }

        .toolbar {
            position: sticky;
            top: 0;
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
            z-index: 20;
        }

        .toolbar-title {
            font-weight: 700;
        }

        .toolbar-actions a,
        .toolbar-actions button {
            border: 1px solid #111827;
            border-radius: 6px;
            padding: 8px 12px;
            text-decoration: none;
            background: #ffffff;
            color: #111827;
            cursor: pointer;
            font-size: 14px;
        }

        .toolbar-actions button {
            background: #111827;
            color: #ffffff;
        }

        .print-wrapper {
            display: grid;
            gap: 12px;
            padding: 12px;
            justify-content: center;
        }

        .sheet {
            width: min(calc(var(--paper-width-mm) * 1mm), calc(100vw - 24px));
            aspect-ratio: var(--paper-width-mm) / var(--paper-height-mm);
            padding: 4mm;
            display: grid;
            gap: 4mm;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
            page-break-after: always;
        }

        .sheet.per-1 {
            grid-template-columns: 1fr;
            grid-template-rows: 1fr;
        }

        .sheet.per-2 {
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: 1fr;
        }

        .sheet.per-4 {
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(2, 1fr);
        }

        .ballot-page {
            position: relative;
            background: #fff;
            border: 1px solid #d1d5db;
            overflow: hidden;
            min-height: 0;
        }

        .ballot-zoom {
            transform: scale(var(--ballot-scale));
            transform-origin: top left;
            width: calc(100% / var(--ballot-scale));
            height: calc(100% / var(--ballot-scale));
        }

        .ballot-content {
            padding: calc(var(--paper-margin) + 8mm) var(--paper-margin) var(--paper-margin);
            height: 100%;
            display: flex;
            flex-direction: column;
            gap: calc(6px * var(--content-scale));
        }

        .marker {
            position: absolute;
            width: var(--marker-size);
            height: var(--marker-size);
            background: #000000;
        }

        .marker.tl { top: var(--marker-offset); left: var(--marker-offset); }
        .marker.tr { top: var(--marker-offset); right: var(--marker-offset); }
        .marker.bl { bottom: var(--marker-offset); left: var(--marker-offset); }
        .marker.br { bottom: var(--marker-offset); right: var(--marker-offset); }

        .official-ballot {
            border: 1.5px solid #111827;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            font-family: Arial, sans-serif;
            overflow: hidden;
            min-height: 0;
        }

        .ballot-main {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            border-bottom: 1.5px solid #111827;
        }

        .ballot-top-number {
            border-bottom: 1px solid #111827;
            text-align: center;
            font-weight: 700;
            padding: calc(3px * var(--content-scale)) calc(5px * var(--content-scale));
            font-size: clamp(calc(8px * var(--font-scale)), calc(11px * var(--content-scale) * var(--font-scale)), calc(11px * var(--font-scale)));
        }

        .ballot-head {
            display: grid;
            grid-template-columns: minmax(16mm, 22mm) 1fr;
            border-bottom: 1px solid #111827;
            min-height: calc(18mm * var(--content-scale));
        }

        .official-tag {
            border-right: 1px solid #111827;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: clamp(calc(7px * var(--font-scale)), calc(9px * var(--content-scale) * var(--font-scale)), calc(9px * var(--font-scale)));
            font-weight: 700;
            line-height: 1.2;
            padding: calc(4px * var(--content-scale));
        }

        .election-meta {
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: calc(4px * var(--content-scale));
            gap: calc(1px * var(--content-scale));
        }

        .election-title {
            font-size: clamp(calc(8px * var(--font-scale)), calc(11px * var(--content-scale) * var(--font-scale)), calc(11px * var(--font-scale)));
            font-weight: 700;
            line-height: 1.2;
        }

        .election-subtext {
            font-size: clamp(calc(7px * var(--font-scale)), calc(9px * var(--content-scale) * var(--font-scale)), calc(9px * var(--font-scale)));
            line-height: 1.2;
        }

        .instruction-line {
            border-bottom: 1px solid #111827;
            color: #b91c1c;
            font-size: clamp(calc(7px * var(--font-scale)), calc(9px * var(--content-scale) * var(--font-scale)), calc(9px * var(--font-scale)));
            font-weight: 700;
            padding: calc(3px * var(--content-scale)) calc(5px * var(--content-scale));
            text-align: center;
        }

        .positions-grid {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .position-block {
            border-bottom: 1px solid #111827;
            background: #fff;
        }

        .position-block:last-child {
            border-bottom: 0;
        }

        .position-title {
            margin: 0;
            padding: calc(3px * var(--content-scale)) calc(5px * var(--content-scale));
            font-size: clamp(calc(7px * var(--font-scale)), calc(9px * var(--content-scale) * var(--font-scale)), calc(9px * var(--font-scale)));
            font-weight: 700;
            background: #efefef;
            border-bottom: 1px solid #111827;
            text-transform: uppercase;
        }

        .position-vote-limit {
            margin: 0;
            padding: calc(2px * var(--content-scale)) calc(5px * var(--content-scale));
            font-size: clamp(calc(6px * var(--font-scale)), calc(7px * var(--content-scale) * var(--font-scale)), calc(7px * var(--font-scale)));
            font-weight: 600;
            color: #1f2937;
            border-bottom: 1px dashed #9ca3af;
            background: #f9fafb;
        }

        .candidate-list {
            padding: calc(2px * var(--content-scale)) calc(5px * var(--content-scale));
        }

        .candidate-row {
            display: grid;
            grid-template-columns: calc(4mm * var(--content-scale)) 1fr;
            gap: calc(3px * var(--content-scale));
            align-items: start;
            padding: calc(1px * var(--content-scale)) 0;
            min-height: calc(4mm * var(--content-scale));
        }

        .bubble {
            width: calc(3.5mm * var(--content-scale));
            height: calc(3.5mm * var(--content-scale));
            border: 1px solid #111827;
            border-radius: 999px;
            margin-top: calc(0.4mm * var(--content-scale));
        }

        .candidate-name {
            font-size: clamp(calc(7px * var(--font-scale)), calc(8.5px * var(--content-scale) * var(--font-scale)), calc(8.5px * var(--font-scale)));
            font-weight: 700;
            line-height: 1.2;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: calc(0.6px * var(--content-scale));
        }

        .candidate-party {
            display: inline;
            font-size: clamp(calc(6px * var(--font-scale)), calc(7.5px * var(--content-scale) * var(--font-scale)), calc(7.5px * var(--font-scale)));
            font-weight: 600;
            color: #374151;
            margin-left: 2px;
        }

        .tear-line {
            text-align: center;
            font-size: clamp(calc(6px * var(--font-scale)), calc(7px * var(--content-scale) * var(--font-scale)), calc(7px * var(--font-scale)));
            color: #9ca3af;
            padding: calc(2px * var(--content-scale));
            border-bottom: 2px dashed #9ca3af;
            letter-spacing: 1px;
        }

        .voter-box {
            padding: calc(4px * var(--content-scale));
            background: #ffffff;
        }

        .voter-grid {
            border: 1.5px solid #111827;
            display: grid;
            grid-template-columns: 1fr minmax(20mm, 28mm);
            grid-template-rows: auto auto minmax(10mm, 16mm);
            background: #ffffff;
        }

        .voter-cell {
            border-right: 1px solid #111827;
            border-bottom: 1px solid #111827;
            padding: calc(2px * var(--content-scale)) calc(4px * var(--content-scale));
            font-size: clamp(calc(6px * var(--font-scale)), calc(8px * var(--content-scale) * var(--font-scale)), calc(8px * var(--font-scale)));
            font-weight: 700;
        }

        .voter-cell:last-child,
        .voter-cell.no-right {
            border-right: 0;
        }

        .voter-cell.no-bottom {
            border-bottom: 0;
        }

        .voter-value {
            display: block;
            margin-top: calc(4px * var(--content-scale));
            border-bottom: 1px solid #9ca3af;
            min-height: calc(3mm * var(--content-scale));
        }

        .thumbmark-box {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(calc(6px * var(--font-scale)), calc(8px * var(--content-scale) * var(--font-scale)), calc(8px * var(--font-scale)));
            font-weight: 700;
        }

        .footer-note {
            margin-top: calc(2px * var(--content-scale));
            text-align: right;
            font-size: clamp(calc(6px * var(--font-scale)), calc(7px * var(--content-scale) * var(--font-scale)), calc(7px * var(--font-scale)));
            color: #6b7280;
            padding: 0 calc(2px * var(--content-scale));
        }

        @media print {
            body { background: #ffffff; }
            .toolbar { display: none; }
            .print-wrapper { padding: 0; gap: 0; }
            .sheet {
                width: calc(var(--paper-width-mm) * 1mm);
                height: calc(var(--paper-height-mm) * 1mm);
                box-shadow: none;
                margin: 0;
                break-inside: avoid;
            }
            .ballot-page { border: 1px solid #d1d5db; }
        }
    </style>
</head>
<body class="density-{{ $densityMode }}">
    <div class="toolbar">
        <div class="toolbar-title">{{ $election->label }} · {{ $paperSize }} · {{ $ballots->count() }} ballot(s) · {{ $perSheet }} per sheet · {{ $scalePercent }}%</div>
        <div class="toolbar-actions">
            <a href="{{ route('admin.ballot-layout.index') }}">Back to Ballot Layout</a>
            <button type="button" onclick="window.print()">Print Ballots</button>
        </div>
    </div>



    <div class="print-wrapper">
        @forelse ($sheets as $sheet)
            <section class="sheet per-{{ $perSheet }}">
                @foreach ($sheet as $ballot)
                    <article class="ballot-page">
                        <div class="ballot-zoom">
                            <div class="marker tl"></div>
                            <div class="marker tr"></div>
                            <div class="marker bl"></div>
                            <div class="marker br"></div>

                            <div class="ballot-content">
                                <div class="official-ballot">
                                    <div class="ballot-main">
                                        <div class="ballot-top-number">Ballot Number: {{ str_pad((string) $ballot->ballot_number, 4, '0', STR_PAD_LEFT) }}</div>

                                        <div class="ballot-head">
                                            <div class="official-tag">OFFICIAL<br>BALLOT</div>
                                            <div class="election-meta">
                                                <div class="election-title">SELG ELECTION {{ $election->election_date?->format('Y') ?? now()->format('Y') }}</div>
                                                <div class="election-subtext">{{ config('ballot.school_name') }}</div>
                                                <div class="election-subtext">{{ $election->election_date?->format('F j, Y') }}</div>
                                            </div>
                                        </div>

                                        <div class="instruction-line">General Instructions: Shade the circle beside the name of the candidate of your choice.</div>

                                        <div class="positions-grid">
                                            @foreach ($positions as $position)
                                                <section class="position-block">
                                                    <h2 class="position-title">{{ $position->name }}</h2>
                                                    <p class="position-vote-limit">Vote for up to {{ max(1, (int) ($position->votes_allowed ?? 1)) }} candidate(s)</p>

                                                    <div class="candidate-list">
                                                        @forelse ($position->candidates as $candidate)
                                                            <div class="candidate-row">
                                                                <span class="bubble"></span>
                                                                <div class="candidate-name">
                                                                    {{ $candidate->name }}@if ($candidate->party)<span class="candidate-party">, {{ $candidate->party }}</span>@endif
                                                                </div>
                                                            </div>
                                                        @empty
                                                            <div class="candidate-row">
                                                                <span class="bubble"></span>
                                                                <div class="candidate-name">No active candidates configured.</div>
                                                            </div>
                                                        @endforelse
                                                    </div>
                                                </section>
                                            @endforeach
                                        </div>
                                        <div class="footer-note">Election ID {{ $election->id }} · UUID {{ $ballot->uuid }}</div>
                                    </div>
                                </div>
                                <div class="tear-line">✂ ✂ ✂ CUT HERE ✂ ✂ ✂</div>

                                <div class="voter-box">
                                    <div class="voter-grid">
                                        <div class="voter-cell">Name:<span class="voter-value"></span></div>
                                        <div class="voter-cell no-right">&nbsp;</div>
                                        <div class="voter-cell">Grade &amp; Section:<span class="voter-value"></span></div>
                                        <div class="voter-cell no-right">Thumbmark:</div>
                                        <div class="voter-cell no-bottom">Signature:<span class="voter-value"></span></div>
                                        <div class="voter-cell no-right no-bottom thumbmark-box">&nbsp;</div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>
        @empty
            <section class="ballot-page">
                <div class="ballot-content">
                    <h2>No generated ballots found for this election.</h2>
                    <p>Go back and set a printable ballot count first.</p>
                </div>
            </section>
        @endforelse
    </div>
</body>
</html>
