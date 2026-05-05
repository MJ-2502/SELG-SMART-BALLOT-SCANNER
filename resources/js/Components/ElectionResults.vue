<template>
  <div class="election-results">
    <!-- Election Complete Alert -->
    <div v-if="electionCompleted" class="alert alert-success alert-dismissible fade show">
      <div class="alert-content">
        <h4 class="alert-heading">✓ Election Complete!</h4>
        <p>All {{ expectedBallots }} ballots have been scanned.</p>
        <p class="mb-0">Results have been automatically generated and saved.</p>
      </div>
      <button type="button" class="btn-close" @click="dismissAlert"></button>
    </div>

    <!-- Completion Progress -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Scanning Progress</h5>
        <div class="row">
          <div class="col-md-6">
            <p class="text-muted">Expected Ballots</p>
            <h3>{{ expectedBallots }}</h3>
          </div>
          <div class="col-md-6">
            <p class="text-muted">Scanned Ballots</p>
            <h3 :class="{'text-success': isComplete}">{{ scannedBallots }}</h3>
          </div>
        </div>
        <div class="progress mt-3">
          <div
            class="progress-bar"
            :class="{'bg-success': isComplete}"
            :style="{ width: completionPercentage + '%' }"
            role="progressbar"
          >
            {{ completionPercentage }}%
          </div>
        </div>
      </div>
    </div>

    <!-- Winners Display -->
    <div v-if="winners && winners.length > 0" class="card">
      <div class="card-header bg-dark text-white">
        <h5 class="mb-0">🏆 Election Results - Winners</h5>
      </div>
      <div class="card-body">
        <div v-for="positionWinner in winners" :key="positionWinner.position_id" class="mb-4">
          <h6 class="border-bottom pb-2">
            {{ positionWinner.position_name }}
            <span class="badge badge-info">{{ positionWinner.votes_allowed }} position(s)</span>
          </h6>

          <!-- Tie Warning -->
          <div v-if="positionWinner.has_tie" class="alert alert-warning">
            ⚠️ Tie detected! Multiple candidates have the same vote count
            ({{ positionWinner.tied_vote_count }} votes)
          </div>

          <!-- Winners List -->
          <div class="winners-list">
            <div v-for="(winner, index) in positionWinner.winners" :key="winner.id" class="winner-card mb-3">
              <div class="d-flex align-items-center">
                <!-- Medal Icon -->
                <div class="medal-icon">
                  <span v-if="index === 0" class="badge badge-gold">🥇 1st</span>
                  <span v-else-if="index === 1" class="badge badge-silver">🥈 2nd</span>
                  <span v-else-if="index === 2" class="badge badge-bronze">🥉 3rd</span>
                  <span v-else class="badge badge-secondary">{{ index + 1 }}</span>
                </div>

                <!-- Winner Info -->
                <div class="winner-info ms-3 flex-grow-1">
                  <h6 class="mb-0">{{ winner.name }}</h6>
                  <small class="text-muted">{{ winner.party }}</small>
                </div>

                <!-- Vote Count -->
                <div class="vote-count text-end">
                  <h5 class="mb-0">{{ winner.votes }}</h5>
                  <small class="text-muted">votes</small>
                </div>
              </div>

              <!-- Vote Bar -->
              <div class="progress mt-2" style="height: 5px;">
                <div
                  class="progress-bar"
                  :style="{ 
                    width: calculateVotePercentage(winner.votes, positionWinner.winners) + '%',
                    backgroundColor: winner.color_code || '#007bff'
                  }"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- No Results Yet -->
    <div v-else class="card">
      <div class="card-body text-center text-muted">
        <p>Results will be displayed once all ballots have been scanned.</p>
      </div>
    </div>

    <!-- Actions -->
    <div class="mt-4 d-flex gap-2">
      <button class="btn btn-primary" @click="generateReport" v-if="isComplete && !winners">
        Generate Full Report
      </button>
      <button class="btn btn-secondary" @click="printResults" v-if="winners">
        🖨️ Print Results
      </button>
      <button class="btn btn-outline-secondary" @click="downloadJSON" v-if="winners">
        ⬇️ Download JSON
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  electionId: {
    type: Number,
    required: true
  },
  initialStatus: {
    type: Object,
    default: null
  },
  initialWinners: {
    type: Array,
    default: null
  }
})

const electionStatus = ref(props.initialStatus || null)
const winners = ref(props.initialWinners || null)
const electionCompleted = ref(false)
const showAlert = ref(true)

const expectedBallots = computed(() => electionStatus.value?.expected_ballots || 0)
const scannedBallots = computed(() => electionStatus.value?.scanned_ballots || 0)
const completionPercentage = computed(() => electionStatus.value?.completion_percentage || 0)
const isComplete = computed(() => electionStatus.value?.is_complete || false)

const fetchElectionStatus = async () => {
  try {
    const response = await fetch(`/api/elections/${props.electionId}/status`)
    const data = await response.json()
    electionStatus.value = data.data
    return data.data
  } catch (error) {
    console.error('Error fetching election status:', error)
  }
}

const fetchWinners = async () => {
  try {
    const response = await fetch(`/api/elections/${props.electionId}/winners`)
    if (response.ok) {
      const data = await response.json()
      winners.value = data.data.winners
    }
  } catch (error) {
    console.error('Error fetching winners:', error)
  }
}

const generateReport = async () => {
  try {
    const response = await fetch(`/admin/reports/generate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
      },
      body: JSON.stringify({
        election_id: props.electionId
      })
    })
    if (response.ok) {
      await fetchWinners()
    }
  } catch (error) {
    console.error('Error generating report:', error)
  }
}

const calculateVotePercentage = (votes, allWinners) => {
  const maxVotes = Math.max(...allWinners.map(w => w.votes))
  return maxVotes > 0 ? (votes / maxVotes) * 100 : 0
}

const dismissAlert = () => {
  showAlert.value = false
}

const printResults = () => {
  window.print()
}

const downloadJSON = () => {
  const data = {
    election_id: props.electionId,
    election_status: electionStatus.value,
    winners: winners.value,
    generated_at: new Date().toISOString()
  }
  const json = JSON.stringify(data, null, 2)
  const blob = new Blob([json], { type: 'application/json' })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `election-${props.electionId}-results.json`
  a.click()
}

const pollStatus = () => {
  const interval = setInterval(async () => {
    const status = await fetchElectionStatus()
    if (status?.is_complete && !winners.value) {
      await fetchWinners()
      electionCompleted.value = true
      clearInterval(interval)
    }
  }, 5000) // Poll every 5 seconds
}

onMounted(async () => {
  await fetchElectionStatus()
  if (isComplete.value) {
    await fetchWinners()
  } else {
    pollStatus()
  }
})
</script>

<style scoped>
.election-results {
  padding: 1rem;
}

.alert-success {
  background-color: #d4edda;
  border-color: #c3e6cb;
  color: #155724;
}

.winner-card {
  padding: 1rem;
  border: 1px solid #e9ecef;
  border-radius: 0.5rem;
  transition: all 0.3s ease;
}

.winner-card:hover {
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  transform: translateY(-1px);
}

.medal-icon {
  font-size: 1.5rem;
  min-width: 60px;
}

.badge-gold {
  background-color: #ffc107;
  color: #000;
}

.badge-silver {
  background-color: #c0c0c0;
  color: #000;
}

.badge-bronze {
  background-color: #cd7f32;
  color: #fff;
}

.winners-list {
  margin-top: 1rem;
}

.vote-count {
  min-width: 80px;
}

.progress {
  background-color: #e9ecef;
}

/* Print Styles */
@media print {
  .btn,
  .alert-dismissible .btn-close {
    display: none;
  }

  .card {
    page-break-inside: avoid;
  }

  .winner-card {
    page-break-inside: avoid;
    border: 1px solid #000;
  }
}
</style>
