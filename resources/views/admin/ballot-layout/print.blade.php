@php
    $paperMargin = (int) config('ballot.paper.margin_mm', 10);
    $markerSize = (int) config('ballot.anchor.size_mm', 6);
    $markerOffset = (int) config('ballot.anchor.offset_mm', 6);
    $bubbleSize = (int) config('ballot.bubble.diameter_mm', 5);
    $paperSize = (string) config('ballot.paper.size', 'A4');
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
            --text-color: #111827;
            --muted-color: #6b7280;
            --line-color: #d1d5db;
        }

        @page {
            size: {{ $paperSize }};
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
        }

        .ballot-page {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
            page-break-after: always;
            overflow: hidden;
        }

        .ballot-content {
            padding: calc(var(--paper-margin) + 8mm) var(--paper-margin) var(--paper-margin);
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
            margin-bottom: 10px;
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
        }

        .school-name {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .header-meta {
            margin-top: 4px;
            font-size: 12px;
            color: var(--muted-color);
        }

        .ballot-number {
            margin-top: 8px;
            display: inline-block;
            border: 1px solid #111827;
            padding: 4px 8px;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.05em;
        }

        .instructions {
            margin: 8px 0 12px;
            padding: 8px;
            border: 1px solid var(--line-color);
            border-radius: 6px;
            font-size: 12px;
            background: #f9fafb;
        }

        .position-block {
            border: 1px solid var(--line-color);
            border-radius: 8px;
            margin-bottom: 10px;
            overflow: hidden;
        }

        .position-title {
            margin: 0;
            padding: 8px 10px;
            font-size: 13px;
            font-weight: 700;
            background: #f3f4f6;
            border-bottom: 1px solid var(--line-color);
            text-transform: uppercase;
        }

        .candidate-row {
            display: grid;
            grid-template-columns: var(--bubble-size) 1fr;
            gap: 10px;
            align-items: center;
            padding: 8px 10px;
            border-bottom: 1px dashed #e5e7eb;
            min-height: 10mm;
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
            font-size: 13px;
            font-weight: 600;
        }

        .candidate-party {
            margin-top: 2px;
            font-size: 11px;
            color: var(--muted-color);
        }

        .footer-note {
            margin-top: 8px;
            text-align: right;
            font-size: 10px;
            color: var(--muted-color);
        }

        @media print {
            body { background: #ffffff; }
            .toolbar { display: none; }
            .print-wrapper { padding: 0; gap: 0; }
            .ballot-page { box-shadow: none; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="toolbar-title">{{ $election->label }} · {{ $ballots->count() }} ballot(s)</div>
        <div class="toolbar-actions">
            <a href="{{ route('admin.ballot-layout.index') }}">Back to Ballot Layout</a>
            <button type="button" onclick="window.print()">Print Ballots</button>
        </div>
    </div>

    <div class="print-wrapper">
        @forelse ($ballots as $ballot)
            <section class="ballot-page">
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

                    <div class="footer-note">Election ID {{ $election->id }} · UUID {{ $ballot->uuid }}</div>
                </div>
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
