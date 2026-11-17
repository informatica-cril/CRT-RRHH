<template>
  <!-- Notificacions dins de l'app: targetes a la cantonada que es queden fins que es resol la causa o es
       descarten. Substitueixen la barra de color de dalt de tot. Les noves fan un so suau i parpellegen un cop. -->
  <div v-if="visibles.length" class="notifs" role="region" aria-label="Notificacions" aria-live="assertive">
    <transition-group name="notif">
      <div v-for="n in visibles" :key="n.id" class="notif" :class="[n.nivell, { nova: novaIds.has(n.id) }]">
        <div class="notif-ico">{{ icona(n) }}</div>
        <div class="notif-cos">
          <div class="notif-titol">{{ n.titol }}</div>
          <div v-if="n.text" class="notif-text">{{ n.text }}</div>
          <div class="notif-accions">
            <button v-if="n.enllac" class="notif-btn" @click="obre(n)">{{ n.accio || 'Obrir' }} ▸</button>
            <button v-if="n.alerta_id" class="notif-btn sec" @click="descarta(n)">Ja ho sé</button>
          </div>
        </div>
        <button class="notif-x" title="Amagar fins que hi hagi res de nou" @click="amaga(n)">✕</button>
      </div>
    </transition-group>
    <button v-if="amagades.size" class="notif-reobre" @click="amagades.clear(); desaAmagades()">🔔 {{ amagades.size }} amagada{{ amagades.size > 1 ? 'es' : '' }} · mostrar</button>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api from '../services/apiClient'

const authStore = useAuthStore()
const router = useRouter()
const items = ref([])
const CLAU_AMAGADES = 'notifs_amagades'
const AMAGADA_MS = 12 * 3600 * 1000       // la ✕ es recorda 12 h, també si es recarrega la pàgina
const llegeixAmagades = () => {
  try {
    const o = JSON.parse(localStorage.getItem(CLAU_AMAGADES) || '{}'), ara = Date.now()
    return Object.keys(o).filter(id => ara - o[id] < AMAGADA_MS)
  } catch (e) { return [] }
}
const desaAmagades = () => {
  try {
    const ara = Date.now(), o = {}
    let abans = {}
    try { abans = JSON.parse(localStorage.getItem(CLAU_AMAGADES) || '{}') } catch (e) { /* res */ }
    amagades.forEach(id => { o[id] = abans[id] || ara })
    localStorage.setItem(CLAU_AMAGADES, JSON.stringify(o))
  } catch (e) { /* sense emmagatzematge: només es recorda mentre la pàgina és oberta */ }
}
const amagades = reactive(new Set(llegeixAmagades()))   // amagades amb la ✕: tornen només si en surt una de nova
const novaIds = reactive(new Set())      // les que acaben d'arribar (destaquen una estona)
const vistes = new Set()
let timer = null
let primera = true

const visibles = computed(() => items.value.filter(n => !amagades.has(n.id)).slice(0, 4))

const icona = (n) => (n.tipus === 'incidencia' ? '💬' : n.tipus === 'saldo' ? '⏱' : n.nivell === 'urgent' ? '⏰' : '🔔')

async function carrega() {
  if (!authStore.userId || document.hidden) return
  try {
    const r = await api.get('/v1/notificacions')
    const nous = (r?.items || []).filter(n => !vistes.has(n.id))
    items.value = r?.items || []
    // Si ja no hi ha una notificació (causa resolta), es neteja d'«amagades» i «vistes».
    const vius = new Set(items.value.map(n => n.id))
    let canvi = false
    for (const id of [...amagades]) if (!vius.has(id)) { amagades.delete(id); canvi = true }
    if (canvi) desaAmagades()
    nous.forEach(n => { vistes.add(n.id); novaIds.add(n.id) })
    if (nous.length && !primera) so()
    if (nous.length) setTimeout(() => nous.forEach(n => novaIds.delete(n.id)), 6000)
    primera = false
    window.dispatchEvent(new CustomEvent('notificacions-total', { detail: items.value.length }))
  } catch (e) { /* sense xarxa: es torna a provar al següent torn */ }
}

// So curt i suau (si el navegador ho permet): perquè una notificació nova no passi desapercebuda.
function so() {
  try {
    const C = window.AudioContext || window.webkitAudioContext
    if (!C) return
    const c = new C(), o = c.createOscillator(), g = c.createGain()
    o.type = 'sine'; o.frequency.value = 880; g.gain.value = 0.04
    o.connect(g); g.connect(c.destination); o.start(); o.stop(c.currentTime + 0.18)
  } catch (e) { /* sense so */ }
}

function amaga(n) { amagades.add(n.id); desaAmagades() }
function obre(n) { amagades.add(n.id); desaAmagades(); router.push(n.enllac) }
async function descarta(n) {
  try { await api.post(`/v1/work-log-alerts/${n.alerta_id}/dismiss`) } catch (e) { /* es tornarà a mostrar */ }
  items.value = items.value.filter(x => x.id !== n.id)
}

function arrenca() {
  clearInterval(timer)
  if (!authStore.userId) return
  primera = true
  carrega()
  timer = setInterval(carrega, 30000)
}

onMounted(() => { arrenca(); document.addEventListener('visibilitychange', carrega); window.addEventListener('incidencies-vistes', carrega) })
onUnmounted(() => { clearInterval(timer); document.removeEventListener('visibilitychange', carrega); window.removeEventListener('incidencies-vistes', carrega) })
watch(() => authStore.userId, (id, antic) => { items.value = []; vistes.clear(); if (antic) { amagades.clear(); desaAmagades() } id ? arrenca() : clearInterval(timer) })
</script>

<style scoped>
.notifs { position: fixed; top: 72px; right: 18px; z-index: 9000; display: flex; flex-direction: column; gap: 10px; width: min(380px, calc(100vw - 32px)); pointer-events: none; }
.notif { pointer-events: auto; display: flex; gap: 12px; align-items: flex-start; padding: 14px 14px 12px; border-radius: 14px; background: var(--color-surface, #fff); border: 1px solid var(--color-border-light, #e5e7eb); border-left: 5px solid #3d6fb6; box-shadow: 0 12px 32px rgba(15, 35, 60, 0.18); color: var(--color-text, #1f2937); }
.notif.urgent { border-left-color: #d97706; }
.notif.nova { animation: notif-pols 1.2s ease 2; }
.notif-ico { font-size: 1.4rem; line-height: 1; padding-top: 2px; }
.notif-cos { flex: 1; min-width: 0; }
.notif-titol { font-weight: 700; font-size: .95rem; }
.notif-text { font-size: .85rem; color: var(--color-text-secondary, #4b5563); margin-top: 2px; line-height: 1.4; overflow-wrap: anywhere; }
.notif-accions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
.notif-btn { border: 0; border-radius: 8px; padding: 6px 12px; font-size: .82rem; font-weight: 700; cursor: pointer; background: var(--color-primary, #094E8C); color: #fff; }
.notif-btn.sec { background: transparent; color: var(--color-text-secondary, #4b5563); border: 1px solid var(--color-border, #d1d5db); font-weight: 600; }
.notif-x { background: none; border: 0; cursor: pointer; color: var(--color-text-muted, #9ca3af); font-size: .9rem; padding: 2px 4px; }
.notif-reobre { pointer-events: auto; align-self: flex-end; border: 0; border-radius: 999px; padding: 6px 12px; font-size: .78rem; cursor: pointer; background: var(--color-surface, #fff); box-shadow: 0 6px 18px rgba(15, 35, 60, .15); }
.notif-enter-active, .notif-leave-active { transition: all .3s ease; }
.notif-enter-from, .notif-leave-to { opacity: 0; transform: translateX(24px); }
@keyframes notif-pols { 0%, 100% { box-shadow: 0 12px 32px rgba(15, 35, 60, .18); } 50% { box-shadow: 0 0 0 4px rgba(217, 119, 6, .35), 0 12px 32px rgba(15, 35, 60, .18); } }
@media (max-width: 768px) { .notifs { top: 64px; right: 16px; left: 16px; width: auto; } }
</style>
