<template>
  <div class="ic">
    <div v-if="incidencia.missatges?.length" class="ic-fil">
      <div v-for="m in incidencia.missatges" :key="m.id" class="ic-msg" :class="{ meu: m.user_id === jo, rrhh: m.user_id !== incidencia.user_id }">
        <div class="ic-autor">
          {{ m.user_id === incidencia.user_id ? (m.user?.name || 'Treballador/a') : `${m.user?.name || 'RRHH'} · RRHH` }}
          <span class="ic-data">{{ dataHora(m.created_at) }}</span>
        </div>
        <div class="ic-text">{{ m.text }}</div>
      </div>
    </div>
    <div v-if="potEscriure" class="ic-resposta">
      <textarea v-model="text" class="form-input" rows="2" maxlength="5000" :placeholder="placeholder"
        @keydown.ctrl.enter="envia" @keydown.meta.enter="envia"></textarea>
      <button class="btn btn-primary btn-sm" :disabled="text.trim().length < 2 || enviant" @click="envia">
        {{ enviant ? 'Enviant…' : 'Enviar' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '../services/apiClient'
import { avisa } from '../utils/avisos'
import { useAuthStore } from '../stores/auth'

const props = defineProps({
  incidencia: { type: Object, required: true },
  potEscriure: { type: Boolean, default: true },
  placeholder: { type: String, default: 'Escriu una resposta…' },
})
const emit = defineEmits(['actualitzada'])
const jo = useAuthStore().userId
const text = ref('')
const enviant = ref(false)

// Dates del servidor (en UTC): es mostren en hora local del navegador.
function dataHora(d) {
  return d ? new Date(d).toLocaleString('ca-ES', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : ''
}

async function envia() {
  if (text.value.trim().length < 2 || enviant.value) return
  enviant.value = true
  try {
    const nova = await api.post(`/v1/incidencies/${props.incidencia.id}/missatges`, { text: text.value.trim() })
    text.value = ''
    emit('actualitzada', nova)
  } catch (e) {
    avisa(e?.message || "No s'ha pogut enviar.", 'error')
  } finally {
    enviant.value = false
  }
}
</script>

<style scoped>
.ic-fil { display: flex; flex-direction: column; gap: 8px; margin: 10px 0; }
.ic-msg { max-width: 85%; padding: 8px 12px; border-radius: 12px; background: var(--color-bg); align-self: flex-start; }
.ic-msg.rrhh { background: var(--color-primary-bg, #e8f0f8); }
.ic-msg.meu { align-self: flex-end; }
.ic-autor { font-size: 0.75rem; font-weight: 700; color: var(--color-text-secondary); margin-bottom: 2px; }
.ic-data { font-weight: 400; margin-left: 6px; }
.ic-text { white-space: pre-wrap; font-size: 0.92rem; line-height: 1.45; color: var(--color-text); }
.ic-resposta { display: flex; gap: 8px; align-items: flex-end; margin-top: 8px; }
.ic-resposta textarea { flex: 1; min-height: 56px; resize: vertical; }
</style>
