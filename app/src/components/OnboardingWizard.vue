<template>
  <div class="onboarding-overlay">
    <div class="onboarding-container">
      <!-- Header -->
      <div class="onboarding-header">
        <div style="display:flex;align-items:center;gap:12px;">
          <div style="width:48px;height:48px;background:linear-gradient(135deg,var(--color-primary),var(--color-accent));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;">📋</div>
          <div>
            <h2 style="font-size:1.2rem;font-weight:700;margin-bottom:2px;">Onboarding — {{ profileName }}</h2>
            <div class="text-small text-muted">Llegiu i signeu cada document per completar la vostra incorporació</div>
          </div>
        </div>
      </div>

      <!-- Progress bar -->
      <div v-if="!allDone && totalSteps > 0" class="onboarding-progress">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <span style="font-size:0.8rem;font-weight:600;color:var(--color-text-secondary);">Pas {{ currentStep + 1 }} de {{ totalSteps }}</span>
          <span style="font-size:0.8rem;color:var(--color-primary);font-weight:600;">{{ Math.round(progressPct) }}%</span>
        </div>
        <div style="height:10px;background:var(--color-bg);border-radius:6px;overflow:hidden;position:relative;">
          <div :style="{ width: progressPct + '%', background: 'linear-gradient(90deg,var(--color-primary),var(--color-accent))', height: '100%', borderRadius: '6px', transition: 'width 0.4s ease' }"></div>
        </div>
        <!-- Step indicators -->
        <div style="display:flex;gap:8px;margin-top:12px;justify-content:center;">
          <div v-for="(step, i) in steps" :key="i"
            :style="{
              width: '32px', height: '32px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: '0.8rem', fontWeight: '700', transition: 'all 0.3s',
              background: step.signed_at ? 'var(--color-success)' : i === currentStep ? 'var(--color-primary)' : 'var(--color-bg)',
              color: step.signed_at || i === currentStep ? '#fff' : 'var(--color-text-secondary)',
              border: i === currentStep && !step.signed_at ? '2px solid var(--color-primary)' : '2px solid transparent'
            }">
            <span v-if="step.signed_at">✓</span>
            <span v-else-if="getDocMode(step.document_id) === 'view_only'">👁️</span>
            <span v-else>{{ i + 1 }}</span>
          </div>
        </div>
      </div>

      <!-- Completed state -->
      <div v-if="allDone && totalSteps > 0" style="flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;padding:40px;text-align:center;">
        <div style="font-size:4rem;margin-bottom:16px;">🎉</div>
        <h2 style="font-size:1.5rem;font-weight:700;margin-bottom:8px;color:var(--color-success);">Onboarding completat!</h2>
        <p style="color:var(--color-text-secondary);max-width:400px;line-height:1.6;margin-bottom:24px;">
          Heu llegit i signat tots els documents requerits. Ara podeu accedir a tots els serveis de la plataforma.
        </p>
        <button class="btn btn-primary" style="padding:12px 40px;font-size:1rem;" @click="$emit('completed')">
          🚀 Començar a treballar
        </button>
      </div>

      <!-- No steps state
      <div v-else-if="totalSteps === 0" style="flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;padding:40px;text-align:center;">
        <div style="font-size:3rem;margin-bottom:16px;">ℹ️</div>
        <h3 style="font-size:1.2rem;font-weight:600;margin-bottom:8px;">{{ profileName }}</h3>
        <p style="color:var(--color-text-secondary);max-width:400px;line-height:1.6;margin-bottom:24px;white-space:pre-wrap;">
          {{ profileDescription || "Aquest perfil no té cap document addicional." }}
        </p>
        <button class="btn btn-primary" @click="$emit('completed')">Continuar al panel</button>
      </div> -->

      <!-- Document step -->
      <div v-else style="flex:1;display:flex;flex-direction:column;overflow:hidden;">
        <!-- Document title -->
        <div style="padding:12px 24px;background:rgba(59,130,246,0.04);border-bottom:1px solid var(--color-border-light);">
          <h3 style="font-size:1rem;font-weight:700;">{{ currentDoc?.title }}</h3>
          <div class="text-small text-muted">{{ currentDoc?.description }} · 📎 {{ currentDoc?.file_name }}</div>
        </div>

        <!-- Document content -->
        <div style="flex:1;overflow:hidden;padding:0 24px;">
          <iframe v-if="currentDoc?.pdf_data" :src="currentDoc.pdf_data" style="width:100%;height:100%;border:none;border-radius:8px;margin:12px 0;"></iframe>
          <div v-else style="overflow-y:auto;height:100%;padding:20px;background:var(--color-bg);border-radius:8px;margin:12px 0;font-size:0.88rem;line-height:1.7;font-family:'Segoe UI',system-ui,sans-serif;">
            <p v-if="currentDoc?.description" style="font-weight:600;margin-bottom:1em;">{{ currentDoc.description }}</p>
            <div style="white-space:pre-wrap;">{{ currentDoc?.content }}</div>
          </div>
        </div>

        <!-- Signature / View section -->
        <div style="padding:16px 24px;border-top:2px solid var(--color-primary);background:var(--color-card-bg);">
          <div v-if="!currentStepData?.signed_at">
            <!-- VIEW ONLY mode -->
            <div v-if="currentDocMode === 'view_only'">
              <div style="background:rgba(59,130,246,0.05);padding:10px 14px;border-radius:8px;margin-bottom:12px;">
                <div style="font-weight:600;font-size:0.82rem;">👁️ Document de lectura obligatòria</div>
                <div class="text-small text-muted">Llegiu el document complet i confirmeu que l'heu visionat.</div>
              </div>
              <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin-bottom:14px;">
                <input type="checkbox" v-model="accepted" style="width:18px;height:18px;margin-top:2px;flex-shrink:0;" />
                <span style="font-size:0.85rem;line-height:1.5;">
                  <strong>Confirmo</strong> que he llegit íntegrament el contingut d'aquest document.
                </span>
              </label>
              <button class="btn btn-primary" style="width:100%;padding:12px;" @click="confirmViewOnly" :disabled="!accepted">
                👁️ Confirmar lectura i continuar
              </button>
            </div>
            <!-- SIGNATURE mode -->
            <div v-else>
              <div style="background:rgba(59,130,246,0.05);padding:10px 14px;border-radius:8px;margin-bottom:12px;">
                <div style="font-weight:600;font-size:0.82rem;">✍️ Firma Electrònica Avançada (eIDAS Art. 26)</div>
                <div class="text-small text-muted">Hash SHA-256 · Sello RFC 3161 · Reg. (UE) 910/2014</div>
              </div>
              <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin-bottom:14px;">
                <input type="checkbox" v-model="accepted" style="width:18px;height:18px;margin-top:2px;flex-shrink:0;" />
                <span style="font-size:0.85rem;line-height:1.5;">
                  <strong>Declaro</strong> que he llegit íntegrament el contingut d'aquest document i n'accepto els termes.
                </span>
              </label>
              <button class="btn btn-primary" style="width:100%;padding:12px;" @click="signCurrentStep" :disabled="!accepted || signing">
                {{ signing ? '⏳ Signant...' : '✍️ Firmar i continuar' }}
              </button>
            </div>
          </div>
          <div v-else style="display:flex;justify-content:space-between;align-items:center;">
            <span style="color:var(--color-success);font-weight:600;">
              {{ getDocMode(currentStepData.document_id) === 'view_only' ? '✓ Document visionat' : '✓ Document firmat' }}
            </span>
            <button class="btn btn-primary" @click="nextStep">Següent →</button>
          </div>
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

const emit = defineEmits(['completed'])
const authStore = useAuthStore()
const accepted = ref(false)
const signing = ref(false)
const currentStep = ref(0)
const onboardingStatus = ref(null)
const allDone = ref(false)

const profile = ref(null)
const currentDoc = ref(null)

async function fetchOnboardingData() {
  if (!authStore.userId) return
  
  try {
    const user = await db.getUser(authStore.userId)
    if (user?.onboarding_profile_id) {
      profile.value = await db.getOnboardingProfile(user.onboarding_profile_id)
    }
    
    let status = await db.getOnboardingStatus(authStore.userId)
    if (!status && profile.value) {
      status = await db.initOnboardingStatus(authStore.userId, profile.value.id)
      auditLog(authStore.userId, 'START_ONBOARDING', 'onboarding', profile.value.id, `Inici onboarding: ${profile.value.name}`)
    }
    
    onboardingStatus.value = status
    if (status) {
      // Defensive fallback if API somehow omitted the steps array
      if (!status.steps) {
        status.steps = (profile.value?.document_ids || []).map(id => ({
          document_id: id,
          viewed_at: null,
          signed_at: null
        }))
      }
      
      const firstUnsigned = status.steps.findIndex(s => !s.signed_at)
      currentStep.value = firstUnsigned >= 0 ? firstUnsigned : status.steps.length
      if (firstUnsigned < 0) allDone.value = true
      await updateCurrentDoc()
      await markViewed()
    }
  } catch (err) {
    console.error('[Onboarding] Fetch error:', err)
  }
}

async function updateCurrentDoc() {
  const step = steps.value[currentStep.value]
  if (step) {
    currentDoc.value = await db.getDocument(step.document_id)
  } else {
    currentDoc.value = null
  }
}

const profileName = computed(() => profile.value?.name || 'Onboarding')
const profileDescription = computed(() => profile.value?.description || '')
const steps = computed(() => onboardingStatus.value?.steps || [])
const totalSteps = computed(() => steps.value.length)
const progressPct = computed(() => {
  if (!totalSteps.value) return 0
  const signed = steps.value.filter(s => s.signed_at).length
  return (signed / totalSteps.value) * 100
})
const currentStepData = computed(() => steps.value[currentStep.value] || null)

// Determine if current doc requires signature or just viewing
function getDocMode(docId) {
  return profile.value?.doc_modes?.[docId] || 'signature'
}
const currentDocMode = computed(() => {
  const step = steps.value[currentStep.value]
  if (!step) return 'signature'
  return getDocMode(step.document_id)
})

onMounted(async () => {
  await fetchOnboardingData()
})

async function markViewed() {
  if (!onboardingStatus.value || allDone.value) return
  const step = onboardingStatus.value.steps[currentStep.value]
  if (step && !step.viewed_at) {
    step.viewed_at = new Date().toISOString()
    onboardingStatus.value.current_step = currentStep.value
    await db.updateOnboardingStatus(onboardingStatus.value, authStore.userId)
    // Also register doc signature view
    let sig = await db.getDocSignature(step.document_id, authStore.userId)
    if (!sig) {
      await db.addDocSignature({
        document_id: step.document_id, user_id: authStore.userId,
        viewed_at: step.viewed_at, signed_at: null,
        document_hash: null, signature_hash: null,
        timestamp_source: null, timestamp_token: null,
        ip_address: '127.0.0.1', user_agent: navigator.userAgent
      })
    }
  }
}

async function signCurrentStep() {
  if (!currentDoc.value || !accepted.value) return
  signing.value = true

  try {
    const sigPackage = await createSignaturePackage({
      documentId: currentDoc.value.id,
      documentContent: currentDoc.value.content,
      userId: authStore.userId,
      userName: authStore.userName,
      userEmail: authStore.userEmail
    })

    // Update onboarding step
    const step = onboardingStatus.value.steps[currentStep.value]
    step.signed_at = sigPackage.signed_at
    await db.updateOnboardingStatus(onboardingStatus.value, authStore.userId)

    // Update document_signatures
    const existingSig = await db.getDocSignature(currentDoc.value.id, authStore.userId)
    const sigData = {
      signed_at: sigPackage.signed_at,
      document_hash: sigPackage.document_hash,
      signature_hash: sigPackage.signature_hash,
      timestamp_source: sigPackage.timestamp.source,
      timestamp_token: sigPackage.timestamp.token || sigPackage.timestamp.bindingHash,
      signing_payload: sigPackage.signing_payload,
      legal_basis: sigPackage.legal_basis,
      ip_address: sigPackage.signer.ip_address,
      user_agent: sigPackage.signer.user_agent
    }
    if (existingSig) {
      await db.updateDocSignature({ ...existingSig, ...sigData })
    } else {
      await db.addDocSignature({
        document_id: currentDoc.value.id, user_id: authStore.userId,
        viewed_at: new Date().toISOString(),
        ...sigData
      })
    }

    auditLog(authStore.userId, 'ONBOARDING_SIGN', 'document', currentDoc.value.id,
      `Onboarding pas ${currentStep.value + 1}: ${currentDoc.value.title} | Hash: ${sigPackage.signature_hash.substring(0, 16)}...`)

    // Refresh status
    onboardingStatus.value = await db.getOnboardingStatus(authStore.userId)
  } catch (err) {
    console.error('[Onboarding] Sign error:', err)
    alert('Error en la firma. Torneu-ho a provar.')
  } finally {
    signing.value = false
  }
}

async function confirmViewOnly() {
  if (!currentDoc.value || !accepted.value) return
  const now = new Date().toISOString()
  const step = onboardingStatus.value.steps[currentStep.value]
  step.signed_at = now
  step.mode = 'view_only'
  await db.updateOnboardingStatus(onboardingStatus.value, authStore.userId)

  // Mark the signature record as completed so this doc won't appear as an unsigned urgent doc
  const existingSig = await db.getDocSignature(currentDoc.value.id, authStore.userId)
  if (existingSig) {
    await db.updateDocSignature({
      ...existingSig,
      viewed_at: existingSig.viewed_at || now,
      signed_at: now
    })
  } else {
    await db.addDocSignature({
      document_id: currentDoc.value.id, user_id: authStore.userId,
      viewed_at: now, signed_at: now,
      document_hash: null, signature_hash: null,
      timestamp_source: null, timestamp_token: null,
      ip_address: '127.0.0.1', user_agent: navigator.userAgent
    })
  }

  auditLog(authStore.userId, 'ONBOARDING_VIEW', 'document', currentDoc.value.id,
    `Onboarding pas ${currentStep.value + 1} (lectura): ${currentDoc.value.title}`)

  onboardingStatus.value = await db.getOnboardingStatus(authStore.userId)
  await nextStep()
}

async function nextStep() {
  accepted.value = false
  if (currentStep.value + 1 >= totalSteps.value) {
    // All done!
    await db.completeOnboarding(authStore.userId)
    auditLog(authStore.userId, 'COMPLETE_ONBOARDING', 'onboarding', profile.value?.id, `Onboarding completat: ${profile.value?.name}`)
    allDone.value = true
    // Refresh user in auth store
    authStore.user = await db.getUser(authStore.userId)
  } else {
    currentStep.value++
    await updateCurrentDoc()
    await markViewed()
  }
}
</script>

<style scoped>
.onboarding-overlay {
  position: fixed; inset: 0; z-index: 100000;
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(8px);
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
}

.onboarding-container {
  background: var(--color-card-bg, #fff);
  border-radius: 16px;
  width: 100%; max-width: 800px;
  height: 90vh; max-height: 850px;
  display: flex; flex-direction: column;
  overflow: hidden;
  box-shadow: 0 24px 80px rgba(0,0,0,0.4);
}

.onboarding-header {
  padding: 20px 24px;
  border-bottom: 1px solid var(--color-border-light);
}

.onboarding-progress {
  padding: 16px 24px;
  border-bottom: 1px solid var(--color-border-light);
}
</style>
