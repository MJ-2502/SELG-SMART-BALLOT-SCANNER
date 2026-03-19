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
            --content-scale: {{ $contentScale }};
            --text-color: #111827;
            --muted-color: #6b7280;
            --line-color: #d1d5db;
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
            grid-template-columns: 1fr;
            grid-template-rows: repeat(2, 1fr);
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

        .header {
            text-align: center;
            margin-bottom: calc(8px * var(--content-scale));
            border-bottom: 2px solid #111827;
            padding-bottom: calc(6px * var(--content-scale));
        }

        .school-name {
            font-size: clamp(11px, calc(18px * var(--content-scale)), 18px);
            font-weight: 700;
            margin: 0;
        }

        .header-meta {
            margin-top: calc(3px * var(--content-scale));
            font-size: clamp(8px, calc(12px * var(--content-scale)), 12px);
            color: var(--muted-color);
        }

        .ballot-number {
            margin-top: calc(6px * var(--content-scale));
            display: inline-block;
            border: 1px solid #111827;
            padding: calc(3px * var(--content-scale)) calc(7px * var(--content-scale));
            font-weight: 700;
            font-size: clamp(8px, calc(13px * var(--content-scale)), 13px);
            letter-spacing: 0.05em;
        }

        .instructions {
            margin: calc(4px * var(--content-scale)) 0 calc(8px * var(--content-scale));
            padding: calc(6px * var(--content-scale));
            border: 1px solid var(--line-color);
            border-radius: 6px;
            font-size: clamp(8px, calc(12px * var(--content-scale)), 12px);
            background: #f9fafb;
        }

        .positions-grid {
            flex: 1;
            min-height: 0;
            display: grid;
            grid-template-columns: 1fr;
            gap: calc(6px * var(--content-scale));
            align-content: start;
        }

        .sheet.per-4 .positions-grid {
            grid-template-columns: 1fr 1fr;
            gap: calc(5px * var(--content-scale));
        }

        .position-block {
            border: 1px solid var(--line-color);
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }

        .position-title {
            margin: 0;
            padding: calc(6px * var(--content-scale)) calc(8px * var(--content-scale));
            font-size: clamp(8px, calc(13px * var(--content-scale)), 13px);
            font-weight: 700;
            background: #f3f4f6;
            border-bottom: 1px solid var(--line-color);
            text-transform: uppercase;
        }

        .candidate-row {
            display: grid;
            grid-template-columns: var(--bubble-size) 1fr;
            gap: calc(8px * var(--content-scale));
            align-items: center;
            padding: calc(5px * var(--content-scale)) calc(8px * var(--content-scale));
            border-bottom: 1px dashed #e5e7eb;
            min-height: calc(6mm * var(--content-scale));
        }

        .candidate-row:last-child {
            border-bottom: 0;
        }

        .bubble {
            width: var(--bubble-size);
            height: var(--bubble-size);
            border: 1.5px solid #111827;
            border-radius: 999px;
        }

        .candidate-name {
            font-size: clamp(8px, calc(13px * var(--content-scale)), 13px);
            font-weight: 600;
            line-height: 1.2;
        }

        .candidate-party {
            margin-top: calc(1px * var(--content-scale));
            font-size: clamp(7px, calc(11px * var(--content-scale)), 11px);
            color: var(--muted-color);
            line-height: 1.2;
        }

        .footer-note {
            margin-top: calc(6px * var(--content-scale));
            text-align: right;
            font-size: clamp(7px, calc(10px * var(--content-scale)), 10px);
            color: var(--muted-color);
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
<body>
    <div class="toolbar">
        <div class="toolbar-title">{{ $election->label }} · {{ $paperSize }} · {{ $ballots->count() }} ballot(s) · {{ $perSheet }} per sheet · {{ $scalePercent }}%</div>
        <div class="toolbar-actions">
            <a href="{{ route('admin.ballot-layout.index') }}">Back to Ballot Layout</a>
            <button type="button" onclick="window.print()">Print Ballots</button>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.ballot-layout.print') }}" style="padding:10px 16px; display:flex; gap:10px; align-items:end; border-bottom:1px solid #e5e7eb; background:#ffffff;">
        <input type="hidden" name="election" value="{{ $election->id }}">
        <div>
            <label for="per_sheet" style="display:block; font-size:12px; color:#6b7280;">Ballots per sheet</label>
            <select id="per_sheet" name="per_sheet">
                <option value="1" @selected($perSheet === 1)>1</option>
                <option value="2" @selected($perSheet === 2)>2</option>
                <option value="4" @selected($perSheet === 4)>4</option>
            </select>
        </div>
        <div>
            <label for="scale_percent" style="display:block; font-size:12px; color:#6b7280;">Scale %</label>
            <input id="scale_percent" type="number" name="scale_percent" min="40" max="100" value="{{ $scalePercent }}">
        </div>
        <button type="submit" style="border:1px solid #111827; border-radius:6px; padding:8px 12px; background:#111827; color:#fff;">Apply Layout</button>
    </form>

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
                                <header class="header">
                                    <p class="school-name">{{ config('ballot.school_name') }}</p>
                                    <div class="header-meta">{{ $election->election_date?->format('F j, Y g:i A') }}</div>
                                    <div class="ballot-number">Ballot No. {{ str_pad((string) $ballot->ballot_number, 6, '0', STR_PAD_LEFT) }}</div>
                                </header>

                                <div class="instructions">
                                    {{ config('ballot.instructions') }}
                                </div>

                                <div class="positions-grid">
                                    @foreach ($positions as $position)
                                        <section class="position-block">
                                            <h2 class="position-title">{{ $position->name }}</h2>

                                            @forelse ($position->candidates as $candidate)
                                                <div class="candidate-row">
                                                    <span class="bubble"></span>
                                                    <div>
                                                        <div class="candidate-name">{{ $candidate->name }}</div>
                                                        @if ($candidate->party)
                                                            <div class="candidate-party">{{ $candidate->party }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="candidate-row">
                                                    <span class="bubble"></span>
                                                    <div class="candidate-name">No active candidates configured.</div>
                                                </div>
                                            @endforelse
                                        </section>
                                    @endforeach
                                </div>

                                <div class="footer-note">Election ID {{ $election->id }} · UUID {{ $ballot->uuid }}</div>
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
