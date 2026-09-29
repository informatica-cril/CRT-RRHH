<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">📄 Gestió documental</h1>
        <p class="page-subtitle">Distribució, control de lectura i firma electrònica</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreateModal">+ Nou document</button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="margin-bottom:24px;">
      <div class="stat-card primary">
        <div class="stat-label">Documents totals</div>
        <div class="stat-value">{{ documents.length }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Pendents firma</div>
        <div class="stat-value" style="color:var(--color-warning);">{{ totalPendingSignatures }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Urgents actius</div>
        <div class="stat-value" style="color:var(--color-danger);">{{ urgentCount }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Completats</div>
        <div class="stat-value" style="color:var(--color-success);">{{ completedCount }}</div>
      </div>
    </div>

    <!-- Filter tabs -->
    <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
      <button v-for="f in filters" :key="f.key" class="btn btn-sm"
        :class="activeFilter === f.key ? 'btn-primary' : 'btn-outline'"
        @click="activeFilter = f.key">{{ f.label }}</button>
    </div>

    <!-- Documents list -->
    <div v-for="doc in filteredDocs" :key="doc.id" class="card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
        <div style="flex:1;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
            <span v-if="doc.is_urgent" class="badge badge-danger" style="font-size:0.7rem;">🔴 URGENT</span>
            <span v-if="doc.requires_signature" class="badge badge-warning" style="font-size:0.7rem;">✍️ Firma requerida</span>
            <span class="badge badge-info" style="font-size:0.65rem;">{{ categoryName(doc.category) }}</span>
            <span v-if="doc.pdf_data" class="badge" style="font-size:0.65rem;background:rgba(239,68,68,0.1);color:#dc2626;">📄 PDF</span>
          </div>
          <h3 style="font-size:1rem;font-weight:600;margin-bottom:4px;">{{ doc.title }}</h3>
          <p class="text-small text-muted" style="margin-bottom:8px;">{{ doc.description }}</p>
          <div class="text-small text-muted">
            📎 {{ doc.file_name }} · Creat: {{ formatDate(doc.created_at) }}
            · Destinataris: {{ doc.target_users === 'all' ? 'Tots els treballadors' : doc.target_users.length + ' seleccionats' }}
          </div>
        </div>
        <div style="display:flex;gap:6px;flex-shrink:0;">
          <button class="btn btn-outline btn-sm" @click="toggleDocDetail(doc.id)">
            {{ expandedDocId === doc.id ? '▾ Tancar' : '▸ Detall' }}
          </button>
          <button v-if="doc.requires_signature" class="btn btn-outline btn-sm" @click="sendReminder(doc)" title="Enviar recordatori">🔔</button>
        </div>
      </div>

      <!-- Expanded detail: signature tracking -->
      <div v-if="expandedDocId === doc.id" style="margin-top:16px;border-top:1px solid var(--color-border-light);padding-top:16px;">
        <h4 style="font-size:0.9rem;margin-bottom:12px;">
          {{ doc.requires_signature ? 'Estat de firmes' : 'Estat de lectura' }}
        </h4>

        <!-- Progress bar -->
        <div v-if="doc.requires_signature" style="margin-bottom:16px;">
          <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:6px;">
            <span style="color:var(--color-success);font-weight:600;">{{ getSignedCount(doc.id) }} firmats</span>
            <span style="color:var(--color-warning);">{{ getViewedCount(doc.id) }} vistos sense firma</span>
            <span style="color:var(--color-danger);">{{ getPendingCount(doc.id) }} pendents</span>
          </div>
          <div style="height:8px;background:var(--color-bg);border-radius:4px;overflow:hidden;">
            <div style="height:100%;display:flex;">
              <div :style="{ width: getSignedPct(doc.id) + '%', background: 'var(--color-success)' }"></div>
              <div :style="{ width: getViewedPct(doc.id) + '%', background: 'var(--color-warning)' }"></div>
            </div>
          </div>
        </div>

        <!-- Workers table -->
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Treballador</th>
                <th>Visualitzat</th>
                <th v-if="doc.requires_signature">Firmat</th>
                <th v-if="doc.requires_signature">Hash firma</th>
                <th>Acció</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in getPendingSignatures(doc.id)" :key="s.user_id"
                :style="s.signed_at ? 'background:rgba(76,175,80,0.04);' : s.viewed_at ? 'background:rgba(249,115,22,0.04);' : ''">
                <td style="font-weight:500;">{{ s.name }}</td>
                <td>
                  <span v-if="s.viewed_at" class="badge badge-success" style="font-size:0.65rem;">✓ {{ formatDateTime(s.viewed_at) }}</span>
                  <span v-else class="badge badge-danger" style="font-size:0.65rem;">✗ No vist</span>
                </td>
                <td v-if="doc.requires_signature">
                  <span v-if="s.signed_at" class="badge badge-success" style="font-size:0.65rem;">✓ {{ formatDateTime(s.signed_at) }}</span>
                  <span v-else class="badge badge-warning" style="font-size:0.65rem;">⏳ Pendent</span>
                </td>
                <td v-if="doc.requires_signature">
                  <code v-if="s.signature_hash" class="text-small" style="font-size:0.6rem;color:var(--color-text-secondary);">{{ s.signature_hash.substring(0, 16) }}…</code>
                  <span v-else class="text-muted">—</span>
                </td>
                <td>
                  <button v-if="!s.signed_at && doc.requires_signature" class="btn btn-outline btn-sm" style="font-size:0.7rem;" @click="sendIndividualReminder(doc, s)">📧 Recordar</button>
                  <span v-else-if="s.signed_at" class="text-small" style="color:var(--color-success);">✓</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="filteredDocs.length === 0" class="card">
      <div class="empty-state"><p>No hi ha documents amb aquest filtre</p></div>
    </div>

    <!-- ═══ ONBOARDING PROFILES SECTION ═══ -->
    <div style="margin-top:32px;border-top:2px solid var(--color-border-light);padding-top:24px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
          <h2 style="font-size:1.1rem;font-weight:700;">📋 Perfils d'onboarding</h2>
          <p class="text-small text-muted">Configura el flux de documents per als nous treballadors</p>
        </div>
        <button class="btn btn-outline btn-sm" @click="showProfileForm = !showProfileForm">
          {{ showProfileForm ? '✕ Tancar' : '+ Nou perfil' }}
        </button>
      </div>

      <!-- Create/Edit Profile Form -->
      <div v-if="showProfileForm" class="card" style="margin-bottom:16px;border:2px solid var(--color-primary);">
        <h4 style="font-size:0.9rem;margin-bottom:12px;">{{ editingProfile ? 'Editar perfil' : 'Nou perfil d\'onboarding' }}</h4>
        <div class="form-group">
          <label class="form-label">Nom del perfil</label>
          <input class="form-input" v-model="profileForm.name" placeholder="Perfil estàndard — Fisioterapeuta" />
        </div>
        <div class="form-group">
          <label class="form-label">Descripció</label>
          <input class="form-input" v-model="profileForm.description" placeholder="Descripció del perfil" />
        </div>
        <div class="form-group">
          <label class="form-label">Documents (en ordre d'onboarding)</label>
          <div style="border:1px solid var(--color-border-light);border-radius:8px;padding:8px;max-height:250px;overflow-y:auto;">
            <div v-for="doc in allDocs" :key="doc.id" style="display:flex;align-items:center;gap:10px;padding:8px 4px;border-bottom:1px solid var(--color-border-light);">
              <input type="checkbox" :value="doc.id" v-model="profileForm.document_ids" style="width:16px;height:16px;" />
              <span style="font-size:0.85rem;flex:1;">{{ doc.title }}</span>
              <span class="badge badge-info" style="font-size:0.6rem;">{{ categoryName(doc.category) }}</span>
              <select v-if="profileForm.document_ids.includes(doc.id)" v-model="profileForm.doc_modes[doc.id]" class="form-select" style="font-size:0.72rem;padding:2px 6px;width:130px;min-width:130px;">
                <option value="signature">✍️ Firma requerida</option>
                <option value="view_only">👁️ Només visió</option>
              </select>
            </div>
          </div>
          <div v-if="profileForm.document_ids.length" class="text-small text-muted" style="margin-top:6px;">
            Ordre: {{ profileForm.document_ids.map(id => allDocs.find(d => d.id === id)?.title || id).join(' → ') }}
          </div>
          <div class="text-small" style="margin-top:6px;color:var(--color-primary);">
            💡 Cada document es mostra <strong>només un cop</strong>. Un cop firmat/visionat (segóns la configuració), no es tornarà a mostrar.
          </div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;cursor:pointer;font-size:0.85rem;">
          <input type="checkbox" v-model="profileForm.is_default" /> Perfil per defecte (nous treballadors)
        </label>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-primary btn-sm" @click="saveProfile" :disabled="!profileForm.name || !profileForm.document_ids.length">{{ editingProfile ? 'Guardar canvis' : 'Crear perfil' }}</button>
          <button class="btn btn-outline btn-sm" @click="cancelProfileEdit">Cancel·lar</button>
        </div>
      </div>

      <!-- Existing profiles list -->
      <div v-for="p in onboardingProfiles" :key="p.id" class="card" style="margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
              <h4 style="font-size:0.95rem;font-weight:600;">{{ p.name }}</h4>
              <span v-if="p.is_default" class="badge badge-success" style="font-size:0.6rem;">Per defecte</span>
            </div>
            <p class="text-small text-muted" style="margin-bottom:6px;">{{ p.description }}</p>
            <div class="text-small" style="color:var(--color-primary);">📄 {{ p.document_ids.length }} documents: {{ p.document_ids.map(id => documents.find(d => d.id === id)?.title || '?').join(' → ') }}</div>
          </div>
          <div style="display:flex;gap:6px;">
            <button class="btn btn-outline btn-sm" @click="editProfile(p)">✏️</button>
            <button class="btn btn-outline btn-sm" @click="toggleOnboardingStatus(p.id)">{{ expandedProfileId === p.id ? '▾' : '▸' }} Estat</button>
          </div>
        </div>

        <!-- Onboarding status per worker -->
        <div v-if="expandedProfileId === p.id" style="margin-top:14px;border-top:1px solid var(--color-border-light);padding-top:14px;">
          <h5 style="font-size:0.85rem;margin-bottom:10px;">Estat d'onboarding dels treballadors</h5>
          <div class="table-container">
            <table>
              <thead>
                <tr><th>Treballador</th><th>Perfil</th><th>Progrés</th><th>Estat</th><th>Completat</th></tr>
              </thead>
              <tbody>
                <tr v-for="w in workersWithOnboarding" :key="w.id">
                  <td style="font-weight:500;">{{ w.name }}</td>
                  <td>
                    <select class="form-select" style="font-size:0.75rem;padding:4px 8px;min-width:120px;" :value="w.onboarding_profile_id || ''" @change="assignProfile(w.id, $event.target.value)">
                      <option value="">— Cap —</option>
                      <option v-for="pr in onboardingProfiles" :key="pr.id" :value="pr.id">{{ pr.name }}</option>
                    </select>
                  </td>
                  <td>
                    <div v-if="getWorkerOnboardingStatus(w.id)" style="display:flex;align-items:center;gap:6px;">
                      <div style="flex:1;height:6px;background:var(--color-bg);border-radius:3px;overflow:hidden;min-width:60px;">
                        <div :style="{ width: getWorkerProgress(w.id) + '%', background: 'var(--color-success)', height: '100%' }"></div>
                      </div>
                      <span class="text-small">{{ getWorkerProgress(w.id) }}%</span>
                    </div>
                    <span v-else class="text-small text-muted">—</span>
                  </td>
                  <td>
                    <span v-if="w.onboarding_completed" class="badge badge-success" style="font-size:0.65rem;">✓ Completat</span>
                    <span v-else-if="getWorkerOnboardingStatus(w.id)" class="badge badge-warning" style="font-size:0.65rem;">⏳ En curs</span>
                    <span v-else class="badge badge-danger" style="font-size:0.65rem;">Pendent</span>
                  </td>
                  <td>
                    <span v-if="getWorkerOnboardingStatus(w.id)?.completed_at" class="text-small">{{ formatDateTime(getWorkerOnboardingStatus(w.id).completed_at) }}</span>
                    <span v-else class="text-muted">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-if="onboardingProfiles.length === 0" class="card">
        <div class="empty-state"><p>No hi ha perfils d'onboarding configurats</p></div>
      </div>
    </div>

    <!-- Create Document Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal" style="max-width:700px;">
        <div class="modal-header">
          <h3 class="modal-title">📄 Nou document</h3>
          <button class="icon-btn" @click="showCreateModal = false">✕</button>
        </div>

        <div class="form-group">
          <label class="form-label">Títol del document</label>
          <input class="form-input" v-model="newDoc.title" placeholder="Protocol de PRL, Circular informativa..." />
        </div>
        <div class="form-group">
          <label class="form-label">Descripció breu</label>
          <input class="form-input" v-model="newDoc.description" placeholder="Descripció del document" />
        </div>
        <div class="form-group">
          <label class="form-label">Contingut del document</label>
          <div style="display:flex;gap:8px;margin-bottom:10px;">
            <button class="btn btn-sm" :class="newDoc.content_mode === 'text' ? 'btn-primary' : 'btn-outline'" @click="newDoc.content_mode = 'text'">📝 Text</button>
            <button class="btn btn-sm" :class="newDoc.content_mode === 'pdf' ? 'btn-primary' : 'btn-outline'" @click="newDoc.content_mode = 'pdf'">📄 Pujar PDF</button>
          </div>
          <!-- Text mode -->
          <textarea v-if="newDoc.content_mode === 'text'" class="form-textarea" v-model="newDoc.content" rows="8" placeholder="Escriviu o enganxeu el contingut del document aquí..." style="font-family:monospace;font-size:0.85rem;"></textarea>
          <!-- PDF upload mode -->
          <div v-else>
            <div v-if="!newDoc.pdf_data" style="border:2px dashed var(--color-border-light);border-radius:12px;padding:32px;text-align:center;cursor:pointer;transition:border-color 0.2s;" @click="$refs.pdfInput.click()" @dragover.prevent="onDragOver" @dragleave="onDragLeave" @drop.prevent="onFileDrop" :style="dragging ? 'border-color:var(--color-primary);background:rgba(59,130,246,0.04);' : ''">
              <div style="font-size:2.5rem;margin-bottom:8px;">📎</div>
              <div style="font-weight:600;font-size:0.9rem;margin-bottom:4px;">Arrossegueu un PDF aquí o feu clic per seleccionar</div>
              <div class="text-small text-muted">Mida màxima recomanada: 4 MB (limitació localStorage)</div>
            </div>
            <div v-else style="display:flex;align-items:center;gap:14px;padding:14px;border:1px solid var(--color-border-light);border-radius:10px;background:rgba(59,130,246,0.03);">
              <div style="font-size:2rem;">📄</div>
              <div style="flex:1;">
                <div style="font-weight:600;font-size:0.9rem;">{{ newDoc.file_name }}</div>
                <div class="text-small text-muted">{{ formatFileSize(newDoc.pdf_size) }} · PDF carregat correctament</div>
              </div>
              <button class="btn btn-outline btn-sm" @click="clearPdf" style="color:var(--color-danger);">✕ Treure</button>
            </div>
            <input ref="pdfInput" type="file" accept=".pdf,application/pdf" @change="onPdfSelected" style="display:none;" />
          </div>
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Nom del fitxer</label>
            <input class="form-input" v-model="newDoc.file_name" placeholder="document.pdf" />
          </div>
          <div class="form-group">
            <label class="form-label">Categoria</label>
            <select class="form-select" v-model="newDoc.category">
              <option value="prl">Prevenció Riscos Laborals</option>
              <option value="normativa">Normativa / Legal</option>
              <option value="laboral">Laboral</option>
              <option value="formacio">Formació</option>
              <option value="altres">Altres</option>
            </select>
          </div>
        </div>

        <!-- Target users -->
        <div class="form-group">
          <label class="form-label">Destinataris</label>
          <div style="display:flex;gap:12px;margin-bottom:8px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.85rem;">
              <input type="radio" v-model="newDoc.target_mode" value="all" /> Tots els treballadors
            </label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.85rem;">
              <input type="radio" v-model="newDoc.target_mode" value="selected" /> Selecció individual
            </label>
          </div>
          <div v-if="newDoc.target_mode === 'selected'" style="max-height:120px;overflow-y:auto;border:1px solid var(--color-border-light);border-radius:8px;padding:8px;">
            <label v-for="w in workers" :key="w.id" style="display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer;font-size:0.82rem;">
              <input type="checkbox" :value="w.id" v-model="newDoc.selected_users" style="width:14px;height:14px;" />
              {{ w.name }} ({{ w.email }})
            </label>
          </div>
        </div>

        <!-- Options -->
        <div style="display:flex;gap:24px;margin:16px 0;flex-wrap:wrap;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.85rem;">
            <input type="checkbox" v-model="newDoc.requires_signature" style="width:16px;height:16px;" />
            ✍️ Requereix firma electrònica
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.85rem;">
            <input type="checkbox" v-model="newDoc.is_urgent" style="width:16px;height:16px;" />
            🔴 Document urgent (bloqueja accés fins firma)
          </label>
        </div>
        <div v-if="newDoc.is_urgent && !newDoc.requires_signature" style="padding:8px 12px;background:rgba(239,68,68,0.1);border-radius:8px;font-size:0.8rem;color:var(--color-danger);margin-bottom:12px;">
          ⚠️ Un document urgent necessita firma obligatòria. S'ha activat automàticament.
        </div>

        <div class="modal-footer">
          <button class="btn btn-outline" @click="showCreateModal = false">Cancel·lar</button>
          <button class="btn btn-primary" @click="createDocument" :disabled="!newDoc.title || (!newDoc.content && !newDoc.pdf_data)">Publicar document</button>
        </div>
      </div>
    </div>

    <!-- Reminder toast -->
    <div v-if="reminderToast" style="position:fixed;bottom:24px;right:24px;z-index:9999;background:var(--color-success);color:#fff;padding:14px 24px;border-radius:12px;font-size:0.85rem;box-shadow:0 8px 32px rgba(0,0,0,0.3);animation:fadeIn 0.3s ease;">
      {{ reminderToast }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, watch } from 'vue'
import { db } from '../services/db'
import { auditLog } from '../services/audit'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
const rawDocuments = ref([])
const workers = ref([])
const onboardingProfiles = ref([])
const allOnboardingStatuses = ref([])
const signatureData = ref({}) // docId -> { pendingSigs, signedCount, viewedCount, pendingCount, signedPct, viewedPct }

const activeFilter = ref('all')
const expandedDocId = ref(null)
const reminderToast = ref('')
const showCreateModal = ref(false)
const showProfileForm = ref(false)
const editingProfile = ref(null)
const expandedProfileId = ref(null)

const profileForm = reactive({
  name: '',
  description: '',
  document_ids: [],
  doc_modes: {}, // docId -> 'signature' | 'view_only'
  is_default: false
})

async function fetchData() {
  try {
    rawDocuments.value = await db.getDocuments()
    const allUsers = await db.getUsers()
    workers.value = allUsers.filter(u => u.role === 'worker' && u.active)
    onboardingProfiles.value = await db.getOnboardingProfiles()
    allOnboardingStatuses.value = await db.getAllOnboardingStatuses()
    
    // Pre-calculate signature data for all documents
    const sigData = {}
    for (const d of rawDocuments.value) {
      const sigs = await db.getPendingSignatures(d.id)
      const signed = sigs.filter(s => s.signed_at).length
      const viewed = sigs.filter(s => s.viewed_at && !s.signed_at).length
      const pending = sigs.filter(s => !s.signed_at).length
      const total = sigs.length
      sigData[d.id] = {
        pendingSigs: sigs,
        signedCount: signed,
        viewedCount: viewed,
        pendingCount: pending,
        signedPct: total ? (signed / total * 100) : 0,
        viewedPct: total ? (viewed / total * 100) : 0
      }
    }
    signatureData.value = sigData
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)

const documents = computed(() => [...rawDocuments.value].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)))
const allDocs = computed(() => documents.value)
const workersWithOnboarding = computed(() => workers.value)

const filters = [
  { key: 'all', label: 'Tots' },
  { key: 'urgent', label: '🔴 Urgents' },
  { key: 'pending', label: '⏳ Pendents firma' },
  { key: 'completed', label: '✓ Completats' },
  { key: 'info', label: '📖 Només lectura' }
]

const filteredDocs = computed(() => {
  const docs = documents.value
  switch (activeFilter.value) {
    case 'urgent': return docs.filter(d => d.is_urgent)
    case 'pending': return docs.filter(d => d.requires_signature && getPendingCount(d.id) > 0)
    case 'completed': return docs.filter(d => !d.requires_signature || getPendingCount(d.id) === 0)
    case 'info': return docs.filter(d => !d.requires_signature)
    default: return docs
  }
})

const urgentCount = computed(() => documents.value.filter(d => d.is_urgent).length)
const completedCount = computed(() => documents.value.filter(d => !d.requires_signature || getPendingCount(d.id) === 0).length)
const totalPendingSignatures = computed(() => documents.value.filter(d => d.requires_signature).reduce((sum, d) => sum + getPendingCount(d.id), 0))

// New document form
const newDoc = reactive({
  title: '', description: '', content: '', file_name: '', category: 'laboral',
  requires_signature: false, is_urgent: false, target_mode: 'all', selected_users: [],
  content_mode: 'pdf', pdf_data: null, pdf_size: 0
})

watch(() => newDoc.is_urgent, (isUrgent) => {
  if (isUrgent) newDoc.requires_signature = true
})

const dragging = ref(false)
const pdfInput = ref(null)

function openCreateModal() {
  Object.assign(newDoc, { title: '', description: '', content: '', file_name: '', category: 'laboral', requires_signature: false, is_urgent: false, target_mode: 'all', selected_users: [], content_mode: 'pdf', pdf_data: null, pdf_size: 0 })
  showCreateModal.value = true
}

async function createDocument() {
  if (newDoc.is_urgent) newDoc.requires_signature = true
  try {
    const doc = await db.addDocument({
      title: newDoc.title,
      description: newDoc.description,
      content: newDoc.content || `[Document PDF: ${newDoc.file_name}]`,
      file_name: newDoc.file_name || `doc_${Date.now()}.pdf`,
      category: newDoc.category,
      requires_signature: newDoc.requires_signature,
      is_urgent: newDoc.is_urgent,
      pdf_data: newDoc.pdf_data || null,
      target_users: newDoc.target_mode === 'all' ? 'all' : [...newDoc.selected_users],
      created_by: authStore.userId
    })
    auditLog(authStore.userId, 'CREATE_DOCUMENT', 'document', doc.id, `Document creat: ${doc.title}${doc.is_urgent ? ' [URGENT]' : ''}${doc.pdf_data ? ' [PDF]' : ''}`)
    await fetchData()
    showCreateModal.value = false
  } catch (e) { console.error(e) }
}

// PDF handling
function onPdfSelected(e) {
  const file = e.target.files?.[0]
  if (file) handlePdfFile(file)
}

function onFileDrop(e) {
  dragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file && file.type === 'application/pdf') handlePdfFile(file)
}

function onDragOver() { dragging.value = true }
function onDragLeave() { dragging.value = false }

function handlePdfFile(file) {
  if (file.size > 4.5 * 1024 * 1024) {
    alert('El fitxer és massa gran. Mida màxima: 4 MB (limitació localStorage).')
    return
  }
  const reader = new FileReader()
  reader.onload = () => {
    newDoc.pdf_data = reader.result
    newDoc.pdf_size = file.size
    if (!newDoc.file_name) newDoc.file_name = file.name
    if (!newDoc.title) newDoc.title = file.name.replace(/\.pdf$/i, '').replace(/[_-]/g, ' ')
  }
  reader.readAsDataURL(file)
}

function clearPdf() {
  newDoc.pdf_data = null
  newDoc.pdf_size = 0
  if (pdfInput.value) pdfInput.value.value = ''
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function toggleDocDetail(id) {
  expandedDocId.value = expandedDocId.value === id ? null : id
}

// Signature tracking helpers (now from signatureData)
function getPendingSignatures(docId) { return signatureData.value[docId]?.pendingSigs || [] }
function getSignedCount(docId) { return signatureData.value[docId]?.signedCount || 0 }
function getViewedCount(docId) { return signatureData.value[docId]?.viewedCount || 0 }
function getPendingCount(docId) { return signatureData.value[docId]?.pendingCount || 0 }
function getSignedPct(docId) { return signatureData.value[docId]?.signedPct || 0 }
function getViewedPct(docId) { return signatureData.value[docId]?.viewedPct || 0 }

function categoryName(cat) {
  const map = { prl: 'PRL', normativa: 'Normativa', laboral: 'Laboral', formacio: 'Formació', altres: 'Altres' }
  return map[cat] || cat
}

function sendReminder(doc) {
  const pending = getPendingSignatures(doc.id).filter(s => !s.signed_at)
  pending.forEach(s => {
    console.log(`[EMAIL SIMULAT] Recordatori a ${s.email}: pendeix firmar "${doc.title}"`)
  })
  auditLog(authStore.userId, 'SEND_REMINDER', 'document', doc.id, `Recordatori enviat a ${pending.length} treballadors per: ${doc.title}`)
  showToast(`📧 Recordatori enviat a ${pending.length} treballadors`)
}

function sendIndividualReminder(doc, sig) {
  console.log(`[EMAIL SIMULAT] Recordatori individual a ${sig.email}: pendeix firmar "${doc.title}"`)
  auditLog(authStore.userId, 'SEND_REMINDER', 'document', doc.id, `Recordatori individual a ${sig.name} per: ${doc.title}`)
  showToast(`📧 Recordatori enviat a ${sig.name}`)
}

function showToast(msg) {
  reminderToast.value = msg
  setTimeout(() => { reminderToast.value = '' }, 3000)
}

function formatDate(d) { return new Date(d).toLocaleDateString('ca-ES', { day: '2-digit', month: 'short', year: 'numeric' }) }
function formatDateTime(d) { return new Date(d).toLocaleString('ca-ES', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) }

// Onboarding profile management
async function saveProfile() {
  try {
    if (editingProfile.value) {
      await db.updateOnboardingProfile({ ...editingProfile.value, name: profileForm.name, description: profileForm.description, document_ids: [...profileForm.document_ids], doc_modes: { ...profileForm.doc_modes }, is_default: profileForm.is_default })
      auditLog(authStore.userId, 'UPDATE_ONBOARDING_PROFILE', 'onboarding_profile', editingProfile.value.id, `Perfil actualitzat: ${profileForm.name}`)
    } else {
      const p = await db.addOnboardingProfile({ name: profileForm.name, description: profileForm.description, document_ids: [...profileForm.document_ids], doc_modes: { ...profileForm.doc_modes }, is_default: profileForm.is_default })
      auditLog(authStore.userId, 'CREATE_ONBOARDING_PROFILE', 'onboarding_profile', p.id, `Perfil creat: ${p.name}`)
    }
    await fetchData()
    cancelProfileEdit()
  } catch (e) { console.error(e) }
}

function editProfile(p) {
  editingProfile.value = p
  Object.assign(profileForm, { name: p.name, description: p.description, document_ids: [...p.document_ids], doc_modes: { ...(p.doc_modes || {}) }, is_default: p.is_default })
  showProfileForm.value = true
}

function cancelProfileEdit() {
  showProfileForm.value = false; editingProfile.value = null
  Object.assign(profileForm, { name: '', description: '', document_ids: [], doc_modes: {}, is_default: false })
}

function toggleOnboardingStatus(profileId) {
  expandedProfileId.value = expandedProfileId.value === profileId ? null : profileId
}

function getWorkerOnboardingStatus(userId) {
  return allOnboardingStatuses.value.find(s => s.user_id === userId) || null
}

function getWorkerProgress(userId) {
  const status = getWorkerOnboardingStatus(userId)
  if (!status || !status.steps.length) return 0
  const signed = status.steps.filter(s => s.signed_at).length
  return Math.round((signed / status.steps.length) * 100)
}

async function assignProfile(userId, profileId) {
  const pid = profileId ? Number(profileId) : null
  const user = workers.value.find(u => u.id === userId)
  if (user) {
    try {
      await db.updateUser({ ...user, onboarding_profile_id: pid, onboarding_completed: pid ? false : true })
      if (pid) {
        await db.initOnboardingStatus(userId, pid)
        const profile = await db.getOnboardingProfile(pid)
        auditLog(authStore.userId, 'ASSIGN_ONBOARDING', 'user', userId, `Perfil d'onboarding assignat: ${profile?.name} a ${user.name}`)
      }
      await fetchData()
    } catch (e) { console.error(e) }
  }
}
</script>
