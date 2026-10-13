<template>
  <div class="modal-overlay" @click.self="!desant && $emit('close')">
    <div class="modal" style="max-width:760px;max-height:90vh;display:flex;flex-direction:column;">
      <div class="modal-header">
        <h3 class="modal-title">📮 Codis postals en bloc</h3>
        <button class="icon-btn" :disabled="desant" @click="$emit('close')">✕</button>
      </div>

      <template v-if="!files.length">
        <p class="text-small text-muted" style="margin-top:0;">
          Enganxa una persona per línia amb els seus codis postals, per exemple
          <em>«Mercè Tous 08003-08010 (Born)»</em>. Després podràs revisar a qui s'assigna cada línia.
        </p>
        <textarea v-model="text" class="form-input" rows="12" placeholder="Nom Cognom 08004-08014 (Sants)"></textarea>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="$emit('close')">Tancar</button>
          <button class="btn btn-primary" :disabled="!text.trim()" @click="llegeix">Revisar</button>
        </div>
      </template>

      <template v-else>
        <p class="text-small text-muted" style="margin-top:0;">
          Revisa la persona de cada línia (en groc, les que cal triar). Les que ja tenen aquests codis
          surten desmarcades. La nova zona val des d'avui i tanca l'anterior.
        </p>
        <div style="overflow-y:auto;">
          <div v-for="(f, i) in files" :key="i" class="cpb-fila" :class="{ 'cpb-falta': !f.userId }">
            <input type="checkbox" v-model="f.marcat" :disabled="!f.userId" />
            <div class="cpb-linia">
              <div class="cpb-nom">{{ f.nom }}</div>
              <div class="cpb-cps">{{ f.cps.join(' · ') }}</div>
            </div>
            <div>
              <select class="form-select cpb-sel" v-model="f.userId" @change="revisa(f)">
                <option :value="null">— Tria la persona —</option>
                <optgroup v-if="f.candidats.length" label="Coincidències">
                  <option v-for="p in f.candidats" :key="'c' + p.id" :value="p.id">{{ p.name }}</option>
                </optgroup>
                <optgroup label="Tota la plantilla">
                  <option v-for="p in ordenades" :key="p.id" :value="p.id">{{ p.name }}</option>
                </optgroup>
              </select>
              <div v-if="f.userId" class="text-small text-muted cpb-ara">
                Ara: {{ (cpsActuals(f.userId) || []).join(' · ') || 'cap' }}<span v-if="f.igual"> — ja ho té</span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" :disabled="desant" @click="files = []">← Tornar</button>
          <button class="btn btn-primary" :disabled="desant || !marcats.length" @click="desa">
            {{ desant ? `Desant… (${fets}/${marcats.length})` : `Assignar ${marcats.length}` }}
          </button>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { db } from '../services/db'
import { auditLog } from '../services/audit'
import { useAuthStore } from '../stores/auth'
import { avisa } from '../utils/avisos'
import { llegeixLlista, candidats, triaAutomatica } from '../utils/cpEnBloc'
import { reavaluaZonaPersona } from '../utils/reavaluaZona'

const props = defineProps({
  persones: { type: Array, default: () => [] },
  cpsActuals: { type: Function, default: () => [] }   // id → codis postals vigents
})
const emit = defineEmits(['close', 'fet'])
const authStore = useAuthStore()

const text = ref('')
const files = ref([])
const desant = ref(false)
const fets = ref(0)
const ordenades = computed(() => [...props.persones].sort((a, b) => a.name.localeCompare(b.name, 'ca')))
const marcats = computed(() => files.value.filter(f => f.marcat && f.userId))

const mateixos = (a, b) => [...(a || [])].sort().join(',') === [...(b || [])].sort().join(',')

function revisa(f) {
  f.igual = !!f.userId && mateixos(props.cpsActuals(f.userId), f.cps)
  f.marcat = !!f.userId && !f.igual
}

function llegeix() {
  files.value = llegeixLlista(text.value).map(f => {
    const fila = { ...f, candidats: candidats(f.nom, props.persones), userId: triaAutomatica(f.nom, props.persones)?.id ?? null, marcat: false, igual: false }
    revisa(fila)
    return fila
  })
  if (!files.value.length) avisa('No s\'ha trobat cap línia amb nom i codi postal.', 'error')
}

async function desa() {
  desant.value = true
  fets.value = 0
  let errors = 0
  const avui = new Date().toISOString().split('T')[0]
  const zones = await db.getZones().catch(() => [])
  for (const f of marcats.value) {
    try {
      let zona = zones.find(z => z.type === 'CP' && mateixos(z.postal_codes, f.cps))
      if (!zona) {
        zona = await db.addZone({
          name: `Zona CP ${f.cps[0]}${f.cps.length > 1 ? '...' : ''}`, type: 'CP', province: 'Barcelona',
          postal_codes: [...f.cps], municipalities: [], active: true
        })
        zones.push(zona)
      }
      await db.assignZone(zona.id, f.userId, avui, null)
      // Camp antic (primer CP), conservant especialitats, departaments i lots com fa la fitxa.
      const u = await db.getUser(f.userId)
      if (u) await db.updateUser({ id: u.id, postal_code_assigned: f.cps[0], specialties: (u.specialties || []).map(s => s.code), departments: (u.departments || []).map(d => d.code), lots: (u.lots || []).map(l => l.code) })
      auditLog(authStore.userId, 'ASSIGN_ZONE', 'user', f.userId, `Zona CP (en bloc): ${f.cps.join(',')}`)
      await reavaluaZonaPersona(f.userId).catch(() => 0)
      fets.value++
    } catch (e) {
      console.error(e)
      errors++
    }
  }
  desant.value = false
  avisa(errors ? `S'han assignat ${fets.value} persones; ${errors} no s'han pogut desar.` : `S'han assignat els codis postals a ${fets.value} persones.`, errors ? 'error' : 'ok')
  emit('fet')
  emit('close')
}
</script>

<style scoped>
.cpb-fila { display: grid; grid-template-columns: 24px 1fr minmax(220px, 1fr); gap: 10px; align-items: center; padding: 8px 4px; border-bottom: 1px solid var(--color-border-light, #f1f1f1); font-size: 0.88rem; }
.cpb-falta { background: rgba(245, 158, 11, 0.08); }
.cpb-nom { font-weight: 600; }
.cpb-cps { color: var(--color-text-secondary, #6b7280); font-size: 0.82rem; }
.cpb-sel { padding: 4px 8px; font-size: 0.85rem; }
.cpb-ara { margin-top: 2px; }
@media (max-width: 600px) { .cpb-fila { grid-template-columns: 24px 1fr; } .cpb-fila > div:last-child { grid-column: 2; } }
</style>
