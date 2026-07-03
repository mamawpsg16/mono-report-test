<template>
  <div>
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

    <!-- Page header -->
    <div class="page-header">
      <div class="breadcrumb">
        <span>Home</span>
        <span class="breadcrumb-sep">&rsaquo;</span>
        <span class="breadcrumb-active">Import Customers</span>
      </div>
      <p class="page-subtitle">Batch-import records from a CSV or XLSX spreadsheet</p>
    </div>

    <!-- Card -->
    <div class="card-area">
      <div class="card">

        <!-- IDLE STATE -->
        <div v-if="view === 'idle'" class="state-idle fade-up">
          <div class="dropzone" @click="$refs.fileInput.click()" @dragover.prevent="dragOver = true" @dragleave="dragOver = false" @drop.prevent="onDrop" :class="{ 'dropzone-hover': dragOver }">
            <div class="drop-icon">
              <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                <path d="M10.5 28a7.5 7.5 0 01-1-14.94A9.5 9.5 0 0129 19a5.5 5.5 0 010 9H10.5z" stroke="#4f46e5" stroke-width="1.8" fill="none" stroke-linejoin="round"/>
                <path d="M18 26V15M14 19l4-4 4 4" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
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

        <!-- SELECTED STATE -->
        <div v-if="view === 'selected'" class="state-selected fade-up">
          <!-- File bar -->
          <div class="file-bar">
            <p class="file-name">{{ fileName }}</p>
          </div>

          <!-- Detected columns -->
          <div>
            <p class="section-label">Detected columns</p>
            <div class="column-chips">
              <span v-for="col in previewColumns" :key="col" class="chip" :class="{ 'chip-primary': isRequiredCol(col) }">
                {{ col }}{{ isRequiredCol(col) ? ' ★' : '' }}
              </span>
            </div>
          </div>

          <!-- Preview table -->
          <div>
            <p class="section-label">Preview — first {{ preview.length }} rows</p>
            <div class="table-wrap">
              <div class="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th v-for="col in previewColumns" :key="col">{{ col }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, i) in preview" :key="i">
                      <td v-for="(col, ci) in previewColumns" :key="col" :class="{ 'td-code': ci === 0 }">
                        {{ row[col] || '—' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <p class="table-footer">Showing {{ preview.length }} of {{ totalRows }} rows</p>
          </div>

          <!-- Actions -->
          <div class="actions">
            <button class="btn-secondary" @click="goIdle">Cancel</button>
            <button class="btn-primary" @click="checkConflicts">
              Import {{ totalRows }} rows
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M3 7h8M8 4l3 3-3 3" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- CONFIRM STATE -->
        <div v-if="view === 'confirm'" class="state-confirm fade-up">
          <div class="file-bar">
            <p class="file-name">{{ fileName }}</p>
          </div>

          <div v-if="conflictData.new_rows > 0" class="confirm-section confirm-new">
            <div class="confirm-icon">+</div>
            <div>
              <p class="confirm-label">{{ conflictData.new_rows }} new row{{ conflictData.new_rows !== 1 ? 's' : '' }}</p>
              <p class="confirm-detail">Will be inserted into the database</p>
            </div>
          </div>

          <div v-if="conflictData.update_rows > 0" class="confirm-section confirm-update">
            <div class="confirm-icon confirm-icon-amber">&#x21bb;</div>
            <div class="confirm-update-content">
              <p class="confirm-label">{{ conflictData.update_rows }} existing row{{ conflictData.update_rows !== 1 ? 's' : '' }} will be updated</p>
              <p class="confirm-detail">Matching records found by customer_code + year</p>
              <div v-if="conflictData.updates.length" class="confirm-updates-table">
                <div class="table-wrap">
                  <div class="table-scroll">
                    <table>
                      <thead>
                        <tr>
                          <th>Row</th>
                          <th>Customer Code</th>
                          <th>Year</th>
                          <th>Changes</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="u in conflictData.updates" :key="u.row">
                          <td>{{ u.row }}</td>
                          <td class="td-code">{{ u.customer_code }}</td>
                          <td>{{ u.year }}</td>
                          <td>
                            <template v-if="Object.keys(u.changes).length">
                              <span v-for="(diff, field) in u.changes" :key="field" class="change-chip">
                                {{ field }}: <span class="change-from">{{ diff.from || '—' }}</span> → <span class="change-to">{{ diff.to || '—' }}</span>
                              </span>
                            </template>
                            <span v-else class="change-none">No changes</span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="conflictData.new_rows === 0 && conflictData.update_rows === 0" class="confirm-section confirm-new">
            <div class="confirm-icon">?</div>
            <div>
              <p class="confirm-label">No rows to process</p>
              <p class="confirm-detail">The file appears to be empty</p>
            </div>
          </div>

          <div class="actions">
            <button class="btn-secondary" @click="view = 'selected'">Back</button>
            <button class="btn-primary" @click="confirmImport">
              Confirm Import
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M3 7h8M8 4l3 3-3 3" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
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
            <button class="btn-secondary">View Records</button>
            <button class="btn-primary" @click="goIdle">Import Another File</button>
          </div>
        </div>

        <!-- ERROR STATE -->
        <div v-if="view === 'error'" class="state-error fade-up">
          <!-- Error banner -->
          <div class="error-banner">
            <div class="error-dot">
              <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                <path d="M5 2.5v3.5M5 8v.5" stroke="#dc2626" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </div>
            <div>
              <p class="error-banner-title">Import failed — {{ validationErrors.length }} validation error{{ validationErrors.length !== 1 ? 's' : '' }}</p>
              <p class="error-banner-detail" v-for="(err, i) in validationErrors.slice(0, 3)" :key="i">{{ err }}</p>
              <p class="error-banner-detail" v-if="validationErrors.length > 3">...and {{ validationErrors.length - 3 }} more errors</p>
            </div>
          </div>

          <!-- File bar (error) -->
          <div class="file-bar">
            <div class="file-icon file-icon-red">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                <rect x="2" y="1" width="11" height="15" rx="2" fill="#fee2e2" stroke="#dc2626" stroke-width="1.1"/>
                <path d="M5 7h7M5 10h4" stroke="#dc2626" stroke-width="1.1" stroke-linecap="round"/>
              </svg>
            </div>
            <div class="file-info">
              <p class="file-name">{{ fileName }}</p>
              <p class="file-meta">{{ totalRows }} rows &middot; {{ validationErrors.length }} error{{ validationErrors.length !== 1 ? 's' : '' }}</p>
            </div>
          </div>

          <!-- Preview with error rows -->
          <div>
            <p class="section-label">Preview — errors highlighted</p>
            <div class="table-wrap">
              <div class="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th v-for="col in previewColumns" :key="col">{{ col }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, i) in preview" :key="i" :class="{ 'row-error': row._hasError }">
                      <td v-for="(col, ci) in previewColumns" :key="col" :class="{ 'td-error': row._hasError, 'td-code': ci === 0 }">
                        <template v-if="row._hasError && row._errorCols?.includes(col)">
                          <div class="error-cell">
                            <span class="error-badge">!</span>
                            <span class="error-empty">Empty</span>
                          </div>
                        </template>
                        <template v-else>
                          {{ row[col] || '—' }}
                        </template>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="actions">
            <button class="btn-secondary" @click="goIdle">Cancel</button>
            <button class="btn-primary" @click="$refs.fileInput.click()">
              Re-upload corrected file
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M3 7h8M8 4l3 3-3 3" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '../helpers/api'
import { validateCSV } from '../helpers/csvValidator'

const REQUIRED_COLS = new Set(['customer_code', 'year', 'name'])

const view = ref('idle')
const file = ref(null)
const fileName = ref('')
const preview = ref([])
const previewColumns = ref([])
const validationErrors = ref([])
const showToast = ref(false)
const toastMessage = ref('')
const progressPct = ref(0)
const successData = ref({ rows: 0, duration: '0s' })
const dragOver = ref(false)
const conflictData = ref({ new_rows: 0, update_rows: 0, updates: [] })
const importId = ref(null)

let allParsedRows = []
let totalRows = ref(0)
let pollTimer = null
let cancelled = false
let uploadStartTime = null

function isRequiredCol(col) {
  return REQUIRED_COLS.has(col)
}

function goIdle() {
  stopPolling()
  view.value = 'idle'
  file.value = null
  fileName.value = ''
  preview.value = []
  previewColumns.value = []
  validationErrors.value = []
  allParsedRows = []
  totalRows.value = 0
  progressPct.value = 0
  showToast.value = false
  cancelled = false
  conflictData.value = { new_rows: 0, update_rows: 0, updates: [] }
  importId.value = null
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
  validationErrors.value = []

  if (selected.name.endsWith('.csv')) {
    await parseCSV(selected)
  } else {
    totalRows.value = 0
    previewColumns.value = []
    preview.value = []
  }

  const errors = previewColumns.value.length ? validateCSV(previewColumns.value, allParsedRows) : []

  if (errors.length) {
    validationErrors.value = errors
    markErrorRows(errors)
    view.value = 'error'
  } else {
    view.value = 'selected'
  }
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
  preview.value = allParsedRows.slice(0, 5).map((r) => ({ ...r }))
}

function markErrorRows(errors) {
  preview.value = allParsedRows.slice(0, 5).map((row, i) => {
    const rowNum = i + 2
    const rowErrors = errors.filter((e) => e.startsWith(`Row ${rowNum}:`))
    const errorCols = rowErrors.map((e) => {
      const match = e.match(/'(\w+)'/)
      return match ? match[1] : null
    }).filter(Boolean)

    return {
      ...row,
      _hasError: rowErrors.length > 0,
      _errorCols: errorCols,
    }
  })
}

async function checkConflicts() {
  if (!file.value) return

  view.value = 'uploading'
  progressPct.value = 0

  const formData = new FormData()
  formData.append('file', file.value)

  try {
    const { data } = await api.post('/api/imports/preview', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    importId.value = data.id
    conflictData.value = {
      new_rows: data.new_rows,
      update_rows: data.update_rows,
      updates: data.updates || [],
    }
    view.value = 'confirm'
  } catch (err) {
    toastMessage.value = err.response?.data?.message || 'Upload failed'
    showToast.value = true
    view.value = 'selected'
  }
}

async function confirmImport() {
  if (!importId.value) return

  view.value = 'uploading'
  progressPct.value = 0
  cancelled = false
  uploadStartTime = Date.now()

  try {
    const { data } = await api.post(`/api/imports/${importId.value}/confirm`)

    if (cancelled) return

    const duration = ((Date.now() - uploadStartTime) / 1000).toFixed(1) + 's'

    if (data.status === 'done') {
      progressPct.value = 100
      successData.value = { rows: data.processed_rows, duration }
      view.value = 'success'
    } else if (data.status === 'failed') {
      validationErrors.value = [data.error_message]
      toastMessage.value = data.error_message
      showToast.value = true
      view.value = 'error'
    } else {
      startPolling(data.id)
    }
  } catch (err) {
    if (!cancelled) {
      toastMessage.value = err.response?.data?.message || 'Import failed'
      showToast.value = true
      view.value = 'idle'
    }
  }
}

function startPolling(id) {
  stopPolling()
  pollTimer = setInterval(async () => {
    if (cancelled) {
      stopPolling()
      return
    }
    try {
      const { data } = await api.get(`/api/imports/${id}`)

      if (data.total_rows && data.processed_rows != null) {
        progressPct.value = Math.round((data.processed_rows / data.total_rows) * 100)
      } else if (data.status === 'processing') {
        progressPct.value = Math.min(progressPct.value + 15, 90)
      }

      if (data.status === 'done') {
        progressPct.value = 100
        stopPolling()
        const duration = ((Date.now() - uploadStartTime) / 1000).toFixed(1) + 's'
        successData.value = { rows: data.processed_rows, duration }
        setTimeout(() => { view.value = 'success' }, 400)
      } else if (data.status === 'failed') {
        stopPolling()
        validationErrors.value = [data.error_message]
        toastMessage.value = data.error_message
        showToast.value = true
        view.value = 'error'
      }
    } catch {
      stopPolling()
      toastMessage.value = 'Lost connection while checking import status'
      showToast.value = true
      view.value = 'idle'
    }
  }, 2000)
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

function cancelUpload() {
  cancelled = true
  stopPolling()
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

/* Page header */
.page-header { padding: 14px 36px 10px; }
.breadcrumb { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #9ca3af; margin-bottom: 5px; }
.breadcrumb-sep { font-size: 10px; }
.breadcrumb-active { color: #4f46e5; font-weight: 500; }
.page-title { font-size: 21px; font-weight: 700; color: #1e1b4b; letter-spacing: -0.4px; }
.page-subtitle { font-size: 13px; color: #9ca3af; margin-top: 3px; }

/* Card */
.card-area { padding: 0 36px 20px; }
.card {
  max-width: 860px;
  margin: 0 auto;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05), 0 6px 24px rgba(79,70,229,0.05);
}

/* Hidden input */
.hidden-input { display: none; }

/* IDLE */
.state-idle { padding: 44px 36px; }
.dropzone {
  border: 2px dashed #c7d2fe;
  border-radius: 14px;
  background: linear-gradient(150deg, #fafaff 0%, #eff6ff 100%);
  padding: 68px 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 18px;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}
.dropzone-hover { border-color: #4f46e5; background: linear-gradient(150deg, #eef2ff 0%, #e0e7ff 100%); }
.drop-icon {
  width: 74px; height: 74px;
  background: #eef2ff; border-radius: 22px;
  display: flex; align-items: center; justify-content: center;
  animation: float 3.4s ease-in-out infinite;
  box-shadow: 0 4px 16px rgba(79,70,229,0.15);
}
.drop-text { text-align: center; }
.drop-title { font-size: 17px; font-weight: 600; color: #1e1b4b; margin-bottom: 6px; }
.drop-sub { font-size: 13px; color: #9ca3af; line-height: 1.6; }
.drop-link { color: #4f46e5; font-weight: 600; cursor: pointer; }
.drop-badges { display: flex; gap: 7px; margin-top: 2px; }
.badge {
  padding: 5px 14px; border-radius: 999px; background: #fff;
  border: 1px solid #e5e7eb; font-size: 12px; font-weight: 500; color: #6b7280;
}
.badge-muted { font-weight: 400; color: #9ca3af; }

/* SELECTED */
.state-selected { padding: 20px 30px; display: flex; flex-direction: column; gap: 14px; }
.file-bar {
  display: flex; align-items: center; gap: 11px;
  padding: 10px 16px; background: #f9fafb; border-radius: 10px; border: 1px solid #e5e7eb;
}
.file-icon {
  width: 36px; height: 36px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.file-icon-green { background: #dcfce7; }
.file-icon-red { background: #fef2f2; }
.file-info { flex: 1; min-width: 0; }
.file-name { font-size: 13px; font-weight: 600; color: #1e1b4b; }
.file-meta { font-size: 11px; color: #9ca3af; margin-top: 1px; }
.file-ready {
  padding: 4px 10px; background: #f0fdf4; border-radius: 6px;
  font-size: 11px; font-weight: 600; color: #16a34a; flex-shrink: 0;
}
.file-change {
  border: none; background: none; cursor: pointer;
  font-size: 12px; color: #9ca3af; font-family: inherit;
  padding: 4px 8px; border-radius: 5px; flex-shrink: 0;
}

.section-label {
  font-size: 10.5px; font-weight: 700; color: #9ca3af;
  text-transform: uppercase; letter-spacing: 0.9px; margin-bottom: 6px;
}

.column-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.chip {
  padding: 4px 11px; border-radius: 6px; background: #f9fafb;
  border: 1px solid #e5e7eb; font-size: 11.5px; color: #374151;
}
.chip-primary {
  background: #eef2ff; border-color: #c7d2fe;
  font-weight: 600; color: #4f46e5;
}

/* Table */
.table-wrap { border-radius: 10px; border: 1px solid #e5e7eb; overflow: hidden; }
.table-scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 640px; font-size: 12.5px; }
thead tr { background: #f9fafb; }
th {
  padding: 8px 12px; text-align: left; font-weight: 600;
  color: #374151; border-bottom: 1px solid #e5e7eb; white-space: nowrap;
}
td { padding: 8px 12px; color: #6b7280; }
.td-code { font-weight: 500; color: #374151; }
tbody tr { border-bottom: 1px solid #f3f4f6; }
tbody tr:last-child { border-bottom: none; }
.table-footer { font-size: 11px; color: #9ca3af; margin-top: 7px; }

/* Error rows */
.row-error { box-shadow: inset 3px 0 0 #dc2626; border-bottom-color: #fecaca !important; }
.td-error { background: #fff1f2; }
.error-cell { display: flex; align-items: center; gap: 7px; }
.error-badge {
  width: 15px; height: 15px; border-radius: 50%; background: #dc2626;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 9px; color: #fff; font-weight: 700; flex-shrink: 0;
}
.error-empty { color: #9ca3af; font-style: italic; font-size: 12px; }

/* Actions */
.actions {
  display: flex; align-items: center; justify-content: space-between;
  padding-top: 14px; border-top: 1px solid #f3f4f6;
}
.btn-secondary {
  padding: 9px 20px; border-radius: 8px; border: 1px solid #e5e7eb;
  background: #fff; cursor: pointer; font-size: 13px; font-weight: 500;
  color: #6b7280; font-family: inherit;
}
.btn-primary {
  padding: 9px 22px; border-radius: 8px; border: none; background: #4f46e5;
  cursor: pointer; font-size: 13px; font-weight: 600; color: #fff;
  font-family: inherit; display: flex; align-items: center; gap: 8px;
}
.btn-danger {
  padding: 9px 22px; border-radius: 8px; border: none; background: #dc2626;
  cursor: pointer; font-size: 13px; font-weight: 600; color: #fff;
  font-family: inherit; display: flex; align-items: center; gap: 8px;
}

/* CONFIRM */
.state-confirm { padding: 20px 30px; display: flex; flex-direction: column; gap: 14px; }
.confirm-section {
  display: flex; gap: 12px; align-items: flex-start;
  padding: 14px 16px; border-radius: 10px;
}
.confirm-new { background: #f0fdf4; border: 1px solid #bbf7d0; }
.confirm-update { background: #fffbeb; border: 1px solid #fde68a; }
.confirm-icon {
  width: 28px; height: 28px; border-radius: 50%; background: #dcfce7;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; font-weight: 700; color: #16a34a; flex-shrink: 0;
}
.confirm-icon-amber { background: #fef3c7; color: #d97706; }
.confirm-label { font-size: 13px; font-weight: 600; color: #1e1b4b; }
.confirm-detail { font-size: 12px; color: #6b7280; margin-top: 2px; }
.confirm-update-content { flex: 1; min-width: 0; }
.confirm-updates-table { margin-top: 10px; }
.change-chip {
  display: inline-block; padding: 2px 8px; margin: 2px 4px 2px 0;
  background: #fff; border: 1px solid #fde68a; border-radius: 4px;
  font-size: 11px; color: #92400e;
}
.change-from { text-decoration: line-through; color: #9ca3af; }
.change-to { font-weight: 600; color: #d97706; }
.change-none { font-size: 11px; color: #9ca3af; font-style: italic; }

/* UPLOADING */
.state-uploading {
  padding: 60px 36px; display: flex; flex-direction: column;
  align-items: center; gap: 28px;
}
.upload-file-bar {
  display: flex; align-items: center; gap: 12px;
  padding: 13px 20px; background: #f9fafb; border-radius: 10px;
  border: 1px solid #e5e7eb; width: 100%; max-width: 440px;
}
.spinner {
  width: 18px; height: 18px;
  border: 2.5px solid #e5e7eb; border-top-color: #4f46e5;
  border-radius: 50%; animation: spin 0.7s linear infinite; flex-shrink: 0;
}
.progress-section { width: 100%; max-width: 440px; display: flex; flex-direction: column; gap: 9px; }
.progress-header { display: flex; justify-content: space-between; align-items: baseline; }
.progress-label { font-size: 13px; color: #374151; font-weight: 500; }
.progress-pct { font-size: 14px; font-weight: 700; color: #4f46e5; font-variant-numeric: tabular-nums; }
.progress-track { width: 100%; height: 8px; background: #f3f4f6; border-radius: 999px; overflow: hidden; }
.progress-fill {
  height: 100%; background: linear-gradient(90deg, #818cf8, #4f46e5);
  border-radius: 999px; transition: width 0.18s ease;
}
.progress-hint { font-size: 11px; color: #737985; }
.cancel-link {
  border: none; background: none; cursor: pointer;
  font-size: 12px; color: #9ca3af; font-family: inherit;
  padding: 5px 10px; border-radius: 6px;
}

/* SUCCESS */
.state-success {
  padding: 70px 36px; display: flex; flex-direction: column;
  align-items: center; gap: 22px; text-align: center;
}
.check-path { animation: checkDraw 0.55s ease forwards 0.25s; }
.success-text h2 { font-size: 22px; font-weight: 700; color: #1e1b4b; letter-spacing: -0.4px; margin-bottom: 6px; }
.success-text p { font-size: 13px; color: #9ca3af; }
.stats-row {
  display: flex; border-radius: 12px; border: 1px solid #e5e7eb;
  overflow: hidden; margin-top: 4px;
}
.stat { padding: 18px 36px; text-align: center; background: #f9fafb; }
.stat-num { font-size: 28px; font-weight: 700; color: #374151; letter-spacing: -0.5px; }
.stat-green { color: #16a34a; }
.stat-label { font-size: 10.5px; color: #9ca3af; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.6px; }
.stat-divider { width: 1px; background: #e5e7eb; }
.success-actions { display: flex; gap: 10px; margin-top: 6px; }

/* ERROR */
.state-error { padding: 20px 30px; display: flex; flex-direction: column; gap: 14px; }
.error-banner {
  padding: 11px 16px; background: #fef2f2; border-radius: 10px;
  border: 1px solid #fecaca; display: flex; gap: 11px; align-items: flex-start;
}
.error-dot {
  width: 20px; height: 20px; border-radius: 50%; background: #fee2e2;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; margin-top: 1px;
}
.error-banner-title { font-size: 13px; font-weight: 700; color: #b91c1c; margin-bottom: 3px; }
.error-banner-detail { font-size: 12px; color: #ef4444; line-height: 1.5; }

/* TOAST */
.toast {
  position: fixed; top: 66px; right: 20px; z-index: 400; width: 332px;
  background: #fff; border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.13), 0 0 0 1px rgba(0,0,0,0.05);
  animation: slideInRight 0.38s cubic-bezier(0.16,1,0.3,1); overflow: hidden;
}
.toast-bar { height: 3px; background: linear-gradient(90deg, #dc2626, #f87171); }
.toast-body { padding: 14px 14px 14px 16px; display: flex; gap: 11px; align-items: flex-start; }
.toast-icon-wrap {
  width: 32px; height: 32px; border-radius: 50%; background: #fef2f2;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;
}
.toast-content { flex: 1; min-width: 0; }
.toast-title { font-size: 13px; font-weight: 700; color: #1e1b4b; margin-bottom: 3px; }
.toast-detail { font-size: 12px; color: #6b7280; line-height: 1.5; }
.toast-close {
  border: none; background: none; cursor: pointer; color: #9ca3af;
  font-size: 18px; padding: 0; line-height: 1; font-family: inherit; flex-shrink: 0;
}
.toast-actions { padding: 0 16px 13px; display: flex; gap: 8px; }
.toast-btn-dismiss {
  padding: 5px 12px; border-radius: 6px; border: 1px solid #e5e7eb;
  background: #f9fafb; cursor: pointer; font-size: 11px; font-weight: 500;
  color: #6b7280; font-family: inherit;
}
</style>
