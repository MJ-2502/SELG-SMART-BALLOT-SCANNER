<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    report: Object,
    reportData: Object,
});

const electionLabel = computed(() => props.report?.election_label_snapshot || props.report?.election?.label || props.report?.election?.election_name || 'Election');
const generatedAt = computed(() => props.report?.generated_date_formatted || props.report?.generated_date || '');
const winnersByPosition = computed(() => props.reportData?.winners ?? []);
const hasWinners = computed(() => Array.isArray(winnersByPosition.value) && winnersByPosition.value.length > 0);

const printReport = () => {
    if (typeof window !== 'undefined') {
        window.print();
    }
};

const getPercent = (votes, total) => {
    if (!total || total === 0) return 0;
    return ((votes / total) * 100).toFixed(1);
};
</script>

<template>
    <Head :title="`Report Print - ${electionLabel}`" />

    <div class="print-shell">
        <div class="toolbar">
            <div class="toolbar-title">
                {{ electionLabel }} - Election Report
                <span v-if="generatedAt" class="toolbar-sub">Generated {{ generatedAt }}</span>
            </div>
            <div class="toolbar-actions">
                <Link :href="`/admin/reports/${report.id}`">Back to Report</Link>
                <button type="button" @click="printReport" class="primary-btn">Save PDF</button>
            </div>
        </div>

        <div class="print-page">
            <header class="page-header">
                <div class="header-content">
                    <h1>OFFICIAL ELECTION REPORT</h1>
                    <p class="subtitle">{{ electionLabel }}</p>
                </div>
                <div class="header-meta">
                    <p v-if="generatedAt"><strong>Date Generated:</strong><br>{{ generatedAt }}</p>
                </div>
            </header>

            <section class="section">
                <table class="data-table text-center">
                    <thead>
                        <tr>
                            <th class="text-center w-1/4">Scanned Ballots</th>
                            <th class="text-center w-1/4">Valid Submissions</th>
                            <th class="text-center w-1/4">Flagged Submissions</th>
                            <th class="text-center w-1/4">Voter Turnout</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-2xl font-bold">{{ reportData.summary?.total_scanned ?? 0 }}</td>
                            <td class="text-2xl font-bold">{{ reportData.summary?.valid_submissions ?? 0 }}</td>
                            <td class="text-2xl font-bold">{{ reportData.summary?.flagged_submissions ?? 0 }}</td>
                            <td class="text-2xl font-bold">{{ reportData.summary?.turnout_percent ?? 0 }}%</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section v-if="hasWinners" class="section">
                <h2 class="section-title">Declared Winners</h2>
                <table class="data-table winner-table">
                    <thead>
                        <tr>
                            <th class="col-position">Position</th>
                            <th>Winner Name</th>
                            <th>Party</th>
                            <th class="text-right">Votes Received</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="position in winnersByPosition" :key="position.position_id">
                            <tr v-if="position.has_tie" class="tie-row">
                                <td colspan="4" class="tie-cell">
                                    Tie detected at {{ position.tied_vote_count }} votes. Manual resolution required for {{ position.seats_remaining ?? position.votes_allowed }} seat(s).
                                </td>
                            </tr>
                            <tr v-for="(winner, index) in position.winners" :key="winner.id">
                                <td v-if="index === 0" :rowspan="position.winners.length" class="cell-group-head">{{ position.position_name }}</td>
                                <td class="font-bold">⭐ {{ winner.name }}</td>
                                <td>{{ winner.party || '-' }}</td>
                                <td class="text-right font-bold">{{ winner.votes }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </section>

            <section class="section">
                <h2 class="section-title">Complete Position Tallies</h2>
                <table class="data-table tally-table">
                    <thead>
                        <tr>
                            <th>Candidate Name</th>
                            <th>Party</th>
                            <th class="text-right">Votes</th>
                            <th class="text-right">%</th>
                        </tr>
                    </thead>
                    <tbody v-for="position in reportData.position_tallies ?? []" :key="position.position_id" class="tally-group">
                        <tr class="group-header-row">
                            <td colspan="4">
                                <strong>{{ position.position_name }}</strong> 
                                <span class="muted-text">(Total Votes: {{ position.total_votes }})</span>
                            </td>
                        </tr>
                        <tr v-for="candidate in position.candidates" :key="candidate.id">
                            <td class="pl-indent">{{ candidate.name }}</td>
                            <td>{{ candidate.party || '-' }}</td>
                            <td class="text-right font-bold">{{ candidate.votes }}</td>
                            <td class="text-right muted-text">{{ getPercent(candidate.votes, position.total_votes) }}%</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</template>

<style scoped>
:global(body) {
    margin: 0;
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    color: #111827;
    background: #f3f4f6;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.print-shell {
    min-height: 100vh;
}

.toolbar {
    position: sticky;
    top: 0;
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #ffffff;
    z-index: 20;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.toolbar-title {
    font-weight: 700;
    color: #0f172a;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.toolbar-sub {
    font-size: 0.8rem;
    color: #6b7280;
    font-weight: 500;
}

.toolbar-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.toolbar-actions a,
.toolbar-actions button {
    border: 1px solid #e5e7eb;
    background: #ffffff;
    color: #1f2937;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
}

.toolbar-actions .primary-btn {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.print-page {
    max-width: 900px;
    margin: 20px auto;
    padding: 40px;
    background: white;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #1f2937;
}

.page-header h1 {
    margin: 0 0 4px;
    font-size: 1.6rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #111827;
}

.subtitle {
    font-size: 1.1rem;
    color: #4b5563;
    margin: 0;
}

.header-meta p {
    margin: 0;
    font-size: 0.9rem;
    color: #4b5563;
    text-align: right;
}

.section {
    margin-bottom: 35px;
}

.section-title {
    font-size: 1.2rem;
    margin-bottom: 12px;
    color: #111827;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Table Styles */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}

.data-table th,
.data-table td {
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    text-align: left;
}

.data-table th {
    background-color: #f3f4f6;
    font-weight: 700;
    color: #374151;
    font-size: 0.85rem;
    text-transform: uppercase;
}

.winner-table th {
    background-color: #fef3c7;
    color: #92400e;
    border-color: #fde68a;
}

.winner-table td {
    border-color: #fde68a;
}

.tie-row td,
.tie-cell {
    background-color: #fffbeb;
    color: #92400e;
    font-weight: 700;
    border-color: #fde68a;
}

.cell-group-head {
    background-color: #fffbeb;
    font-weight: 700;
    vertical-align: top;
    width: 25%;
}

.group-header-row td {
    background-color: #f9fafb;
    padding: 8px 14px;
}

.tally-group {
    page-break-inside: avoid;
    break-inside: avoid;
}

/* Utility Classes */
.pl-indent { padding-left: 2rem !important; }
.text-right { text-align: right !important; }
.text-center { text-align: center !important; }
.text-2xl { font-size: 1.5rem; padding: 16px !important; }
.w-1\/4 { width: 25%; }
.font-bold { font-weight: 700; }
.muted-text { color: #6b7280; font-size: 0.85rem; }

@media print {
    @page {
        margin: 1cm;
    }

    :global(body) {
        background: #ffffff;
    }

    .print-shell {
        min-height: auto;
    }

    .toolbar {
        display: none;
    }

    .print-page {
        padding: 0;
        margin: 0;
        max-width: 100%;
        box-shadow: none;
        border-radius: 0;
    }
}
</style>