<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ election: Object, ballots: Array, positions: Array, perSheet: Number, scalePercent: Number });

const paperMargin = 10;
const markerSize = 6;
const markerOffset = 6;
const bubbleSize = 5;
const paperSize = 'A4';
const paperWidthMm = paperSize === 'LETTER' ? 215.9 : 210;
const paperHeightMm = paperSize === 'LETTER' ? 279.4 : 297;
const scale = Math.max(0.4, Math.min(1, (Number(props.scalePercent) || 100) / 100));
const densityBySheet = Number(props.perSheet) === 4 ? 0.72 : Number(props.perSheet) === 2 ? 0.88 : 1;
const contentScale = Math.max(0.55, Math.min(1, scale * densityBySheet));

const positionCount = computed(() => props.positions.length);
const candidateCounts = computed(() => props.positions.map((position) => position.candidates?.length ?? 0));
const maxCandidatesPerPosition = computed(() => Math.max(0, ...candidateCounts.value));
const totalCandidates = computed(() => candidateCounts.value.reduce((sum, count) => sum + count, 0));
const densityMode = computed(() => {
    if (maxCandidatesPerPosition.value >= 14 || totalCandidates.value >= 50 || positionCount.value >= 7) {
        return 'ultra';
    }

    if (maxCandidatesPerPosition.value >= 9 || totalCandidates.value >= 32 || positionCount.value >= 5) {
        return 'dense';
    }

    return 'normal';
});

const denseContentScale = Math.max(0.5, Math.min(1, contentScale * 0.88));
const ultraContentScale = Math.max(0.45, Math.min(1, contentScale * 0.78));

const sheets = computed(() => {
    const perSheet = Math.max(1, Number(props.perSheet) || 2);
    const chunks = [];

    for (let index = 0; index < props.ballots.length; index += perSheet) {
        chunks.push(props.ballots.slice(index, index + perSheet));
    }

    return chunks;
});

const formatBallotNumber = (ballotNumber) => String(ballotNumber ?? '').padStart(4, '0');

const printBallots = () => {
    if (typeof window !== 'undefined') {
        window.print();
    }
};
</script>

<template>
    <Head :title="`Ballot Print - ${election?.label ?? 'Ballot'}`" />
    <div class="print-shell" :class="`density-${densityMode}`">
        <div class="toolbar">
            <div class="toolbar-title">
                {{ election?.label }} · {{ paperSize }} · {{ ballots.length }} ballot(s) · {{ perSheet }} per sheet · {{ scalePercent }}%
            </div>
            <div class="toolbar-actions">
                <Link href="/admin/ballot-generator">Back to Ballot Generator</Link>
                <button type="button" @click="printBallots">Print Ballots</button>
            </div>
        </div>

        <div class="print-wrapper">
            <template v-for="(sheet, sheetIndex) in sheets" :key="sheetIndex">
                <section class="sheet" :class="`per-${perSheet}`">
                    <article v-for="ballot in sheet" :key="ballot.id" class="ballot-page">
                        <div class="ballot-zoom">
                            <div class="ballot-content" :style="{ '--paper-margin': `${paperMargin}mm`, '--marker-size': `${markerSize}mm`, '--marker-offset': `${markerOffset}mm`, '--bubble-size': `${bubbleSize}mm`, '--paper-width-mm': paperWidthMm, '--paper-height-mm': paperHeightMm, '--ballot-scale': scale, '--base-content-scale': contentScale, '--content-scale': contentScale }">
                                <div class="ballot-main-shell">
                                    <div class="marker tl"></div>
                                    <div class="marker tr"></div>
                                    <div class="marker bl"></div>
                                    <div class="marker br"></div>
                                    <div class="official-ballot">
                                        <div class="ballot-main">
                                        <div class="ballot-top-number">Ballot Number: {{ formatBallotNumber(ballot.ballot_number) }}</div>

                                        <div class="ballot-head">
                                            <div class="official-tag">OFFICIAL<br>BALLOT</div>
                                            <div class="election-meta">
                                                <div class="election-title">SELG ELECTION {{ election?.election_year ?? new Date().getFullYear() }}</div>
                                                <div class="election-subtext">{{ $page.props.school_name ?? 'SELG' }}</div>
                                                <div class="election-subtext">{{ election?.election_date_formatted ?? '' }}</div>
                                            </div>
                                        </div>

                                        <div class="instruction-line">General Instructions: Shade the circle beside the name of the candidate of your choice.</div>

                                        <div class="positions-grid">
                                            <section v-for="position in positions" :key="position.id" class="position-block">
                                                <div class="position-head">
                                                    <h2 class="position-title">{{ position.name }}</h2>
                                                    <p class="position-vote-limit">Vote for up to {{ Math.max(1, Number(position.votes_allowed ?? 1)) }} candidate(s)</p>
                                                </div>

                                                <div class="candidate-list">
                                                    <template v-if="position.candidates && position.candidates.length">
                                                        <div v-for="candidate in position.candidates" :key="candidate.id" class="candidate-row">
                                                            <span class="bubble"></span>
                                                            <div class="candidate-name">
                                                                {{ candidate.name }}<span v-if="candidate.party" class="candidate-party">, {{ candidate.party }}</span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <div v-else class="candidate-row">
                                                        <span class="bubble"></span>
                                                        <div class="candidate-name">No active candidates configured.</div>
                                                    </div>
                                                </div>
                                            </section>
                                        </div>

                                      
                                        </div>
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
                </section>
            </template>

            <section v-if="ballots.length === 0" class="ballot-page">
                <div class="ballot-content">
                    <h2>No generated ballots found for this election.</h2>
                    <p>Go back and set a printable ballot count first.</p>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
:global(:root) {
    --paper-margin: 10mm;
    --marker-size: 6mm;
    --marker-offset: 6mm;
    --bubble-size: 5mm;
    --paper-width-mm: 210;
    --paper-height-mm: 297;
    --ballot-scale: 1;
    --base-content-scale: 1;
    --content-scale: 1;
    --text-color: #111827;
    --muted-color: #6b7280;
    --line-color: #d1d5db;
    --font-scale: 1.2;
}

:global(body) {
    margin: 0;
    font-family: Arial, sans-serif;
    color: var(--text-color);
    background: #f3f4f6;
}

:global(body.density-dense) {
    --content-scale: v-bind(denseContentScale);
}

:global(body.density-ultra) {
    --content-scale: v-bind(ultraContentScale);
}

.print-shell {
    min-height: 100vh;
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

.toolbar-actions {
    display: flex;
    gap: 10px;
}

.toolbar-actions :deep(a),
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
    z-index: 2;
}

.marker.tl { top: calc(-1 * var(--marker-offset)); left: calc(-1 * var(--marker-offset)); }
.marker.tr { top: calc(-1 * var(--marker-offset)); right: calc(-1 * var(--marker-offset)); }
.marker.bl { bottom: calc(-1 * var(--marker-offset)); left: calc(-1 * var(--marker-offset)); }
.marker.br { bottom: calc(-1 * var(--marker-offset)); right: calc(-1 * var(--marker-offset)); }

.ballot-main-shell {
    position: relative;
    margin-bottom: calc(4mm * var(--content-scale));
}

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

.position-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: calc(4px * var(--content-scale));
    padding: calc(3px * var(--content-scale)) calc(5px * var(--content-scale));
    background: #efefef;
    border-bottom: 1px solid #111827;
}

.position-title {
    margin: 0;
    padding: 0;
    font-size: clamp(calc(7px * var(--font-scale)), calc(9px * var(--content-scale) * var(--font-scale)), calc(9px * var(--font-scale)));
    font-weight: 700;
    text-transform: uppercase;
}

.position-vote-limit {
    margin: 0;
    padding: 0;
    font-size: clamp(calc(6px * var(--font-scale)), calc(7px * var(--content-scale) * var(--font-scale)), calc(7px * var(--font-scale)));
    font-weight: 600;
    color: #1f2937;
    text-align: right;
    white-space: nowrap;
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
    @page {
        size: A4;
        margin: 0;
    }

    :global(html),
    :global(body) {
        background: #ffffff;
        margin: 0;
        padding: 0;
    }

    .toolbar {
        display: none;
    }

    .print-wrapper {
        padding: 0;
        gap: 0;
    }

    .sheet {
        width: calc(var(--paper-width-mm) * 1mm);
        height: calc(var(--paper-height-mm) * 1mm);
        box-shadow: none;
        margin: 0;
        break-inside: avoid;
    }

    .ballot-page {
        border: 1px solid #d1d5db;
    }
}
</style>