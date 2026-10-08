<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">💰 Gestió de Nòmines</h1>
        <p class="page-subtitle">Pujar PDF conjunt de nòmines i assignar automàticament per DNI</p>
      </div>
      <div class="page-actions" style="display:flex;gap:8px;">
        <button class="btn btn-primary" @click="showBulkModal = true">📄 Pujar PDF Conjunt</button>
        <button class="btn btn-outline" @click="showUploadModal = true">+ Pujar Individual</button>
        <button class="btn btn-outline" @click="showImportAntic = true">🗄️ Portal antic</button>
      </div>
    </div>

    <div class="stats-grid mb-lg">
      <div class="stat-card primary">
        <div class="stat-label">Total Nòmines Pujades</div>
        <div class="stat-value">{{ payrolls.length }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Treballadors amb nòmina</div>
        <div class="stat-value">{{ uniqueWorkers }}</div>
      </div>
      <div class="stat-card" style="border-left:3px solid var(--color-warning);">
        <div class="stat-label">Política retenció</div>
        <div class="stat-value" style="font-size:1.2rem;">48 mesos</div>
      </div>
    </div>

    <!-- Filtres -->
    <div style="display:flex;gap:16px;margin-bottom:20px;align-items:center;flex-wrap:wrap;">
      <SelectorPersona v-model="filterUser" :persones="workers" :buida="{ valor: 'all', text: 'Tots els treballadors' }" :detall="w => w.dni || w.email" style="width:250px;" />
      <select class="form-select" v-model="filterYear" style="width:120px;">
        <option value="">Tot l'any</option>
        <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
      </select>
      <select class="form-select" v-model="filterMonth" style="width:150px;">
        <option value="">Tots els mesos</option>
        <option value="1">Gener</option>
        <option value="2">Febrer</option>
        <option value="3">Març</option>
        <option value="4">Abril</option>
        <option value="5">Maig</option>
        <option value="6">Juny</option>
        <option value="7">Juliol</option>
        <option value="8">Agost</option>
        <option value="9">Setembre</option>
        <option value="10">Octubre</option>
        <option value="11">Novembre</option>
        <option value="12">Desembre</option>
      </select>
      <select class="form-select" v-model="sortOrder" style="width:200px;">
        <option value="desc">Més recents primer</option>
        <option value="asc">Més antigues primer</option>
      </select>
      <button class="btn btn-outline" v-if="filterMonth || filterYear || filterUser !== 'all' || sortOrder !== 'desc'" @click="filterUser='all'; filterMonth=''; filterYear=''; sortOrder='desc'">Netejar</button>
      <div style="flex:1;"></div>
      <!-- Batch Actions -->
      <div v-if="selectedPayrolls.length" style="display:flex;gap:8px;align-items:center;background:rgba(59,130,246,0.06);padding:6px 14px;border-radius:10px;">
        <span style="font-size:0.82rem;font-weight:600;">{{ selectedPayrolls.length }} seleccionades</span>
        <button class="btn btn-outline btn-sm" @click="printSelected" title="Imprimir seleccionades">🖨️ Imprimir</button>
        <button class="btn btn-outline btn-sm" @click="emailSelected" title="Enviar per email">📧 Email</button>
        <button class="btn btn-outline btn-sm" @click="chatSendSelected" title="Enviar per missatgeria interna">💬 Xat</button>
      </div>
    </div>

    <!-- Llista -->
    <div class="card">
      <div v-if="filteredPayrolls.length === 0" class="empty-state">
        <p>No hi ha nòmines per a aquesta selecció</p>
      </div>
      <div class="table-container" v-else>
        <table>
          <thead>
            <tr>
              <th style="width:36px;"><input type="checkbox" @change="toggleSelectAll($event.target.checked)" :checked="allSelected" style="width:16px;height:16px;" /></th>
              <th>Treballador</th>
              <th>DNI</th>
              <th>Mes / Període</th>
              <th>Document</th>
              <th>Pujat el</th>
              <th>Caduca</th>
              <th>Estat Firma</th>
              <th>Accions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in filteredPayrolls" :key="p.id" :style="selectedPayrolls.includes(p.id) ? 'background:rgba(59,130,246,0.04);' : ''">
              <td><input type="checkbox" :checked="selectedPayrolls.includes(p.id)" @change="toggleSelect(p.id)" style="width:16px;height:16px;" /></td>
              <td style="font-weight:500;">{{ getWorkerName(p.user_id) }}</td>
              <td class="text-small" style="font-family:monospace;">{{ getWorkerDni(p.user_id) }}</td>
              <td><span class="badge badge-info">{{ String(p.month).padStart(2, '0') }} / {{ p.year }}</span></td>
              <td>📄 {{ p.file_name }}</td>
              <td class="text-small text-muted">{{ formatDate(p.created_at) }}</td>
              <td class="text-small text-muted">{{ p.expires_at ? formatDate(p.expires_at) : '—' }}</td>
              <td>
                <span v-if="p.signed_at" class="badge badge-success" style="font-size:0.65rem;">✓ Firmat {{ formatDate(p.signed_at) }}</span>
                <span v-else-if="p.viewed_at" class="badge badge-warning" style="font-size:0.65rem;">👁️ Vist {{ formatDate(p.viewed_at) }}</span>
                <span v-else class="badge badge-danger" style="font-size:0.65rem;">Pendent</span>
                <div v-if="p.signature_hash" class="text-small mt-xs text-muted" style="font-size:0.6rem;font-family:monospace;">{{ p.signature_hash.substring(0, 16) }}...</div>
              </td>
              <td style="display:flex;gap:6px;">
                <button class="btn btn-outline btn-sm" @click="openPreview(p)">👁️</button>
                <button class="btn btn-outline btn-sm" @click="printPayroll(p)" title="Imprimir">🖨️</button>
                <button class="btn btn-outline btn-sm" @click="emailPayroll(p)" title="Enviar per email">📧</button>
                <button class="btn btn-outline btn-sm" @click="chatSendPayroll(p)" title="Enviar per xat">💬</button>
                <button class="btn btn-danger btn-sm" @click="remove(p.id)" title="Eliminar">✕</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ BULK UPLOAD MODAL ═══ -->
    <div v-if="showBulkModal" class="modal-overlay" @click.self="closeBulkModal">
      <div class="modal" style="max-width:750px;max-height:90vh;display:flex;flex-direction:column;">
        <div class="modal-header" style="background:linear-gradient(135deg,rgba(59,130,246,0.08),rgba(147,51,234,0.06));border-radius:12px 12px 0 0;">
          <div>
            <h3 class="modal-title">📄 Pujada Massiva de Nòmines per DNI</h3>
            <div class="text-small text-muted">El sistema extreu automàticament el DNI/NIE de cada pàgina i l'assigna al treballador corresponent</div>
          </div>
          <button class="icon-btn" @click="closeBulkModal">✕</button>
        </div>

        <!-- Step 1: Upload -->
        <div v-if="bulkStep === 'upload'" style="padding:20px;flex:1;overflow-y:auto;">
          <div class="form-group">
            <label class="form-label">Període (Mes i Any)</label>
            <div style="display:flex; gap:8px;">
              <select class="form-select" v-model="bulkMonth" style="width:150px;">
                <option value="" disabled>Mes</option>
                <option value="01">Gener</option>
                <option value="02">Febrer</option>
                <option value="03">Març</option>
                <option value="04">Abril</option>
                <option value="05">Maig</option>
                <option value="06">Juny</option>
                <option value="07">Juliol</option>
                <option value="08">Agost</option>
                <option value="09">Setembre</option>
                <option value="10">Octubre</option>
                <option value="11">Novembre</option>
                <option value="12">Desembre</option>
              </select>
              <select class="form-select" v-model="bulkYear" style="width:120px;">
                <option value="" disabled>Any</option>
                <option v-for="y in selectableYears" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">PDF Conjunt (totes les nòmines en un sol fitxer)</label>
            <div v-if="!bulkFile" 
              style="border:2px dashed var(--color-border-light);border-radius:16px;padding:40px;text-align:center;cursor:pointer;transition:all 0.3s;background:linear-gradient(135deg,rgba(59,130,246,0.02),rgba(147,51,234,0.02));"
              @click="$refs.bulkPdfInput.click()"
              @dragover.prevent
              @drop.prevent="onBulkDrop">
              <div style="font-size:3rem;margin-bottom:12px;">📎</div>
              <div style="font-weight:700;font-size:1rem;margin-bottom:6px;">Arrossegueu el PDF conjunt aquí</div>
              <div class="text-small text-muted">o feu clic per seleccionar. Mida màxima recomanada: 20MB</div>
              <div class="text-small text-muted" style="margin-top:8px;">Format esperat: <strong>1 pàgina = 1 nòmina</strong> amb DNI/NIE visible al text</div>
            </div>
            <div v-else style="display:flex;align-items:center;gap:14px;padding:16px;border:1px solid var(--color-border-light);border-radius:12px;background:rgba(59,130,246,0.03);">
              <div style="font-size:2.5rem;">📄</div>
              <div style="flex:1;">
                <div style="font-weight:600;font-size:0.95rem;">{{ bulkFile.name }}</div>
                <div class="text-small text-muted">{{ (bulkFile.size / 1024 / 1024).toFixed(2) }} MB · {{ bulkTotalPages || '?' }} pàgines detectades</div>
              </div>
              <button class="btn btn-outline btn-sm" @click="clearBulkFile" style="color:var(--color-danger);">✕</button>
            </div>
            <input ref="bulkPdfInput" type="file" accept="application/pdf" @change="onBulkFileSelected" style="display:none;" />
          </div>

          <div v-if="bulkProcessing" style="text-align:center;padding:20px;">
            <div style="font-size:2rem;animation:spin 1s linear infinite;">⏳</div>
            <div style="font-weight:600;margin-top:8px;">Processant PDF... Extraient DNIs de {{ bulkTotalPages }} pàgines</div>
            <div class="text-small text-muted">Pàgina {{ bulkCurrentPage }} de {{ bulkTotalPages }}</div>
            <div style="width:100%;height:6px;background:var(--color-bg);border-radius:3px;margin-top:12px;overflow:hidden;">
              <div style="height:100%;background:linear-gradient(90deg,var(--color-primary),var(--color-accent));border-radius:3px;transition:width 0.3s;" :style="{ width: bulkProgress + '%' }"></div>
            </div>
          </div>

          <div v-if="bulkError" style="padding:12px;background:rgba(239,68,68,0.08);border-radius:8px;margin-top:12px;color:var(--color-danger);font-size:0.9rem;">
            ⚠️ {{ bulkError }}
          </div>

          <div class="modal-footer" v-if="!bulkProcessing">
            <button class="btn btn-outline" @click="closeBulkModal">Cancel·lar</button>
            <button class="btn btn-primary" @click="processBulkPdf" :disabled="!bulkFile || !bulkMonth || !bulkYear">
              🔍 Analitzar i extreure DNIs
            </button>
          </div>
        </div>

        <!-- Step 2: Review matching -->
        <div v-if="bulkStep === 'review'" style="padding:20px;flex:1;overflow-y:auto;">
          <div style="display:flex;gap:16px;margin-bottom:16px;">
            <div class="stat-card primary" style="flex:1;padding:12px;">
              <div class="stat-label">Pàgines processades</div>
              <div class="stat-value">{{ bulkTotalPages }}</div>
            </div>
            <div class="stat-card" style="flex:1;padding:12px;border-left:3px solid var(--color-success);">
              <div class="stat-label">Matching OK</div>
              <div class="stat-value" style="color:var(--color-success);">{{ bulkMatched.length }}</div>
            </div>
            <div class="stat-card" style="flex:1;padding:12px;border-left:3px solid var(--color-danger);" v-if="bulkUnmatched.length">
              <div class="stat-label">Sense treballador</div>
              <div class="stat-value" style="color:var(--color-danger);">{{ bulkUnmatched.length }}</div>
            </div>
          </div>

          <!-- Matched -->
          <h4 style="font-size:0.9rem;margin-bottom:8px;">✅ Nòmines assignades ({{ bulkMatched.length }})</h4>
          <div class="table-container" style="max-height:250px;overflow-y:auto;margin-bottom:16px;">
            <table>
              <thead><tr><th>Pàg.</th><th>DNI detectat</th><th>Treballador</th><th>Mida</th></tr></thead>
              <tbody>
                <tr v-for="m in bulkMatched" :key="m.pageIndex" style="background:rgba(76,175,80,0.03);">
                  <td>{{ m.pageIndex + 1 }}</td>
                  <td style="font-family:monospace;font-weight:600;">{{ m.dni }}</td>
                  <td>{{ m.workerName }} <span class="text-muted text-small">({{ m.workerEmail }})</span></td>
                  <td class="text-small text-muted">{{ m.sizeKb }} KB</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Unmatched -->
          <div v-if="bulkUnmatched.length">
            <h4 style="font-size:0.9rem;margin-bottom:8px;color:var(--color-danger);">⚠️ DNIs no trobats al sistema ({{ bulkUnmatched.length }})</h4>
            <div class="table-container" style="max-height:150px;overflow-y:auto;margin-bottom:16px;">
              <table>
                <thead><tr><th>Pàg.</th><th>DNI detectat</th><th>Estat</th></tr></thead>
                <tbody>
                  <tr v-for="u in bulkUnmatched" :key="u.pageIndex" style="background:rgba(239,68,68,0.03);">
                    <td>{{ u.pageIndex + 1 }}</td>
                    <td style="font-family:monospace;">{{ u.dni || '(no detectat)' }}</td>
                    <td class="text-small" style="color:var(--color-danger);">{{ u.reason }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-outline" @click="bulkStep = 'upload'">← Tornar</button>
            <button class="btn btn-primary" @click="confirmBulkUpload" :disabled="!bulkMatched.length">
              ✓ Confirmar i assignar {{ bulkMatched.length }} nòmines
            </button>
          </div>
        </div>

        <!-- Step 3: Done -->
        <div v-if="bulkStep === 'done'" style="padding:40px;text-align:center;">
          <div style="font-size:3rem;margin-bottom:16px;">🎉</div>
          <h3 style="color:var(--color-success);margin-bottom:8px;">Nòmines assignades correctament!</h3>
          <p class="text-muted">S'han assignat {{ bulkSavedCount }} nòmines del període {{ bulkMonth }}/{{ bulkYear }} als treballadors corresponents.</p>
          <p class="text-small text-muted" style="margin-top:12px;">Política de retenció: 48 mesos des de la data de pujada.</p>
          <button class="btn btn-primary" style="margin-top:20px;" @click="closeBulkModal">Tancar</button>
        </div>
      </div>
    </div>

    <!-- ═══ INDIVIDUAL UPLOAD MODAL ═══ -->
    <div v-if="showUploadModal" class="modal-overlay" @click.self="showUploadModal = false">
      <div class="modal" style="max-width:550px;">
        <div class="modal-header">
          <h3 class="modal-title">Pujar Nòmina Individual (PDF)</h3>
          <button class="icon-btn" @click="showUploadModal = false">✕</button>
        </div>

        <div class="form-group">
          <label class="form-label">Treballador Destinatari</label>
          <SelectorPersona v-model="newPayroll.user_id" :persones="workers" placeholder="Tria el treballador…" detall="dni" style="width:100%;" />
        </div>

        <div class="form-group">
          <label class="form-label">Mes i Any (Període)</label>
          <div style="display:flex; gap:8px;">
            <select class="form-select" v-model="newPayroll.month">
              <option value="" disabled>Mes</option>
              <option value="01">Gener</option>
              <option value="02">Febrer</option>
              <option value="03">Març</option>
              <option value="04">Abril</option>
              <option value="05">Maig</option>
              <option value="06">Juny</option>
              <option value="07">Juliol</option>
              <option value="08">Agost</option>
              <option value="09">Setembre</option>
              <option value="10">Octubre</option>
              <option value="11">Novembre</option>
              <option value="12">Desembre</option>
            </select>
            <select class="form-select" v-model="newPayroll.year">
              <option value="" disabled>Any</option>
              <option v-for="y in selectableYears" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Document PDF</label>
          <div v-if="!newPayroll.pdf_data" style="border:2px dashed var(--color-border-light);border-radius:12px;padding:32px;text-align:center;cursor:pointer;" @click="$refs.pdfInput.click()">
            <div style="font-size:2rem;margin-bottom:8px;">📎</div>
            <div style="font-weight:600;font-size:0.9rem;">Feu clic per seleccionar PDF</div>
            <div class="text-small text-muted">Només .pdf. Mida màxima 4MB.</div>
          </div>
          <div v-else style="display:flex;align-items:center;gap:14px;padding:14px;border:1px solid var(--color-border-light);border-radius:10px;background:rgba(59,130,246,0.03);">
            <div style="font-size:2rem;">📄</div>
            <div style="flex:1;">
              <div style="font-weight:600;font-size:0.9rem;">{{ newPayroll.file_name }}</div>
              <div class="text-small text-muted">Pujat temporalment per processar</div>
            </div>
            <button class="btn btn-outline btn-sm" @click="newPayroll.pdf_data = null; $refs.pdfInput.value = ''" style="color:var(--color-danger);">✕</button>
          </div>
          <input ref="pdfInput" type="file" accept="application/pdf" @change="onPdfSelected" style="display:none;" />
        </div>

        <div class="modal-footer">
          <button class="btn btn-outline" @click="showUploadModal = false">Cancel·lar</button>
          <button class="btn btn-primary" @click="savePayroll" :disabled="!isFormValid">Pujar i Assignar</button>
        </div>
      </div>
    </div>

    <ImportPortalAnticModal v-if="showImportAntic" @close="showImportAntic = false" @imported="fetchData" />

    <!-- ═══ PDF PREVIEW MODAL ═══ -->
    <div v-if="showPreviewModal" class="modal-overlay" @click.self="closePreview">
      <div class="modal" style="max-width:500px;">
        <div class="modal-header">
          <h3 class="modal-title">📄 {{ previewPayroll?.file_name }}</h3>
          <button class="icon-btn" @click="closePreview">✕</button>
        </div>
        <div style="padding:24px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:16px;">
          <span style="font-size:3rem;">📄</span>
          <p style="font-weight:600;">{{ previewPayroll?.file_name }}</p>
          <p class="text-small text-muted">Feu clic per obrir el PDF en una nova pestanya del navegador.</p>
          <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;">
            <button class="btn btn-primary" @click="openInNewTab">🔗 Obrir en nova pestanya</button>
            <button class="btn btn-outline" @click="downloadPreview">⬇️ Descarregar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import SelectorPersona from '../components/SelectorPersona.vue'
import { confirma, demana } from '../utils/dialegs'
import { ref, computed, reactive, onMounted } from 'vue'
import { useEstatUrl, idOText } from '../composables/useEstatUrl'
import { db } from '../services/db'
import { auditLog } from '../services/audit'
import { useAuthStore } from '../stores/auth'
import ImportPortalAnticModal from '../components/ImportPortalAnticModal.vue'
import { PDFDocument } from 'pdf-lib'
import * as pdfjsLib from 'pdfjs-dist/build/pdf'

// Configure pdf.js worker — using unpkg for version matching reliability
pdfjsLib.GlobalWorkerOptions.workerSrc = `https://unpkg.com/pdfjs-dist@${pdfjsLib.version}/build/pdf.worker.min.mjs`

const authStore = useAuthStore()

const showUploadModal = ref(false)
const showBulkModal = ref(false)
const showImportAntic = ref(false)
const showPreviewModal = ref(false)
const filterUser = ref('all')
const filterMonth = ref('')
const filterYear = ref('')
useEstatUrl('persona', filterUser, idOText)
useEstatUrl('mes', filterMonth)
useEstatUrl('any', filterYear, idOText)
const sortOrder = ref('desc')
const previewPayroll = ref(null)
const previewUrl = ref(null)

const workers = ref([])
const payrolls = ref([])
const uniqueWorkers = computed(() => new Set(payrolls.value.map(p => p.user_id)).size)

async function fetchData() {
  try {
    const allUsers = await db.getUsers()
    workers.value = allUsers.filter(u => u.role === 'worker')
    payrolls.value = (await db.getPayrolls()).sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)

const availableYears = computed(() => {
  const years = new Set(payrolls.value.map(p => p.year).filter(y => y))
  const currentYear = new Date().getFullYear()
  years.add(currentYear)
  return Array.from(years).sort((a, b) => b - a)
})

const selectableYears = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 10 }, (_, i) => currentYear - 5 + i)
})

const filteredPayrolls = computed(() => {
  let result = payrolls.value.filter(p => {
    if (filterUser.value !== 'all' && p.user_id !== filterUser.value) return false
    if (filterYear.value && String(p.year) !== String(filterYear.value)) return false
    if (filterMonth.value && String(p.month) !== String(filterMonth.value)) return false
    return true
  })
  
  result.sort((a, b) => {
    const aDate = (a.year || 0) * 12 + (a.month || 0);
    const bDate = (b.year || 0) * 12 + (b.month || 0);
    if (aDate === bDate) {
        return sortOrder.value === 'desc' 
            ? new Date(b.created_at) - new Date(a.created_at)
            : new Date(a.created_at) - new Date(b.created_at);
    }
    return sortOrder.value === 'desc' ? bDate - aDate : aDate - bDate;
  })
  
  return result
})

const newPayroll = reactive({ user_id: '', month: '', year: new Date().getFullYear(), pdf_data: null, file_name: '' })

const isFormValid = computed(() => newPayroll.user_id && newPayroll.month && newPayroll.year && newPayroll.pdf_data)

function getWorkerName(id) { 
  return workers.value.find(u => u.id === id)?.name || 'Treballador desconegut' 
}
function getWorkerDni(id) { 
  return workers.value.find(u => u.id === id)?.dni || '—' 
}

function formatDate(ds) {
  return new Date(ds).toLocaleDateString('ca-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

// ── Individual Upload ────────────────────────────────────────
function onPdfSelected(e) {
  const file = e.target.files?.[0]
  if (file && file.type === 'application/pdf') {
    if (file.size > 4.5 * 1024 * 1024) { alert('El PDF és massa gran.'); return }
    const reader = new FileReader()
    reader.onload = () => {
      newPayroll.pdf_data = reader.result
      newPayroll.file_name = file.name
    }
    reader.readAsDataURL(file)
  }
}

async function savePayroll() {
  try {
    const p = await db.addPayroll({
      user_id: newPayroll.user_id,
      month: `${newPayroll.year}-${newPayroll.month}`,
      pdf_data: newPayroll.pdf_data,
      file_name: newPayroll.file_name
    })
    auditLog(authStore.userId, 'UPLOAD_PAYROLL', 'payroll', p.id, `Nòmina pujada a ${getWorkerName(p.user_id)} per a ${p.month}/${p.year}`)
    await fetchData()
    showUploadModal.value = false
    Object.assign(newPayroll, { user_id: '', month: '', year: new Date().getFullYear(), pdf_data: null, file_name: '' })
    alert('Nòmina desada correctament.')
  } catch (e) { console.error(e) }
}

// ── Bulk Upload ──────────────────────────────────────────────
const bulkStep = ref('upload')
const bulkFile = ref(null)
const bulkFileArrayBuffer = ref(null)
const bulkMonth = ref('')
const bulkYear = ref(new Date().getFullYear())
const bulkTotalPages = ref(0)
const bulkCurrentPage = ref(0)
const bulkProgress = ref(0)
const bulkProcessing = ref(false)
const bulkError = ref('')
const bulkMatched = ref([])
const bulkUnmatched = ref([])
const bulkSavedCount = ref(0)

// Each entry: { pageIndex, dni, workerId, workerName, workerEmail, pdfBytes, sizeKb }
const bulkPageData = ref([])

function closeBulkModal() {
  showBulkModal.value = false
  bulkStep.value = 'upload'
  bulkFile.value = null
  bulkFileArrayBuffer.value = null
  bulkMonth.value = ''
  bulkYear.value = new Date().getFullYear()
  bulkTotalPages.value = 0
  bulkProcessing.value = false
  bulkError.value = ''
  bulkMatched.value = []
  bulkUnmatched.value = []
  bulkPageData.value = []
}

function clearBulkFile() {
  bulkFile.value = null
  bulkFileArrayBuffer.value = null
  bulkTotalPages.value = 0
  if (document.querySelector('input[type=file]')) document.querySelector('input[type=file]').value = ''
}

function onBulkFileSelected(e) {
  const file = e.target.files?.[0]
  if (file && file.type === 'application/pdf') {
    bulkFile.value = file
    readBulkFile(file)
  }
}

function onBulkDrop(e) {
  const file = e.dataTransfer.files?.[0]
  if (file && file.type === 'application/pdf') {
    bulkFile.value = file
    readBulkFile(file)
  }
}

async function readBulkFile(file) {
  const arrayBuffer = await file.arrayBuffer()
  bulkFileArrayBuffer.value = arrayBuffer
  // Quick page count
  try {
    const pdfDoc = await PDFDocument.load(arrayBuffer)
    bulkTotalPages.value = pdfDoc.getPageCount()
  } catch (e) {
    bulkError.value = 'Error al llegir el PDF: ' + e.message
  }
}

// DNI/NIE regex patterns
const DNI_REGEX = /\b(\d{8}\s*[A-Z])\b/g
const NIE_REGEX = /\b([XYZ]\s*\d{7}\s*[A-Z])\b/g

function extractDniFromText(text) {
  const dnis = []
  const upperText = text.toUpperCase()
  
  let match
  DNI_REGEX.lastIndex = 0
  while ((match = DNI_REGEX.exec(upperText)) !== null) {
    dnis.push(match[1].replace(/\s/g, ''))
  }
  NIE_REGEX.lastIndex = 0
  while ((match = NIE_REGEX.exec(upperText)) !== null) {
    dnis.push(match[1].replace(/\s/g, ''))
  }
  
  // Return unique DNIs
  return [...new Set(dnis)]
}

async function processBulkPdf() {
  if (!bulkFileArrayBuffer.value || !bulkMonth.value) return
  
  bulkProcessing.value = true
  bulkError.value = ''
  bulkMatched.value = []
  bulkUnmatched.value = []
  bulkPageData.value = []

  try {
    const arrayBuffer = bulkFileArrayBuffer.value
    
    // Load with pdfjs-dist for text extraction
    const pdfDoc = await pdfjsLib.getDocument({ data: arrayBuffer.slice(0) }).promise
    const totalPages = pdfDoc.numPages
    bulkTotalPages.value = totalPages

    // Load with pdf-lib for page splitting
    const pdfLibDoc = await PDFDocument.load(arrayBuffer)
    
    for (let i = 0; i < totalPages; i++) {
      bulkCurrentPage.value = i + 1
      bulkProgress.value = Math.round(((i + 1) / totalPages) * 100)
      
      // Extract text from page using pdfjs-dist
      const page = await pdfDoc.getPage(i + 1) // 1-indexed
      const textContent = await page.getTextContent()
      const pageText = textContent.items.map(item => item.str).join(' ')
      
      // Extract DNIs
      const dnis = extractDniFromText(pageText)
      
      // Split page to individual PDF using pdf-lib
      const newPdf = await PDFDocument.create()
      const [copiedPage] = await newPdf.copyPages(pdfLibDoc, [i])
      newPdf.addPage(copiedPage)
      const pdfBytes = await newPdf.save()
      
      // Convert to base64 data URL for storage
      const base64 = btoa(String.fromCharCode(...new Uint8Array(pdfBytes)))
      const dataUrl = `data:application/pdf;base64,${base64}`
      
      if (dnis.length > 0) {
        const dni = dnis[0] // Use first DNI found
        const worker = workers.value.find(u => u.dni === dni)
        
        if (worker) {
          const entry = {
            pageIndex: i,
            dni,
            workerId: worker.id,
            workerName: worker.name,
            workerEmail: worker.email,
            pdfDataUrl: dataUrl,
            sizeKb: Math.round(pdfBytes.length / 1024)
          }
          bulkMatched.value.push(entry)
          bulkPageData.value.push(entry)
        } else {
          bulkUnmatched.value.push({ pageIndex: i, dni, reason: `DNI ${dni} no correspon a cap treballador registrat` })
        }
      } else {
        bulkUnmatched.value.push({ pageIndex: i, dni: null, reason: 'No s\'ha trobat cap DNI/NIE al text de la pàgina' })
      }

      // Small delay to let UI update
      await new Promise(r => setTimeout(r, 50))
    }
    
    bulkStep.value = 'review'
  } catch (err) {
    bulkError.value = `Error processant el PDF: ${err.message}`
    console.error('[BulkPayroll] Error:', err)
  } finally {
    bulkProcessing.value = false
  }
}

async function confirmBulkUpload() {
  const combinedBulkMonth = `${bulkYear.value}-${bulkMonth.value}`;
  const payrollsToAdd = bulkMatched.value.map(entry => ({
    user_id: entry.workerId,
    month: combinedBulkMonth,
    pdf_data: entry.pdfDataUrl,
    file_name: `nomina_${entry.dni}_${combinedBulkMonth}.pdf`
  }))
  
  try {
    const saved = await db.addPayrollBulk(payrollsToAdd)
    bulkSavedCount.value = saved.length
    
    auditLog(authStore.userId, 'BULK_UPLOAD_PAYROLL', 'payroll', null,
      `Pujada massiva: ${saved.length} nòmines per al període ${combinedBulkMonth}. DNIs: ${bulkMatched.value.map(m => m.dni).join(', ')}`)
    
    await fetchData()
    bulkStep.value = 'done'
  } catch (e) { console.error(e) }
}

// ── Preview & Delete ─────────────────────────────────────────
function dataUrlToBlob(dataUrl) {
  const base64 = dataUrl.startsWith('data:application/pdf;base64,')
    ? dataUrl.replace('data:application/pdf;base64,', '')
    : dataUrl
  const binary = atob(base64)
  const bytes = new Uint8Array(binary.length)
  for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i)
  return new Blob([bytes], { type: 'application/pdf' })
}

// El llistat ja no porta el PDF: es demana per fila la primera vegada que cal
// (obrir, descarregar o imprimir) i es memoritza a la fila per no repetir la crida.
async function ensurePdf(p) {
  if (!p) return null
  if (p.pdf_data) return p.pdf_data
  try {
    const full = await db.getPayroll(p.id)
    p.pdf_data = full.pdf_data
    return p.pdf_data
  } catch (e) {
    console.error(e)
    alert('No s\'ha pogut carregar el PDF de la nòmina.')
    return null
  }
}

function openPreview(p) {
  previewPayroll.value = p
  showPreviewModal.value = true
}

function closePreview() {
  showPreviewModal.value = false
  previewPayroll.value = null
}

async function openInNewTab() {
  const pdf = await ensurePdf(previewPayroll.value)
  if (!pdf) return
  const blob = dataUrlToBlob(pdf)
  const url = URL.createObjectURL(blob)
  window.open(url, '_blank')
  setTimeout(() => URL.revokeObjectURL(url), 30000)
  auditLog(authStore.userId, 'VIEW_PAYROLL', 'payroll', previewPayroll.value.id, `Vista prèvia admin: ${previewPayroll.value.file_name}`)
}

async function downloadPreview() {
  const pdf = await ensurePdf(previewPayroll.value)
  if (!pdf) return
  const a = document.createElement('a')
  a.href = pdf
  a.download = previewPayroll.value.file_name || 'nomina.pdf'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  auditLog(authStore.userId, 'DOWNLOAD_PAYROLL', 'payroll', previewPayroll.value.id, `Descàrrega admin: ${previewPayroll.value.file_name}`)
}

async function remove(id) {
  if (await confirma('Esteu segur de voler eliminar definitivament aquesta nòmina?')) {
    try {
      await db.deletePayroll(id)
      auditLog(authStore.userId, 'DELETE_PAYROLL', 'payroll', id, 'Eliminació manual de nòmina per admin')
      await fetchData()
    } catch (e) { console.error(e) }
  }
}

// ── Multi-select & batch actions ───────────────────────────
const selectedPayrolls = ref([])
const allSelected = computed(() => filteredPayrolls.value.length > 0 && filteredPayrolls.value.every(p => selectedPayrolls.value.includes(p.id)))

function toggleSelect(id) {
  const idx = selectedPayrolls.value.indexOf(id)
  if (idx >= 0) selectedPayrolls.value.splice(idx, 1)
  else selectedPayrolls.value.push(id)
}
function toggleSelectAll(checked) {
  if (checked) selectedPayrolls.value = filteredPayrolls.value.map(p => p.id)
  else selectedPayrolls.value = []
}

async function printPayroll(p) {
  const pdf = await ensurePdf(p)
  if (!pdf) return
  // data: URLs are cross-origin in Chrome — use blob URL so contentWindow.print() works
  const blob = dataUrlToBlob(pdf)
  const url = URL.createObjectURL(blob)
  const iframe = document.createElement('iframe')
  iframe.style.display = 'none'
  iframe.src = url
  document.body.appendChild(iframe)
  iframe.onload = () => {
    iframe.contentWindow.print()
    setTimeout(() => { document.body.removeChild(iframe); URL.revokeObjectURL(url) }, 2000)
  }
  auditLog(authStore.userId, 'PRINT_PAYROLL', 'payroll', p.id, `Nòmina impresa: ${getWorkerName(p.user_id)} - ${p.month}/${p.year}`)
}
async function printSelected() {
  const selected = filteredPayrolls.value.filter(p => selectedPayrolls.value.includes(p.id))
  // Seqüencial: cada PDF es descarrega a demanda, no llancem N peticions alhora.
  for (const p of selected) await printPayroll(p)
}

function emailPayroll(p) {
  const worker = workers.value.find(u => u.id === p.user_id)
  const email = worker?.email || ''
  const subject = encodeURIComponent(`Nòmina ${p.month}/${p.year} — CRT RRHH`)
  const body = encodeURIComponent(`Benvolgut/da ${worker?.name},\n\nAdjuntem la seva nòmina corresponent al període ${p.month}/${p.year}.\n\nCRT - Centre de Rehabilitació Terapèutica`)
  window.open(`mailto:${email}?subject=${subject}&body=${body}`, '_blank')
  auditLog(authStore.userId, 'EMAIL_PAYROLL', 'payroll', p.id, `Nòmina enviada per email a ${email}`)
}
function emailSelected() {
  const selected = filteredPayrolls.value.filter(p => selectedPayrolls.value.includes(p.id))
  selected.forEach(p => emailPayroll(p))
}

async function chatSendPayroll(p) {
  const worker = workers.value.find(u => u.id === p.user_id)
  if (!worker) return
  try {
    const conv = await db.getOrCreateDm(authStore.userId, worker.id)
    await db.addChatMessage({
      conversation_id: conv.id,
      sender_id: authStore.userId,
      content: `📄 Nòmina ${p.month}/${p.year} adjuntada. Si us plau, reviseu-la des de la secció de Nòmines.`,
      type: 'text',
      file_data: null,
      file_name: null
    })
    auditLog(authStore.userId, 'CHAT_SEND_PAYROLL', 'payroll', p.id, `Nòmina notificada per xat intern a ${worker.name}`)
    alert(`✅ Missatge enviat a ${worker.name} per missatgeria interna`)
  } catch (e) { console.error(e) }
}
function chatSendSelected() {
  const selected = filteredPayrolls.value.filter(p => selectedPayrolls.value.includes(p.id))
  selected.forEach(p => chatSendPayroll(p))
  selectedPayrolls.value = []
}
</script>

<style scoped>
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
