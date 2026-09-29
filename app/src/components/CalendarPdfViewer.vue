<template>
  <div class="calendar-pdf-viewer">
    <div class="cpv-toolbar">
      <div class="text-small" style="font-weight:600;">📅 Calendari laboral</div>
      <div style="display:flex;align-items:center;gap:6px;">
        <button class="btn btn-outline btn-sm" @click="changeYear(-1)" :disabled="loading">‹</button>
        <span style="font-weight:700;min-width:48px;text-align:center;">{{ year }}</span>
        <button class="btn btn-outline btn-sm" @click="changeYear(1)" :disabled="loading">›</button>
      </div>
    </div>

    <!-- Contenidor no descarregable: render a canvas, sense barra de PDF ni menú contextual -->
    <div class="cpv-canvas-wrap" @contextmenu.prevent>
      <div v-if="loading" class="cpv-state">Generant calendari…</div>
      <div v-else-if="error" class="cpv-state cpv-error">No s'ha pogut generar el calendari</div>
      <canvas v-show="!loading && !error" ref="canvasEl" class="cpv-canvas"></canvas>
    </div>
    <div class="text-small text-muted" style="margin-top:6px;">
      Document informatiu · no descarregable. Els dies amb punt verd són dies efectivament treballats.
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import db from '../services/db'
import { generateLaborCalendarPdf } from '../services/laborCalendarPdf'
import * as pdfjsLib from 'pdfjs-dist/build/pdf'

pdfjsLib.GlobalWorkerOptions.workerSrc = `https://unpkg.com/pdfjs-dist@${pdfjsLib.version}/build/pdf.worker.min.mjs`

const props = defineProps({
  worker: { type: Object, required: true },
  schedule: { type: Object, default: null }
})

const year = ref(new Date().getFullYear())
const loading = ref(true)
const error = ref(false)
const canvasEl = ref(null)

// Festius i fichatges (es carreguen un cop / quan canvia el treballador)
const holidaySet = ref(new Set())
const workedDates = ref(new Set())

async function loadData() {
  try {
    const [holidays, logs] = await Promise.all([
      db.getHolidays().catch(() => []),
      db.getWorkLogsByUser(props.worker.id).catch(() => [])
    ])
    holidaySet.value = new Set((holidays || []).map(h => String(h.date).substring(0, 10)))
    workedDates.value = new Set((logs || []).filter(l => l.date).map(l => l.date.substring(0, 10)))
  } catch (e) {
    console.error('[CalendarPdfViewer] loadData', e)
  }
}

async function build() {
  loading.value = true
  error.value = false
  try {
    const bytes = await generateLaborCalendarPdf({
      workerName: props.worker.name,
      scheduleName: props.schedule?.name || '—',
      schedule: props.schedule,
      holidaySet: holidaySet.value,
      workedDates: workedDates.value,
      year: year.value
    })
    // Render a canvas amb pdfjs (una còpia de bytes: pdfjs pot transferir el buffer)
    const task = pdfjsLib.getDocument({ data: bytes.slice(0) })
    const pdf = await task.promise
    const pageObj = await pdf.getPage(1)
    await nextTick()
    const canvas = canvasEl.value
    if (!canvas) { loading.value = false; return }
    const containerW = canvas.parentElement?.clientWidth || 560
    const base = pageObj.getViewport({ scale: 1 })
    const scale = Math.min(2, Math.max(0.6, containerW / base.width))
    const viewport = pageObj.getViewport({ scale })
    canvas.width = viewport.width
    canvas.height = viewport.height
    canvas.style.width = '100%'
    canvas.style.height = 'auto'
    await pageObj.render({ canvasContext: canvas.getContext('2d'), viewport }).promise
  } catch (e) {
    console.error('[CalendarPdfViewer] build', e)
    error.value = true
  } finally {
    loading.value = false
  }
}

async function changeYear(delta) {
  year.value += delta
  await build()
}

watch(() => props.worker?.id, async () => { await loadData(); await build() })
watch(() => props.schedule, async () => { await build() })

onMounted(async () => {
  await loadData()
  await build()
})
</script>

<style scoped>
.calendar-pdf-viewer { width: 100%; }
.cpv-toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.cpv-canvas-wrap {
  border: 1px solid var(--color-border-light, #e2e8f0);
  border-radius: 10px;
  background: #fff;
  padding: 8px;
  min-height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  user-select: none;
  -webkit-user-select: none;
}
.cpv-canvas { max-width: 100%; display: block; pointer-events: none; }
.cpv-state { color: var(--color-text-secondary, #64748b); padding: 32px; font-size: 0.9rem; }
.cpv-error { color: var(--color-danger, #ef4444); }
</style>
