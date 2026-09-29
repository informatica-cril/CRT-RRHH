<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">💰 {{ t('my_payrolls') }}</h1>
        <p class="page-subtitle">{{ t('my_payrolls_subtitle') }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-outline btn-sm" @click="showPolicyModal = true" style="font-size:0.8rem;">📋 {{ t('payroll_retention_policy') }}</button>
      </div>
    </div>

    <!-- Policy alert for first visit -->
    <div v-if="!policyDismissed" style="background:linear-gradient(135deg,rgba(59,130,246,0.06),rgba(147,51,234,0.04));border:1px solid rgba(59,130,246,0.15);border-radius:12px;padding:16px;margin-bottom:20px;display:flex;align-items:flex-start;gap:12px;">
      <div style="font-size:1.5rem;flex-shrink:0;">ℹ️</div>
      <div style="flex:1;">
        <div style="font-weight:600;font-size:0.9rem;margin-bottom:4px;">Política de Retenció de Nòmines</div>
        <div class="text-small" style="line-height:1.6;color:var(--color-text-secondary);">
          Les nòmines es conserven durant un màxim de <strong>48 mesos</strong> (4 anys) des de la seva publicació, d'acord amb la normativa vigent (art. 34.9 ET). 
          Passat aquest termini, les nòmines no estaran disponibles en aquesta plataforma. 
          <strong>Les nòmines només es poden consultar en línia i no es poden descarregar.</strong>
        </div>
      </div>
      <button class="icon-btn" @click="dismissPolicy" style="flex-shrink:0;font-size:0.8rem;">✕</button>
    </div>

    <!-- Stats -->
    <div class="stats-grid mb-lg">
      <div class="stat-card primary">
        <div class="stat-label">{{ t('available_documents') }}</div>
        <div class="stat-value">{{ myPayrolls.length }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">{{ t('current_year') }} ({{ currentYear }})</div>
        <div class="stat-value">{{ payrollsThisYear }} mesos</div>
      </div>
      <div class="stat-card" style="border-left:3px solid var(--color-warning);">
        <div class="stat-label">{{ t('max_retention') }}</div>
        <div class="stat-value" style="font-size:1.1rem;">48 mesos</div>
      </div>
    </div>

    <!-- List -->
    <div class="card">
      <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
          <h3 class="card-title">{{ t('historic_archive') }}</h3>
          <span class="text-small text-muted">🔒 {{ t('read_only') }}</span>
        </div>
        <div style="display:flex;gap:12px;">
          <select class="form-select" v-model="filterYear" style="width:130px;font-size:0.85rem;">
            <option value="">{{ t('all_years') }}</option>
            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
          </select>
          <select class="form-select" v-model="sortOrder" style="width:180px;font-size:0.85rem;">
            <option value="desc">{{ t('most_recent_first') }}</option>
            <option value="asc">{{ t('oldest_first') }}</option>
          </select>
        </div>
      </div>
      <div v-if="myPayrolls.length === 0" class="empty-state">
        <p>{{ t('no_payrolls') }}</p>
      </div>
      <div class="table-container" v-else>
        <table>
          <thead>
            <tr>
              <th>{{ t('period_my') }}</th>
              <th>{{ t('name') }}</th>
              <th>{{ t('publication_date') }}</th>
              <th>{{ t('expires') }}</th>
              <th>{{ t('status') }}</th>
              <th>{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in myPayrolls" :key="p.id" :style="p.signed_at ? 'background:rgba(76,175,80,0.04);' : ''">
              <td><span class="badge badge-info" style="font-size:1rem;font-weight:700;">{{ String(p.month).padStart(2, '0') }} / {{ p.year }}</span></td>
              <td>📄 {{ p.file_name }}</td>
              <td class="text-small text-muted">{{ formatDate(p.created_at) }}</td>
              <td class="text-small text-muted">
                <span :style="isExpiringSoon(p) ? 'color:var(--color-warning);font-weight:600;' : ''">
                  {{ p.expires_at ? formatDate(p.expires_at) : '—' }}
                </span>
                <span v-if="isExpiringSoon(p)" class="text-small" style="display:block;font-size:0.65rem;color:var(--color-warning);">⚠️ {{ t('expiring_soon') }}</span>
              </td>
              <td>
                <span v-if="p.signed_at" class="badge badge-success" style="font-size:0.65rem;">✓ {{ t('signed') }} {{ formatDate(p.signed_at) }}</span>
                <span v-else class="badge badge-warning" style="font-size:0.65rem;">⚠️ {{ t('pending_signature') }}</span>
              </td>
              <td style="display:flex;gap:8px;align-items:center;">
                <button v-if="p.signed_at" class="btn btn-outline btn-sm" @click="viewPayroll(p)">👁️ {{ t('consult') }}</button>
                <button v-if="!p.signed_at" class="btn btn-primary btn-sm" @click="openSignModal(p)">✍️ {{ t('sign_reception') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ PDF VIEWER MODAL ═══ -->
    <div v-if="showViewerModal" class="modal-overlay" @click.self="showViewerModal = false">
      <div class="modal" style="max-width:900px;max-height:92vh;display:flex;flex-direction:column;">
        <div class="modal-header">
          <div>
            <h3 class="modal-title">📄 {{ viewingPayroll?.file_name }}</h3>
            <div class="text-small text-muted">Període: {{ String(viewingPayroll?.month).padStart(2, '0') }}/{{ viewingPayroll?.year }}</div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;">
            <button class="btn btn-primary btn-sm" @click="downloadPayroll">⬇️ {{ t('download') }}</button>
            <button class="icon-btn" @click="showViewerModal = false">✕</button>
          </div>
        </div>
        <!-- Mòbil/APK: Android WebView no renderitza PDFs en iframes -->
        <div v-if="isNative" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px;text-align:center;gap:16px;">
          <span style="font-size:3rem;">📄</span>
          <p style="font-weight:700;font-size:1.1rem;">{{ viewingPayroll?.file_name }}</p>
          <p class="text-small text-muted">La visualització integrada no és compatible amb aquest dispositiu.<br>Descarrega el document per obrir-lo amb el visor PDF del dispositiu.</p>
          <button class="btn btn-primary" @click="downloadPayroll">⬇️ {{ t('download') }}</button>
        </div>
        <!-- Web: iframe normal -->
        <div v-else-if="pdfLoadError" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px;text-align:center;gap:12px;">
          <span style="font-size:2.5rem;">⚠️</span>
          <p style="font-weight:600;color:var(--color-text-secondary);">No s'ha pogut carregar el document.</p>
          <button class="btn btn-primary" @click="downloadPayroll">⬇️ {{ t('download') }}</button>
        </div>
        <iframe
          v-else-if="viewerUrl && !isNative"
          :src="viewerUrl"
          style="flex:1;border:none;border-radius:0 0 12px 12px;min-height:500px;"
          @error="pdfLoadError = true"
        ></iframe>
      </div>
    </div>

    <!-- ═══ SIGN MODAL ═══ -->
    <div v-if="showSignModal" class="modal-overlay" @click.self="showSignModal = false">
      <div class="modal" style="max-width:500px;">
        <div class="modal-header">
          <h3 class="modal-title">✍️ Firma Electrònica de Recepció</h3>
          <button class="icon-btn" @click="showSignModal = false">✕</button>
        </div>
        <div style="margin-bottom:20px;font-size:0.95rem;line-height:1.5;">
          <p style="margin-bottom:12px;">En fer clic a "Firmar digitalment", reconeixeu l'accés i recepció de la nòmina corresponent al període <strong>{{ String(selectedPayroll?.month).padStart(2, '0') }}/{{ selectedPayroll?.year }}</strong>.</p>
          <p style="color:var(--color-text-secondary);font-size:0.85rem;">Es generarà un hash criptogràfic únic associat al vostre usuari ({{ authStore.userEmail }}) amb validesa legal a efectes de notificació.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showSignModal = false">Cancel·lar</button>
          <button class="btn btn-primary" @click="confirmSign">Firmar digitalment</button>
        </div>
      </div>
    </div>

    <!-- ═══ POLICY MODAL ═══ -->
    <div v-if="showPolicyModal" class="modal-overlay" @click.self="showPolicyModal = false">
      <div class="modal" style="max-width:600px;">
        <div class="modal-header" style="background:linear-gradient(135deg,rgba(59,130,246,0.08),rgba(147,51,234,0.04));border-radius:12px 12px 0 0;">
          <h3 class="modal-title">📋 Política de Retenció de Nòmines</h3>
          <button class="icon-btn" @click="showPolicyModal = false">✕</button>
        </div>
        <div style="padding:20px;font-size:0.9rem;line-height:1.8;">
          <h4 style="margin-bottom:12px;">1. Principi de conservació</h4>
          <p>D'acord amb l'article 34.9 de l'Estatut dels Treballadors (RDL 8/2019), les nòmines i registres laborals es conserven durant un mínim de <strong>4 anys</strong> (48 mesos) des de la seva publicació.</p>
          
          <h4 style="margin:16px 0 12px;">2. Obsolescència automàtica</h4>
          <p>Un cop transcorreguts els 48 mesos, les nòmines <strong>deixen d'estar disponibles</strong> a la plataforma de manera automàtica. Es recomana als treballadors que guardin còpia privada si cal, tot i que l'empresa conserva els originals en el seu arxiu físic i digital intern conforme a la normativa.</p>
          
          <h4 style="margin:16px 0 12px;">3. Mode de consulta</h4>
          <p>Les nòmines es posen a disposició del treballador <strong>exclusivament en mode lectura</strong> (sense possibilitat de descàrrega directa) per raons de seguretat documental i protecció de dades (RGPD/LOPDGDD).</p>
          
          <h4 style="margin:16px 0 12px;">4. Firma electrònica de recepció</h4>
          <p>La plataforma permet la firma electrònica de recepció, la qual cosa acredita que el treballador ha tingut accés efectiu al document. Aquesta firma té efectes legals a efectes de notificació laboral.</p>

          <h4 style="margin:16px 0 12px;">5. Base legal</h4>
          <ul style="padding-left:20px;">
            <li>Art. 34.9 Estatut dels Treballadors</li>
            <li>Reglament (UE) 2016/679 (RGPD)</li>
            <li>LO 3/2018 (LOPDGDD)</li>
            <li>RD 311/2022 (ENS)</li>
          </ul>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary" @click="showPolicyModal = false">Entès</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { db } from '../services/db'
import { useAuthStore } from '../stores/auth'
import { auditLog } from '../services/audit'
import { Capacitor } from '@capacitor/core'
import { i18n } from '../i18n'

const authStore = useAuthStore()
const isNative = Capacitor.isNativePlatform()
const t = (key) => i18n.t(key)

const payrolls = ref([])
const filterYear = ref('')
const sortOrder = ref('desc')

const availableYears = computed(() => {
  const years = new Set(payrolls.value.map(p => p.year).filter(y => y))
  const currentYear = new Date().getFullYear()
  years.add(currentYear)
  return Array.from(years).sort((a, b) => b - a)
})

const myPayrolls = computed(() => {
  let result = payrolls.value.filter(p => {
    if (filterYear.value && String(p.year) !== String(filterYear.value)) return false
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

async function fetchData() {
  try {
    const raw = await db.getWorkerPayrolls(authStore.userId)
    payrolls.value = raw.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  } catch (e) {
    console.error(e)
  }
}

onMounted(() => {
  fetchData()
  policyDismissed.value = localStorage.getItem('crt_payroll_policy_dismissed') === 'true'
})

const currentYear = new Date().getFullYear().toString()
const payrollsThisYear = computed(() => payrolls.value.filter(p => String(p.year) === currentYear).length)

const showSignModal = ref(false)
const showViewerModal = ref(false)
const showPolicyModal = ref(false)
const selectedPayroll = ref(null)
const viewingPayroll = ref(null)
const viewerUrl = ref(null)
const policyDismissed = ref(false)
const pdfLoadError = ref(false)

// onMounted moved up

function dismissPolicy() {
  policyDismissed.value = true
  localStorage.setItem('crt_payroll_policy_dismissed', 'true')
}

function isExpiringSoon(p) {
  if (!p.expires_at) return false
  const monthsLeft = (new Date(p.expires_at) - new Date()) / (1000 * 60 * 60 * 24 * 30)
  return monthsLeft <= 6 && monthsLeft > 0
}

function formatDate(ds) {
  if (!ds) return '—'
  return new Date(ds).toLocaleDateString('ca-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

async function viewPayroll(p) {
  try {
    pdfLoadError.value = false
    if (!p.viewed_at) await db.viewPayroll(p.id).catch(() => {})
    auditLog(authStore.userId, 'VIEW_PAYROLL', 'payroll', p.id, `Consulta de nòmina ${p.month}/${p.year} (sense descàrrega)`)

    if (p.pdf_data) {
      // Use data URL directly — blob URLs fail on older Android WebViews
      viewerUrl.value = p.pdf_data.startsWith('data:')
        ? p.pdf_data
        : `data:application/pdf;base64,${p.pdf_data}`
    } else {
      viewerUrl.value = null
      pdfLoadError.value = true
    }

    viewingPayroll.value = p
    showViewerModal.value = true
  } catch (e) {
    console.error(e)
    pdfLoadError.value = true
  }
}

async function downloadPayroll() {
  if (!viewingPayroll.value || !viewerUrl.value) return
  const p = viewingPayroll.value
  const fileName = p.file_name || `nomina_${p.month}_${p.year}.pdf`

  if (isNative) {
    try {
      const { Filesystem, Directory } = await import(/* @vite-ignore */ '@capacitor/filesystem')
      const { Share } = await import(/* @vite-ignore */ '@capacitor/share')
      let base64Data = viewerUrl.value
      if (base64Data.startsWith('data:application/pdf;base64,')) {
        base64Data = base64Data.replace('data:application/pdf;base64,', '')
      }
      const result = await Filesystem.writeFile({
        path: fileName,
        data: base64Data,
        directory: Directory.Cache,
      })
      await Share.share({
        title: fileName,
        url: result.uri,
        dialogTitle: 'Desa o comparteix la nòmina',
      })
    } catch (e) {
      console.error('Error descarregant nòmina:', e)
      alert('No s\'ha pogut descarregar la nòmina.')
    }
  } else {
    const a = document.createElement('a')
    a.href = viewerUrl.value
    a.download = fileName
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
  }

  auditLog(authStore.userId, 'DOWNLOAD_PAYROLL', 'payroll', p.id, `Descàrrega de nòmina ${p.month}/${p.year}`)
}

async function openSignModal(p) {
  selectedPayroll.value = p
  showSignModal.value = true
  if (!p.viewed_at) {
    try {
      await db.viewPayroll(p.id)
      await fetchData()
    } catch (e) { console.error(e) }
  }
}

async function confirmSign() {
  if (!selectedPayroll.value) return
  const p = selectedPayroll.value
  
  const dataString = `${authStore.userId}-${p.id}-${authStore.userEmail}-${Date.now()}`
  let signatureHash = ''
  try {
    if (window.crypto && crypto.subtle) {
      const data = new TextEncoder().encode(dataString)
      const hashBuffer = await crypto.subtle.digest('SHA-256', data)
      signatureHash = Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('')
    } else {
      signatureHash = btoa(dataString).replace(/=/g, '').substring(0, 32) + ' (fallback)'
    }
    
    await db.signPayroll(p.id, signatureHash)
    auditLog(authStore.userId, 'SIGN_PAYROLL', 'payroll', p.id, `Recepció de nòmina firmada digitalment (${p.month}/${p.year})`)
    
    await fetchData()
    showSignModal.value = false
    selectedPayroll.value = null
  } catch (e) {
    console.error(e)
    alert('Error al firmar la nòmina')
  }
}
</script>
