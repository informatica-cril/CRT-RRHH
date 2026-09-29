<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">📄 Els meus documents</h1>
        <p class="page-subtitle">Documents assignats — lectura i firma electrònica</p>
      </div>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:24px;">
      <div class="stat-card" style="padding:14px;">
        <div class="text-small" style="font-weight:600;color:var(--color-text-secondary);">Pendents firma</div>
        <div style="font-size:1.5rem;font-weight:700;color:var(--color-warning);margin-top:4px;">{{ pendingSignCount }}</div>
      </div>
      <div class="stat-card" style="padding:14px;">
        <div class="text-small" style="font-weight:600;color:var(--color-text-secondary);">Pendents lectura</div>
        <div style="font-size:1.5rem;font-weight:700;color:var(--color-danger);margin-top:4px;">{{ unreadCount }}</div>
      </div>
      <div class="stat-card" style="padding:14px;">
        <div class="text-small" style="font-weight:600;color:var(--color-text-secondary);">Completats</div>
        <div style="font-size:1.5rem;font-weight:700;color:var(--color-success);margin-top:4px;">{{ completedCount }}</div>
      </div>
    </div>

    <!-- Document list -->
    <div v-for="doc in myDocuments" :key="doc.id" class="card" style="margin-bottom:14px;cursor:pointer;"
      :style="doc.is_urgent && !getMySignature(doc.id)?.signed_at ? 'border-left:4px solid var(--color-danger);' : ''"
      @click="openDocument(doc)">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <span v-if="doc.is_urgent && !getMySignature(doc.id)?.signed_at" class="badge badge-danger" style="font-size:0.65rem;">🔴 URGENT</span>
            <span v-if="doc.requires_signature" class="badge badge-warning" style="font-size:0.65rem;">✍️ Firma</span>
            <span class="badge badge-info" style="font-size:0.6rem;">{{ categoryName(doc.category) }}</span>
          </div>
          <h3 style="font-size:0.95rem;font-weight:600;">{{ doc.title }}</h3>
          <p class="text-small text-muted">{{ doc.description }}</p>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <span v-if="getMySignature(doc.id)?.signed_at" class="badge badge-success">✓ Firmat</span>
          <span v-else-if="getMySignature(doc.id)?.viewed_at" class="badge badge-warning">👁 Vist</span>
          <span v-else class="badge badge-danger">Nou</span>
        </div>
      </div>
    </div>

    <div v-if="myDocuments.length === 0" class="card">
      <div class="empty-state"><p>No hi ha documents assignats</p></div>
    </div>

    <!-- Document Viewer / Signer Modal -->
    <div v-if="viewingDoc" class="modal-overlay">
      <div class="modal" style="max-width:750px;max-height:90vh;display:flex;flex-direction:column;">
        <div class="modal-header">
          <div>
            <h3 class="modal-title">{{ viewingDoc.title }}</h3>
            <div class="text-small text-muted">📎 {{ viewingDoc.file_name }} · {{ formatDate(viewingDoc.created_at) }}</div>
          </div>
          <button class="icon-btn" @click="viewingDoc = null">✕</button>
        </div>

        <!-- Document content: PDF or text -->
        <iframe v-if="viewingDoc.pdf_data" :src="viewingDoc.pdf_data" style="flex:1;border:none;border-radius:8px;margin:12px 0;min-height:400px;"></iframe>
        <div v-else style="flex:1;overflow-y:auto;padding:20px;background:var(--color-bg);border-radius:8px;margin:12px 0;font-size:0.88rem;line-height:1.7;white-space:pre-wrap;font-family:'Segoe UI',system-ui,sans-serif;">{{ viewingDoc.content }}</div>

        <!-- Signature section -->
        <div v-if="viewingDoc.requires_signature && !currentSig?.signed_at" style="border-top:2px solid var(--color-primary);padding-top:16px;">
          <div style="background:rgba(59,130,246,0.06);padding:14px;border-radius:10px;margin-bottom:14px;">
            <div style="font-weight:600;font-size:0.9rem;margin-bottom:8px;">✍️ Firma Electrònica Avançada (eIDAS)</div>
            <div class="text-small text-muted" style="line-height:1.6;">
              En signar, es genera un paquet criptogràfic que inclou:<br>
              • Hash SHA-256 del document (integritat)<br>
              • Identitat del signant (autenticitat)<br>
              • Sello de temps independent RFC 3161 (no-repudi)<br>
              • Conforme al Reglament (UE) 910/2014 Art. 26 i ENS RD 311/2022
            </div>
          </div>

          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin-bottom:16px;">
            <input type="checkbox" v-model="acceptedContent" style="width:18px;height:18px;margin-top:2px;flex-shrink:0;" />
            <span style="font-size:0.85rem;line-height:1.5;">
              <strong>Declaro</strong> que he llegit íntegrament el contingut d'aquest document i n'accepto els termes.
              Entenc que la meva firma electrònica té validesa legal conforme a la normativa europea (eIDAS).
            </span>
          </label>

          <div style="display:flex;gap:8px;align-items:center;">
            <button class="btn btn-primary" @click="signDocument" :disabled="!acceptedContent || signing" style="min-width:200px;">
              {{ signing ? '⏳ Signant...' : '✍️ Firmar document' }}
            </button>
            <span v-if="signing" class="text-small text-muted">Generant hash i sol·licitant sello de temps...</span>
          </div>
        </div>

        <!-- Already signed -->
        <div v-if="currentSig?.signed_at" style="border-top:2px solid var(--color-success);padding-top:16px;">
          <div style="background:rgba(76,175,80,0.06);padding:16px;border-radius:10px;">
            <div style="font-weight:700;font-size:0.95rem;color:var(--color-success);margin-bottom:10px;">✓ Document firmat correctament</div>
            <div style="display:grid;grid-template-columns:auto 1fr;gap:6px 12px;font-size:0.8rem;">
              <span class="text-muted">Data firma:</span>
              <span>{{ formatDateTime(currentSig.signed_at) }}</span>
              <span class="text-muted">Signant:</span>
              <span>{{ authStore.userName }} ({{ authStore.userEmail }})</span>
              <span class="text-muted">Hash document:</span>
              <code style="font-size:0.7rem;word-break:break-all;color:var(--color-primary);">{{ currentSig.document_hash }}</code>
              <span class="text-muted">Hash firma:</span>
              <code style="font-size:0.7rem;word-break:break-all;color:var(--color-accent);">{{ currentSig.signature_hash }}</code>
              <span class="text-muted">Sello temps:</span>
              <span style="font-size:0.75rem;">{{ currentSig.timestamp_source }}</span>
            </div>
          </div>
          <div class="modal-footer" style="margin-top:12px;">
            <button class="btn btn-outline" @click="viewingDoc = null">Tancar</button>
          </div>
        </div>

        <!-- Not signable, just close -->
        <div v-if="!viewingDoc.requires_signature" class="modal-footer">
          <button class="btn btn-outline" @click="viewingDoc = null">Tancar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { db } from '../services/db'
import { auditLog } from '../services/audit'
import { useAuthStore } from '../stores/auth'
import { createSignaturePackage } from '../services/signatureService'

const authStore = useAuthStore()
const viewingDoc = ref(null)
const acceptedContent = ref(false)
const signing = ref(false)
const currentSig = ref(null)
const documents = ref([])
const signatures = ref({}) // docId -> signature

async function fetchData() {
  try {
    const rawDocs = await db.getDocumentsForUser(authStore.userId)
    const rawSigs = await db.getDocSignaturesByUser(authStore.userId)
    
    // Map signatures by docId
    const sigMap = {}
    rawSigs.forEach(s => { sigMap[s.document_id] = s })
    signatures.value = sigMap
    
    // Sort documents: Urgent unsigned first, then by date
    documents.value = rawDocs.sort((a, b) => {
      const aUrgent = a.is_urgent && !signatures.value[a.id]?.signed_at ? 1 : 0
      const bUrgent = b.is_urgent && !signatures.value[b.id]?.signed_at ? 1 : 0
      if (aUrgent !== bUrgent) return bUrgent - aUrgent
      return new Date(b.created_at) - new Date(a.created_at)
    })
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)

const myDocuments = computed(() => documents.value)

const pendingSignCount = computed(() => documents.value.filter(d => d.requires_signature && !signatures.value[d.id]?.signed_at).length)
const unreadCount = computed(() => documents.value.filter(d => !signatures.value[d.id]?.viewed_at).length)
const completedCount = computed(() => documents.value.filter(d => !d.requires_signature || signatures.value[d.id]?.signed_at).length)

function getMySignature(docId) {
  return signatures.value[docId] || null
}

async function openDocument(doc) {
  viewingDoc.value = doc
  acceptedContent.value = false
  signing.value = false

  try {
    // Record view if not already viewed
    let sig = await db.getDocSignature(doc.id, authStore.userId)
    if (!sig) {
      sig = await db.addDocSignature({
        document_id: doc.id,
        user_id: authStore.userId,
        viewed_at: new Date().toISOString(),
        signed_at: null,
        document_hash: null,
        signature_hash: null,
        timestamp_source: null,
        timestamp_token: null,
        ip_address: '127.0.0.1',
        user_agent: navigator.userAgent
      })
      auditLog(authStore.userId, 'VIEW_DOCUMENT', 'document', doc.id, `Document visualitzat: ${doc.title}`)
    } else if (!sig.viewed_at) {
      sig = await db.updateDocSignature({ ...sig, viewed_at: new Date().toISOString() })
      auditLog(authStore.userId, 'VIEW_DOCUMENT', 'document', doc.id, `Document visualitzat: ${doc.title}`)
    }
    await fetchData() // Refresh all data and sigs
    currentSig.value = sig
  } catch (e) {
    console.error(e)
  }
}

async function signDocument() {
  if (!viewingDoc.value || !acceptedContent.value) return
  signing.value = true

  try {
    const sigPackage = await createSignaturePackage({
      documentId: viewingDoc.value.id,
      documentContent: viewingDoc.value.content,
      userId: authStore.userId,
      userName: authStore.userName,
      userEmail: authStore.userEmail
    })

    const existingSig = await db.getDocSignature(viewingDoc.value.id, authStore.userId)
    if (existingSig) {
      await db.updateDocSignature({
        ...existingSig,
        signed_at: sigPackage.signed_at,
        document_hash: sigPackage.document_hash,
        signature_hash: sigPackage.signature_hash,
        timestamp_source: sigPackage.timestamp.source,
        timestamp_token: sigPackage.timestamp.token || sigPackage.timestamp.bindingHash,
        signing_payload: sigPackage.signing_payload,
        legal_basis: sigPackage.legal_basis,
        ip_address: sigPackage.signer.ip_address,
        user_agent: sigPackage.signer.user_agent
      })
    }

    auditLog(authStore.userId, 'SIGN_DOCUMENT', 'document', viewingDoc.value.id,
      `Firma electrònica avançada: ${viewingDoc.value.title} | Hash: ${sigPackage.signature_hash.substring(0, 16)}... | TSA: ${sigPackage.timestamp.source}`)

    await fetchData()
    currentSig.value = signatures.value[viewingDoc.value.id]
  } catch (err) {
    console.error('[SignatureService] Error:', err)
    alert('Error en el procés de firma. Torneu-ho a provar.')
  } finally {
    signing.value = false
  }
}

function categoryName(cat) {
  const map = { prl: 'PRL', normativa: 'Normativa', laboral: 'Laboral', formacio: 'Formació', altres: 'Altres' }
  return map[cat] || cat
}

function formatDate(d) { return new Date(d).toLocaleDateString('ca-ES', { day: '2-digit', month: 'short', year: 'numeric' }) }
function formatDateTime(d) { return new Date(d).toLocaleString('ca-ES', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }) }
</script>
