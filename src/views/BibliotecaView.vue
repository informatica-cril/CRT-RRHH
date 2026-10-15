<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">📚 Biblioteca de documents</h1>
        <p class="page-subtitle">Formacions, protocols i informació per a tota la plantilla. Clica un document per obrir-lo.</p>
      </div>
      <div v-if="gestio" class="page-actions">
        <button class="btn btn-outline" :disabled="ocupat" @click="$refs.zipInput.click()">📦 Importar ZIP</button>
        <button class="btn btn-primary" :disabled="ocupat" @click="novaCarpeta">+ Carpeta</button>
        <input ref="zipInput" type="file" accept=".zip,application/zip" style="display:none" @change="importaZip" />
      </div>
    </div>

    <div class="card doc-cerca">
      <span class="doc-lupa">🔍</span>
      <input v-model="cerca" class="form-input" placeholder="Cerca un document…" />
    </div>

    <div v-if="ocupat" class="card doc-info">⏳ {{ ocupat }}</div>
    <div v-if="carregant" class="card doc-buit">Carregant…</div>
    <div v-else-if="!carpetes.length" class="card doc-buit">
      Encara no hi ha cap document.<span v-if="gestio"> Crea una carpeta o importa un ZIP amb les carpetes.</span>
    </div>
    <div v-else-if="!visibles.length" class="card doc-buit">Cap document coincideix amb «{{ cerca }}».</div>

    <div class="doc-carpetes">
      <div v-for="c in visibles" :key="c.id" class="card doc-carpeta"
        :class="{ arrossegant: gestio && sobre === c.id }"
        @dragover.prevent="gestio && (sobre = c.id)" @dragleave="sobre = null" @drop.prevent="gestio && deixa($event, c)">
        <button class="doc-cap" @click="commuta(c.id)">
          <span class="doc-fletxa" :class="{ oberta: obertes.has(c.id) || cerca }">▸</span>
          <span class="doc-icona-carpeta">📁</span>
          <span class="doc-nom-carpeta">{{ c.nom }}</span>
          <span class="doc-n">{{ c.fitxers.length }}</span>
        </button>

        <div v-if="obertes.has(c.id) || cerca" class="doc-cos">
          <div v-if="!c.fitxers.length" class="text-small text-muted doc-buida">Carpeta buida.</div>
          <div v-for="f in c.fitxers" :key="f.id" class="doc-fitxer">
            <button class="doc-obre" :title="`Obrir ${f.nom}`" @click="obre(f)">
              <span class="doc-tipus" :class="tipus(f.nom).classe">{{ tipus(f.nom).etiqueta }}</span>
              <span class="doc-nom">{{ f.nom }}</span>
              <span class="text-small text-muted doc-mida">{{ mida(f.mida) }}</span>
            </button>
            <button v-if="gestio" class="icon-btn doc-treu" title="Esborrar el document" @click="esborraFitxer(c, f)">🗑</button>
          </div>

          <div v-if="gestio" class="doc-gestio">
            <button class="btn btn-outline btn-sm" :disabled="!!ocupat" @click="tria(c)">⬆ Pujar documents</button>
            <span class="text-small text-muted">o arrossega'ls aquí</span>
            <span class="doc-espai"></span>
            <button class="btn btn-outline btn-sm" @click="reanomena(c)">✏️ Canviar el nom</button>
            <button class="btn btn-outline btn-sm doc-perill" @click="esborraCarpeta(c)">Esborrar la carpeta</button>
          </div>
        </div>
      </div>
    </div>
    <input ref="fitxersInput" type="file" multiple :accept="ACCEPT" style="display:none" @change="pujaTriats" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api, { apiFetchRaw } from '../services/apiClient'
import { useAuthStore } from '../stores/auth'
import { avisa } from '../utils/avisos'
import { confirma, demana } from '../utils/dialegs'

const ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.jpg,.jpeg,.png'
const authStore = useAuthStore()
const gestio = computed(() => authStore.isAdmin || authStore.isHr)
const carpetes = ref([])
const carregant = ref(true)
const ocupat = ref('')
const cerca = ref('')
const obertes = ref(new Set())
const sobre = ref(null)
const fitxersInput = ref(null)
let carpetaTriada = null

const norm = (t) => String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
// Amb cerca: les carpetes que coincideixen pel nom, o només els fitxers que hi coincideixen.
const visibles = computed(() => {
  const q = norm(cerca.value.trim())
  if (!q) return carpetes.value
  return carpetes.value.map(c => norm(c.nom).includes(q) ? c : { ...c, fitxers: c.fitxers.filter(f => norm(f.nom).includes(q)) })
    .filter(c => c.fitxers.length || norm(c.nom).includes(q))
})

function commuta(id) {
  const s = new Set(obertes.value)
  s.has(id) ? s.delete(id) : s.add(id)
  obertes.value = s
}

const TIPUS = { pdf: ['PDF', 'pdf'], doc: ['Word', 'word'], docx: ['Word', 'word'], odt: ['Text', 'word'],
  xls: ['Excel', 'excel'], xlsx: ['Excel', 'excel'], ods: ['Full', 'excel'],
  ppt: ['PowerPoint', 'ppt'], pptx: ['PowerPoint', 'ppt'], odp: ['Present.', 'ppt'],
  jpg: ['Imatge', 'img'], jpeg: ['Imatge', 'img'], png: ['Imatge', 'img'] }
function tipus(nom) {
  const t = TIPUS[String(nom).split('.').pop().toLowerCase()] || ['Fitxer', 'altre']
  return { etiqueta: t[0], classe: t[1] }
}
function mida(b) {
  if (!b) return ''
  return b < 1024 * 1024 ? `${Math.max(1, Math.round(b / 1024))} kB` : `${(b / 1024 / 1024).toFixed(1)} MB`
}

async function carrega() {
  try { carpetes.value = await api.get('/v1/biblioteca') } catch (e) { avisa("No s'ha pogut carregar la biblioteca.", 'error') }
  carregant.value = false
}

// Obrir amb la sessió a la capçalera (mai el testimoni a l'adreça). PDF i imatges s'obren en una
// pestanya; la resta es descarrega. La pestanya s'obre abans d'esperar perquè el navegador no la bloquegi.
async function obre(f) {
  const tipusF = tipus(f.nom).classe
  const enPestanya = tipusF === 'pdf' || tipusF === 'img'
  const finestra = enPestanya ? window.open('', '_blank') : null
  try {
    const res = await apiFetchRaw(`/v1/biblioteca/fitxers/${f.id}`)
    if (!res.ok) throw new Error()
    const url = URL.createObjectURL(await res.blob())
    if (finestra) {
      finestra.location.href = url
    } else {
      const a = document.createElement('a')
      a.href = url
      a.download = f.nom
      document.body.appendChild(a)
      a.click()
      a.remove()
    }
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    finestra?.close()
    avisa("No s'ha pogut obrir el document.", 'error')
  }
}

function substitueixCarpeta(c) {
  const k = carpetes.value.findIndex(x => x.id === c.id)
  if (k >= 0) carpetes.value[k] = c
  else carpetes.value.push(c)
}

async function novaCarpeta() {
  const nom = await demana('Nom de la carpeta nova:', { obligatori: true, placeholder: 'Ex.: Protocols' })
  if (!nom) return
  try {
    const c = await api.post('/v1/biblioteca/carpetes', { nom })
    substitueixCarpeta(c)
    obertes.value = new Set([...obertes.value, c.id])
  } catch (e) { avisa(e?.message || "No s'ha pogut crear la carpeta.", 'error') }
}

async function reanomena(c) {
  const nom = await demana('Nom nou de la carpeta:', { obligatori: true, valor: c.nom })
  if (!nom || nom === c.nom) return
  try { substitueixCarpeta(await api.put(`/v1/biblioteca/carpetes/${c.id}`, { nom })) }
  catch (e) { avisa(e?.message || "No s'ha pogut canviar el nom.", 'error') }
}

async function esborraCarpeta(c) {
  const text = c.fitxers.length
    ? `S'esborrarà la carpeta «${c.nom}» i els seus ${c.fitxers.length} documents. No es pot desfer.`
    : `S'esborrarà la carpeta «${c.nom}».`
  if (!await confirma(text, { perill: true, boto: 'Esborrar' })) return
  try {
    await api.delete(`/v1/biblioteca/carpetes/${c.id}`)
    carpetes.value = carpetes.value.filter(x => x.id !== c.id)
  } catch (e) { avisa(e?.message || "No s'ha pogut esborrar.", 'error') }
}

async function esborraFitxer(c, f) {
  if (!await confirma(`S'esborrarà «${f.nom}». No es pot desfer.`, { perill: true, boto: 'Esborrar' })) return
  try {
    await api.delete(`/v1/biblioteca/fitxers/${f.id}`)
    c.fitxers = c.fitxers.filter(x => x.id !== f.id)
  } catch (e) { avisa(e?.message || "No s'ha pogut esborrar.", 'error') }
}

function tria(c) {
  carpetaTriada = c
  fitxersInput.value.value = ''
  fitxersInput.value.click()
}
function pujaTriats(e) { if (carpetaTriada) puja(carpetaTriada, [...e.target.files]) }
function deixa(e, c) {
  sobre.value = null
  puja(c, [...(e.dataTransfer?.files || [])])
}

async function puja(c, fitxers) {
  if (!fitxers.length) return
  // De 20 en 20 perquè una pujada gran no topi amb el límit del servidor.
  ocupat.value = `Pujant ${fitxers.length} document(s) a «${c.nom}»…`
  try {
    for (let i = 0; i < fitxers.length; i += 20) {
      const fd = new FormData()
      fitxers.slice(i, i + 20).forEach(f => fd.append('fitxers[]', f))
      substitueixCarpeta(await api.upload(`/v1/biblioteca/carpetes/${c.id}/fitxers`, fd))
    }
    obertes.value = new Set([...obertes.value, c.id])
    avisa(`S'han pujat ${fitxers.length} document(s).`, 'ok')
  } catch (e) {
    avisa(e?.message || "No s'han pogut pujar. Comprova que siguin PDF, Office o imatges de menys de 30 MB.", 'error')
  }
  ocupat.value = ''
}

async function importaZip(e) {
  const zip = e.target.files?.[0]
  e.target.value = ''
  if (!zip) return
  ocupat.value = `Important «${zip.name}»… pot trigar una mica.`
  try {
    const fd = new FormData()
    fd.append('zip', zip)
    const r = await api.upload('/v1/biblioteca/importa-zip', fd)
    carpetes.value = r.biblioteca || []
    const omesos = r.omesos?.length ? ` No s'han importat ${r.omesos.length}: ${r.omesos.slice(0, 5).join(', ')}${r.omesos.length > 5 ? '…' : ''}.` : ''
    avisa(`Importats ${r.fitxers} documents en ${r.carpetes} carpetes.${omesos}`, r.omesos?.length ? 'info' : 'ok')
  } catch (err) {
    avisa(err?.message || "No s'ha pogut importar el ZIP.", 'error')
  }
  ocupat.value = ''
}

onMounted(carrega)
</script>

<style scoped>
.doc-cerca { position: relative; padding: 12px; margin-bottom: 12px; }
.doc-cerca input { padding-left: 36px; }
.doc-lupa { position: absolute; left: 24px; top: 50%; transform: translateY(-50%); opacity: 0.5; }
.doc-info { padding: 12px 16px; margin-bottom: 12px; background: var(--color-primary-bg, #e8f0f8); }
.doc-buit { padding: 28px; text-align: center; color: var(--color-text-secondary); }
.doc-carpetes { display: flex; flex-direction: column; gap: 10px; }
.doc-carpeta { padding: 0; overflow: hidden; transition: box-shadow 0.15s; }
.doc-carpeta.arrossegant { box-shadow: 0 0 0 2px var(--color-primary); }
.doc-cap { display: flex; align-items: center; gap: 10px; width: 100%; padding: 14px 18px; background: none; border: 0; cursor: pointer; text-align: left; color: var(--color-text); font-size: 0.95rem; }
.doc-cap:hover { background: var(--color-bg-hover); }
.doc-fletxa { display: inline-block; transition: transform 0.15s; color: var(--color-text-secondary); }
.doc-fletxa.oberta { transform: rotate(90deg); }
.doc-icona-carpeta { font-size: 1.2rem; }
.doc-nom-carpeta { flex: 1; font-weight: 600; }
.doc-n { font-size: 0.78rem; font-weight: 700; background: var(--color-bg); border-radius: 999px; padding: 2px 9px; color: var(--color-text-secondary); }
.doc-cos { padding: 4px 18px 14px 44px; border-top: 1px solid var(--color-border-light); }
.doc-buida { padding: 10px 0; }
.doc-fitxer { display: flex; align-items: center; gap: 6px; border-bottom: 1px solid var(--color-border-light); }
.doc-fitxer:last-of-type { border-bottom: 0; }
.doc-obre { flex: 1; min-width: 0; display: flex; align-items: center; gap: 10px; padding: 10px 6px; background: none; border: 0; cursor: pointer; text-align: left; color: var(--color-text); border-radius: 6px; }
.doc-obre:hover { background: var(--color-bg-hover); }
.doc-obre:hover .doc-nom { text-decoration: underline; }
.doc-tipus { flex-shrink: 0; min-width: 72px; text-align: center; font-size: 0.68rem; font-weight: 800; border-radius: 6px; padding: 3px 6px; letter-spacing: 0.02em; }
.doc-tipus.pdf { background: #fde8e8; color: #b42318; }
.doc-tipus.word { background: #e3ecfb; color: #1d4ed8; }
.doc-tipus.excel { background: #e3f6ea; color: #15803d; }
.doc-tipus.ppt { background: #fdeee2; color: #c2410c; }
.doc-tipus.img { background: #f1e8fb; color: #7e22ce; }
.doc-tipus.altre { background: var(--color-bg); color: var(--color-text-secondary); }
.doc-nom { flex: 1; min-width: 0; overflow-wrap: anywhere; font-size: 0.9rem; }
.doc-mida { flex-shrink: 0; }
.doc-treu { opacity: 0.6; }
.doc-treu:hover { opacity: 1; }
.doc-gestio { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding-top: 12px; }
.doc-espai { flex: 1; }
.doc-perill { color: var(--color-danger, #dc2626); }
@media (max-width: 600px) { .doc-cos { padding-left: 18px; } .doc-tipus { min-width: 56px; } }
</style>
