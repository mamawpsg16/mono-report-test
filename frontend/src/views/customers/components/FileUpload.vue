<template>
  <AppModal
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    title="Import Customers"
    max-width="2xl"
  >
    <!-- Toast -->
    <div v-if="showToast" class="toast">
      <div class="toast-bar"></div>
      <div class="toast-body">
        <div class="toast-icon-wrap">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <circle cx="8" cy="8" r="6" stroke="#dc2626" stroke-width="1.4"/>
            <path d="M8 5v4M8 11v.5" stroke="#dc2626" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="toast-content">
          <p class="toast-title">Import Failed</p>
          <p class="toast-detail">{{ toastMessage }}</p>
        </div>
        <button class="toast-close" @click="showToast = false">&times;</button>
      </div>
      <div class="toast-actions">
        <button class="toast-btn-dismiss" @click="showToast = false">Dismiss</button>
      </div>
    </div>

        <!-- IDLE STATE -->
        <div v-if="view === 'idle'" class="state-idle fade-up" >
          <div class="dropzone" @click="$refs.fileInput.click()" @dragover.prevent="dragOver = true" @dragleave="dragOver = false" @drop.prevent="onDrop" :class="{ 'dropzone-hover': dragOver }">
            <div class="drop-icon">
              <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                <path d="M10.5 28a7.5 7.5 0 01-1-14.94A9.5 9.5 0 0129 19a5.5 5.5 0 010 9H10.5z" stroke="#1d4ed8" stroke-width="1.8" fill="none" stroke-linejoin="round"/>
                <path d="M18 26V15M14 19l4-4 4 4" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="drop-text">
              <p class="drop-title">Drop your file here</p>
              <p class="drop-sub">Drag a spreadsheet from your desktop, or <span class="drop-link">click to browse</span></p>
            </div>
            <div class="drop-badges">
              <span class="badge">CSV</span>
              <span class="badge">XLSX</span>
              <span class="badge badge-muted">Max 10 MB</span>
            </div>
          </div>
        </div>

        <input id="file-upload" ref="fileInput" type="file" accept=".csv,.xlsx" class="hidden-input" @change="onFileSelect" aria-label="Upload CSV or XLSX file" />

        <!-- REVIEW STATE -->
        <div v-if="view === 'review'" class="state-review fade-up">
          <div class="file-bar review-bar">
            <div class="file-bar-info">
              <p class="file-label">Selected file</p>
              <p class="file-name">{{ fileName }}</p>
            </div>
            <div class="review-bar-actions">
              <button class="btn-secondary" @click="goIdle()">Cancel</button>
              <button v-if="hasErrors" class="btn-primary" @click="$refs.fileInput.click()">
                Re-upload corrected file
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                  <path d="M3 7h8M8 4l3 3-3 3" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <button v-else class="btn-primary" @click="confirmImport">
                Confirm Import
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                  <path d="M3 7h8M8 4l3 3-3 3" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="review-summary">
            <div class="summary-stat">
              <p class="summary-num">{{ previewData.summary.total_rows ?? 0 }}</p>
              <p class="summary-label">Total rows</p>
            </div>
            <div class="summary-stat summary-stat-new">
              <p class="summary-num">{{ previewData.summary.new_count ?? 0 }}</p>
              <p class="summary-label">New</p>
            </div>
            <div class="summary-stat summary-stat-update">
              <p class="summary-num">{{ previewData.summary.update_count ?? 0 }}</p>
              <p class="summary-label">Updates</p>
            </div>
            <div class="summary-stat summary-stat-error" v-if="hasErrors">
              <p class="summary-num">{{ previewData.errors.length }}</p>
              <p class="summary-label">Errors</p>
            </div>
          </div>

          <div class="review-tabs">
            <button v-if="previewData.rows.length" class="review-tab" :class="{ 'review-tab-active': previewTab === 'rows' }" @click="previewTab = 'rows'">
              Rows ({{ previewData.rows.length }})
            </button>
            <button v-if="hasErrors" class="review-tab review-tab-error" :class="{ 'review-tab-active': previewTab === 'errors' }" @click="previewTab = 'errors'">
              Errors ({{ previewData.errors.length }})
            </button>
          </div>

          <!-- Rows tab -->
          <div v-if="previewTab === 'rows'">
            <div class="row-filters">
              <button class="filter-chip" :class="{ 'filter-chip-active': rowFilter === 'all' }" @click="rowFilter = 'all'">
                All ({{ previewData.rows.length }})
              </button>
              <button v-if="rowStatusCounts.new" class="filter-chip" :class="{ 'filter-chip-active': rowFilter === 'new' }" @click="rowFilter = 'new'">
                New ({{ rowStatusCounts.new }})
              </button>
              <button v-if="rowStatusCounts.update" class="filter-chip" :class="{ 'filter-chip-active': rowFilter === 'update' }" @click="rowFilter = 'update'">
                Update ({{ rowStatusCounts.update }})
              </button>
            </div>
            <DatatableClient :headers="rowsHeaders" :items="rowsItems" empty-message="No rows to display">
              <template #item-_status="row">
                <span class="status-chip" :class="row._status === 'new' ? 'status-new' : 'status-update'">
                  {{ row._status === 'new' ? 'New' : 'Update' }}
                </span>
              </template>
              <template v-for="col in previewColumns" #[`item-${col}`]="row">
                <template v-if="row._status === 'update' && row._changes?.[col]">
                  <span class="change-from" :key="`${col}-from`">{{ row._changes[col].from || '—' }}</span> → <span class="change-to" :key="`${col}-to`">{{ row._changes[col].to || '—' }}</span>
                </template>
                <template v-else>{{ row[col] || '—' }}</template>
              </template>
            </DatatableClient>
          </div>

          <!-- Errors tab -->
          <div v-if="previewTab === 'errors' && hasErrors">
            <DatatableClient :headers="errorsHeaders" :items="errorsItems" empty-message="No errors to display">
              <template #item-row="err"><span class="td-code">{{ err.row }}</span></template>
              <template #item-message="err"><span class="error-message">{{ err.message }}</span></template>
            </DatatableClient>
          </div>

        </div>

        <!-- UPLOADING STATE -->
        <div v-if="view === 'uploading'" class="state-uploading fade-up">
          <div class="upload-file-bar">
            <div class="file-icon file-icon-green">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                <rect x="2" y="1" width="11" height="15" rx="2" fill="#bbf7d0" stroke="#16a34a" stroke-width="1.1"/>
                <path d="M5 7h7M5 10h4" stroke="#16a34a" stroke-width="1.1" stroke-linecap="round"/>
              </svg>
            </div>
            <div class="file-info">
              <p class="file-name">{{ fileName }}</p>
              <p class="file-meta">Uploading and validating rows...</p>
            </div>
            <div class="spinner"></div>
          </div>

          <div class="progress-section">
            <div class="progress-header">
              <p class="progress-label">Processing rows...</p>
              <p class="progress-pct">{{ progressPct }}%</p>
            </div>
            <div class="progress-track">
              <div class="progress-fill" :style="{ width: progressPct + '%' }"></div>
            </div>
            <p class="progress-hint">Please don't close this tab while importing</p>
          </div>

          <button class="cancel-link" @click="cancelUpload">Cancel upload</button>
        </div>

        <!-- SUCCESS STATE -->
        <div v-if="view === 'success'" class="state-success fade-up">
          <div class="success-check">
            <svg width="80" height="80" viewBox="0 0 80 80" fill="none">
              <circle cx="40" cy="40" r="36" fill="#dcfce7" stroke="#16a34a" stroke-width="2.5"/>
              <path d="M25 40l12 12 18-22" stroke="#16a34a" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="52" stroke-dashoffset="52" class="check-path"/>
            </svg>
          </div>
          <div class="success-text">
            <h2>Import Complete!</h2>
            <p>All rows were validated and saved to the database.</p>
          </div>
          <div class="stats-row">
            <div class="stat">
              <p class="stat-num stat-green">{{ successData.rows }}</p>
              <p class="stat-label">Rows saved</p>
            </div>
            <div class="stat-divider"></div>
            <div class="stat">
              <p class="stat-num">0</p>
              <p class="stat-label">Errors</p>
            </div>
            <div class="stat-divider"></div>
            <div class="stat">
              <p class="stat-num">{{ successData.duration }}</p>
              <p class="stat-label">Duration</p>
            </div>
          </div>
          <div class="success-actions">
            <button class="btn-primary" @click="goIdle">Import Another File</button>
          </div>
        </div>

  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import api from '@/helpers/api'
import DatatableClient from '@/components/table/DatatableClient.vue'
import AppModal from '@/components/AppModal.vue'

defineProps({
  modelValue: { type: Boolean, default: false }, // true = modal open
})

const emit = defineEmits(['imported', 'update:modelValue'])

const view = ref('idle')
const file = ref(null)
const fileName = ref('')
const previewColumns = ref([])
const showToast = ref(false)
const toastMessage = ref('')
const progressPct = ref(0)
const successData = ref({ rows: 0, duration: '0s' })
const dragOver = ref(false)
const storedPath = ref(null)

// server-side preview/review data
const previewData = ref({ summary: {}, rows: [], errors: [] })
const previewTab = ref('rows')   // 'rows' | 'errors'
const rowFilter = ref('all')     // 'all' | 'new' | 'update'

let allParsedRows = []
let totalRows = ref(0)
let cancelled = false
let uploadStartTime = null

const COLUMN_LABELS = {
  customer_code: 'Customer Code',
  year: 'Year',
  name: 'Name',
  email: 'Email',
  phone: 'Phone',
  address: 'Address',
  city: 'City',
  country: 'Country',
}

const rowsHeaders = computed(() => [
  { text: 'Status', value: '_status', width: 100 },
  ...previewColumns.value.map((col) => ({ text: COLUMN_LABELS[col] ?? col, value: col })),
])
const rowStatusCounts = computed(() => {
  const counts = { new: 0, update: 0 }
  for (const r of previewData.value.rows) counts[r._status] = (counts[r._status] ?? 0) + 1
  return counts
})
const rowsItems = computed(() => {
  const rows = previewData.value.rows
  return rowFilter.value === 'all' ? rows : rows.filter((r) => r._status === rowFilter.value)
})

const errorsHeaders = [
  { text: 'Row', value: 'row', width: 90 },
  { text: 'Column', value: 'column', width: 160 },
  { text: 'Message', value: 'message' },
]
const errorsItems = computed(() => previewData.value.errors)

const hasErrors = computed(() => previewData.value.errors.length > 0)

function goIdle() {
  view.value = 'idle'
  file.value = null
  fileName.value = ''
  previewColumns.value = []
  allParsedRows = []
  totalRows.value = 0
  progressPct.value = 0
  showToast.value = false
  cancelled = false
  storedPath.value = null
  previewData.value = { summary: {}, rows: [], errors: [] }
  previewTab.value = 'rows'
  rowFilter.value = 'all'
}

function onDrop(event) {
  dragOver.value = false
  const dropped = event.dataTransfer.files[0]
  if (dropped) handleFile(dropped)
}

function onFileSelect(event) {
  const selected = event.target.files[0]
  if (selected) handleFile(selected)
  event.target.value = ''
}

async function handleFile(selected) {
  file.value = selected
  fileName.value = selected.name

  if (selected.name.endsWith('.csv')) {
    await parseCSV(selected)
  } else {
    totalRows.value = 0
    previewColumns.value = []
  }

  // Always upload — the server returns real new/update diff AND validation
  // errors in one response (compute_diff runs over every row regardless of
  // errors), so there's no accurate way to classify rows without it.
  await checkConflicts()
}

async function parseCSV(csvFile) {
  const text = await csvFile.text()
  const lines = text.split('\n').filter((l) => l.trim())
  if (lines.length < 2) return

  const headers = lines[0].split(',').map((h) => h.trim())
  previewColumns.value = headers

  allParsedRows = lines.slice(1).map((line) => {
    const values = line.split(',')
    return Object.fromEntries(headers.map((h, i) => [h, (values[i] || '').trim()]))
  })

  totalRows.value = allParsedRows.length
}

async function checkConflicts() {
  if (!file.value) return

  view.value = 'uploading'
  progressPct.value = 0

  const formData = new FormData()
  formData.append('file', file.value)

  try {
    const { data } = await api.post('/api/customers/preview', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    storedPath.value = data.stored_path
    previewData.value = { summary: data.summary, rows: data.rows, errors: data.errors ?? [] }
    previewTab.value = 'rows'
    view.value = 'review'
  } catch (err) {
    const errData = err.response?.data
    if (errData?.errors?.length || errData?.summary) {
      previewData.value = {
        summary: errData.summary ?? {},
        rows: errData.rows ?? [],
        errors: errData.errors ?? [],
      }
      previewTab.value = 'errors'
      view.value = 'review'
    } else {
      toastMessage.value = errData?.message || 'Upload failed'
      showToast.value = true
      view.value = 'idle'
    }
  }
}

async function confirmImport() {
  if (!storedPath.value) return

  view.value = 'uploading'
  progressPct.value = 0
  cancelled = false
  uploadStartTime = Date.now()

  try {
    const { data } = await api.post('/api/customers/confirm', {
      stored_path: storedPath.value,
      original_filename: fileName.value,
    })

    if (cancelled) return

    const duration = ((Date.now() - uploadStartTime) / 1000).toFixed(1) + 's'

    if (data.status === 'done') {
      progressPct.value = 100
      successData.value = { rows: data.processed_rows, duration }
      view.value = 'success'
      emit('imported')
    } else {
      toastMessage.value = data.errors?.[0]?.message || 'Import failed'
      showToast.value = true
      view.value = 'review'
    }
  } catch (err) {
    if (!cancelled) {
      toastMessage.value = err.response?.data?.message || 'Import failed'
      showToast.value = true
      view.value = 'review'
    }
  }
}

function cancelUpload() {
  cancelled = true
  goIdle()
}
</script>

<style scoped>
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes float { 0%,100% { transform: translateY(0px); } 50% { transform: translateY(-9px); } }
@keyframes checkDraw { to { stroke-dashoffset: 0; } }
@keyframes slideInRight { from { opacity: 0; transform: translateX(110%); } to { opacity: 1; transform: translateX(0); } }
@keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.fade-up { animation: fadeUp 0.3s ease; }

/* Hidden input */
.hidden-input { display: none; }

/* IDLE */
.state-idle { padding: 44px 36px; }
.dropzone {
  border: 2px dashed var(--color-accent-border);
  border-radius: 14px;
  background: var(--color-accent-soft);
  padding: 68px 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 18px;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}
.dropzone-hover { border-color: var(--color-accent); background: #dbeafe; }
.drop-icon {
  width: 74px; height: 74px;
  background: var(--color-accent-soft); border-radius: 22px;
  display: flex; align-items: center; justify-content: center;
  animation: float 3.4s ease-in-out infinite;
  box-shadow: 0 4px 16px rgba(var(--color-accent-rgb),0.15);
}
.drop-text { text-align: center; }
.drop-title { font-size: 17px; font-weight: 600; color: var(--color-ink); margin-bottom: 6px; }
.drop-sub { font-size: 13px; color: var(--color-text-muted); line-height: 1.6; }
.drop-link { color: var(--color-accent); font-weight: 600; cursor: pointer; }
.drop-badges { display: flex; gap: 7px; margin-top: 2px; }
.badge {
  padding: 5px 14px; border-radius: 999px; background: var(--color-surface);
  border: 1px solid var(--color-border); font-size: 12px; font-weight: 500; color: var(--color-text-muted);
}
.badge-muted { font-weight: 400; color: var(--color-text-muted); }

/* FILE BAR (shared: review, error, uploading) */
.file-bar {
  display: flex; align-items: center; gap: 11px;
  padding: 10px 16px; background: var(--color-surface-hover); border-radius: 10px; border: 1px solid var(--color-border);
}
.file-icon {
  width: 36px; height: 36px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.file-icon-green { background: #dcfce7; }
.file-icon-red { background: var(--color-danger-soft); }
.file-info { flex: 1; min-width: 0; }
.file-name { font-size: 13px; font-weight: 600; color: var(--color-ink); }
.file-meta { font-size: 11px; color: var(--color-text-muted); margin-top: 1px; }

/* Review file bar: label + name left, actions right */
.review-bar { justify-content: space-between; padding: 12px 16px; }
.file-bar-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.file-label {
  font-size: 10.5px; font-weight: 700; color: var(--color-text-muted);
  text-transform: uppercase; letter-spacing: 0.6px;
}
.review-bar-actions { display: flex; gap: 10px; flex-shrink: 0; }

.section-label {
  font-size: 10.5px; font-weight: 700; color: var(--color-text-muted);
  text-transform: uppercase; letter-spacing: 0.9px; margin-bottom: 6px;
}

/* Table */
.table-wrap { border-radius: 10px; border: 1px solid var(--color-border); overflow: hidden; }
.table-scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 640px; font-size: 12.5px; }
thead tr { background: var(--color-surface-hover); }
th {
  padding: 8px 12px; text-align: left; font-weight: 600;
  color: var(--color-text); border-bottom: 1px solid var(--color-border); white-space: nowrap;
}
td { padding: 8px 12px; color: var(--color-text-muted); }
.td-code { font-weight: 500; color: var(--color-text); }
tbody tr { border-bottom: 1px solid var(--color-border-subtle); }
tbody tr:last-child { border-bottom: none; }

/* Actions */
.btn-secondary {
  padding: 9px 20px; border-radius: 8px; border: 1px solid var(--color-border);
  background: var(--color-surface); cursor: pointer; font-size: 13px; font-weight: 500;
  color: var(--color-text-muted); font-family: inherit;
}
.btn-primary {
  padding: 9px 22px; border-radius: 8px; border: none; background: var(--color-accent);
  cursor: pointer; font-size: 13px; font-weight: 600; color: #fff;
  font-family: inherit; display: flex; align-items: center; gap: 8px;
}

/* REVIEW */
.state-review { padding: 10px 30px; display: flex; flex-direction: column; gap: 14px; }

.review-summary { display: flex; border-radius: 10px; border: 1px solid var(--color-border); overflow: hidden; }
.summary-stat { flex: 1; padding: 12px 10px; text-align: center; background: var(--color-surface-hover); border-right: 1px solid var(--color-border); }
.summary-stat:last-child { border-right: none; }
.summary-num { font-size: 20px; font-weight: 700; color: var(--color-text); letter-spacing: -0.3px; }
.summary-label { font-size: 10.5px; color: var(--color-text-muted); margin-top: 2px; text-transform: uppercase; letter-spacing: 0.6px; }
.summary-stat-new .summary-num { color: #16a34a; }
.summary-stat-update .summary-num { color: #d97706; }
.summary-stat-error { background: var(--color-danger-soft); }
.summary-stat-error .summary-num { color: var(--color-danger); }

.review-tabs { display: flex; gap: 6px; border-bottom: 1px solid var(--color-border-subtle); }
.review-tab {
  padding: 8px 14px; border: none; background: none; cursor: pointer;
  font-size: 12.5px; font-weight: 600; color: var(--color-text-muted); font-family: inherit;
  border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.review-tab-active { color: var(--color-accent); border-bottom-color: var(--color-accent); }
.review-tab-error.review-tab-active { color: var(--color-danger); border-bottom-color: var(--color-danger); }

.status-chip {
  display: inline-block; padding: 3px 10px; border-radius: 999px;
  font-size: 11px; font-weight: 600;
}
.status-new { background: #dcfce7; color: #16a34a; }
.status-update { background: #fef3c7; color: #d97706; }

.row-filters { display: flex; gap: 8px; margin-bottom: 12px; }
.filter-chip {
  padding: 5px 13px; border-radius: 999px; border: 1px solid var(--color-border);
  background: var(--color-surface); cursor: pointer; font-size: 12px; font-weight: 500;
  color: var(--color-text-muted); font-family: inherit;
}
.filter-chip-active { background: var(--color-accent-soft); border-color: var(--color-accent-border); color: var(--color-accent); font-weight: 600; }

.change-from { text-decoration: line-through; color: var(--color-text-muted); }
.change-to { font-weight: 600; color: #d97706; }

/* UPLOADING */
.state-uploading {
  padding: 60px 36px; display: flex; flex-direction: column;
  align-items: center; gap: 28px;
}
.upload-file-bar {
  display: flex; align-items: center; gap: 12px;
  padding: 13px 20px; background: var(--color-surface-hover); border-radius: 10px;
  border: 1px solid var(--color-border); width: 100%; max-width: 440px;
}
.spinner {
  width: 18px; height: 18px;
  border: 2.5px solid var(--color-border); border-top-color: var(--color-accent);
  border-radius: 50%; animation: spin 0.7s linear infinite; flex-shrink: 0;
}
.progress-section { width: 100%; max-width: 440px; display: flex; flex-direction: column; gap: 9px; }
.progress-header { display: flex; justify-content: space-between; align-items: baseline; }
.progress-label { font-size: 13px; color: var(--color-text); font-weight: 500; }
.progress-pct { font-size: 14px; font-weight: 700; color: var(--color-accent); font-variant-numeric: tabular-nums; }
.progress-track { width: 100%; height: 8px; background: var(--color-border-subtle); border-radius: 999px; overflow: hidden; }
.progress-fill {
  height: 100%; background: var(--color-accent);
  border-radius: 999px; transition: width 0.18s ease;
}
.progress-hint { font-size: 11px; color: var(--color-text-muted); }
.cancel-link {
  border: none; background: none; cursor: pointer;
  font-size: 12px; color: var(--color-text-muted); font-family: inherit;
  padding: 5px 10px; border-radius: 6px;
}

/* SUCCESS */
.state-success {
  padding: 70px 36px; display: flex; flex-direction: column;
  align-items: center; gap: 22px; text-align: center;
}
.check-path { animation: checkDraw 0.55s ease forwards 0.25s; }
.success-text h2 { font-size: 22px; font-weight: 700; color: var(--color-ink); letter-spacing: -0.4px; margin-bottom: 6px; }
.success-text p { font-size: 13px; color: var(--color-text-muted); }
.stats-row {
  display: flex; border-radius: 12px; border: 1px solid var(--color-border);
  overflow: hidden; margin-top: 4px;
}
.stat { padding: 18px 36px; text-align: center; background: var(--color-surface-hover); }
.stat-num { font-size: 28px; font-weight: 700; color: var(--color-text); letter-spacing: -0.5px; }
.stat-green { color: #16a34a; }
.stat-label { font-size: 10.5px; color: var(--color-text-muted); margin-top: 3px; text-transform: uppercase; letter-spacing: 0.6px; }
.stat-divider { width: 1px; background: var(--color-border); }
.success-actions { display: flex; gap: 10px; margin-top: 6px; }

/* TOAST */
.toast {
  position: fixed; top: 66px; right: 20px; z-index: 400; width: 332px;
  background: var(--color-surface); border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.13), 0 0 0 1px rgba(0,0,0,0.05);
  animation: slideInRight 0.38s cubic-bezier(0.16,1,0.3,1); overflow: hidden;
}
.toast-bar { height: 3px; background: var(--color-danger); }
.toast-body { padding: 14px 14px 14px 16px; display: flex; gap: 11px; align-items: flex-start; }
.toast-icon-wrap {
  width: 32px; height: 32px; border-radius: 50%; background: var(--color-danger-soft);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;
}
.toast-content { flex: 1; min-width: 0; }
.toast-title { font-size: 13px; font-weight: 700; color: var(--color-ink); margin-bottom: 3px; }
.toast-detail { font-size: 12px; color: var(--color-text-muted); line-height: 1.5; }
.toast-close {
  border: none; background: none; cursor: pointer; color: var(--color-text-muted);
  font-size: 18px; padding: 0; line-height: 1; font-family: inherit; flex-shrink: 0;
}
.toast-actions { padding: 0 16px 13px; display: flex; gap: 8px; }
.toast-btn-dismiss {
  padding: 5px 12px; border-radius: 6px; border: 1px solid var(--color-border);
  background: var(--color-surface-hover); cursor: pointer; font-size: 11px; font-weight: 500;
  color: var(--color-text-muted); font-family: inherit;
}
</style>
