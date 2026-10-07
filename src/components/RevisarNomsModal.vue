<template>
  <div class="modal-overlay" @click.self="!desant && $emit('close')">
    <div class="modal" style="max-width:640px;max-height:90vh;display:flex;flex-direction:column;">
      <div class="modal-header">
        <h3 class="modal-title">🔤 Revisar l'ordre dels noms</h3>
        <button class="icon-btn" :disabled="desant" @click="$emit('close')">✕</button>
      </div>
      <p class="text-small text-muted" style="margin-top:0;">
        Noms que semblen desats amb els cognoms davant. Revisa la proposta (la pots editar),
        desmarca els que ja estiguin bé i prem <strong>Corregir</strong>.
      </p>

      <div v-if="!files.length" class="empty-state"><p>✓ No n'hi ha cap que sembli girat.</p></div>
      <div v-else style="overflow-y:auto;">
        <div class="rn-cap">
          <label><input type="checkbox" :checked="totsMarcats" @change="marcaTots($event.target.checked)" /> Tots</label>
          <span class="text-small text-muted">{{ marcats.length }} de {{ files.length }} marcats</span>
        </div>
        <div v-for="f in files" :key="f.id" class="rn-fila">
          <input type="checkbox" v-model="f.marcat" />
          <span class="rn-actual">{{ f.actual }}</span>
          <span class="text-muted">→</span>
          <input v-model="f.nou" class="form-input rn-nou" />
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline" :disabled="desant" @click="$emit('close')">Tancar</button>
        <button v-if="files.length" class="btn btn-primary" :disabled="desant || !marcats.length" @click="corregeix">
          {{ desant ? `Desant… (${fets}/${marcats.length})` : `Corregir ${marcats.length}` }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { db } from '../services/db'
import { auditLog } from '../services/audit'
import { useAuthStore } from '../stores/auth'
import { proposaOrdre } from '../utils/ordreNom'
import { avisa } from '../utils/avisos'

const props = defineProps({ persones: { type: Array, default: () => [] } })
const emit = defineEmits(['close', 'fet'])
const authStore = useAuthStore()

const files = ref(props.persones
  .map(p => ({ id: p.id, actual: p.name, nou: proposaOrdre(p.name), marcat: true }))
  .filter(f => f.nou && f.nou !== f.actual)
  .sort((a, b) => a.actual.localeCompare(b.actual, 'ca')))
const marcats = computed(() => files.value.filter(f => f.marcat && f.nou.trim()))
const totsMarcats = computed(() => files.value.length > 0 && files.value.every(f => f.marcat))
const desant = ref(false)
const fets = ref(0)

function marcaTots(v) { files.value.forEach(f => { f.marcat = v }) }

async function corregeix() {
  desant.value = true
  fets.value = 0
  let errors = 0
  for (const f of marcats.value) {
    try {
      await db.updateUser({ id: f.id, name: f.nou.trim() })
      auditLog(authStore.userId, 'UPDATE_USER', 'user', f.id, `Ordre del nom: «${f.actual}» → «${f.nou.trim()}»`)
      fets.value++
    } catch (e) {
      errors++
    }
  }
  desant.value = false
  avisa(errors ? `S'han corregit ${fets.value} noms; ${errors} no s'han pogut desar.` : `S'han corregit ${fets.value} noms.`, errors ? 'error' : 'ok')
  emit('fet')
  emit('close')
}
</script>

<style scoped>
.rn-cap { display: flex; justify-content: space-between; align-items: center; padding: 6px 2px; border-bottom: 1px solid var(--color-border-light, #eee); }
.rn-fila { display: grid; grid-template-columns: 24px 1fr auto 1fr; gap: 8px; align-items: center; padding: 6px 2px; border-bottom: 1px solid var(--color-border-light, #f1f1f1); font-size: 0.88rem; }
.rn-actual { color: var(--color-text-secondary, #6b7280); }
.rn-nou { padding: 4px 8px; }
</style>
