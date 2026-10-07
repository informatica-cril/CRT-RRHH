<template>
  <div ref="arrel" class="sp" :class="{ obert, desactivat: disabled }">
    <button type="button" class="form-select sp-boto" :disabled="disabled" @click="commuta" @keydown.down.prevent="obre">
      <span :class="{ 'sp-buit': !seleccionada && !esBuida }">{{ textSeleccio }}</span>
    </button>

    <div v-if="obert" class="sp-panell">
      <input ref="cerca" v-model="q" class="form-input sp-cerca" type="text" placeholder="Cerca per nom, DNI o correu…"
        @keydown.down.prevent="mou(1)" @keydown.up.prevent="mou(-1)" @keydown.enter.prevent="triaActiva"
        @keydown.esc.prevent="tanca" @keydown.tab="tanca" />
      <ul ref="llista" class="sp-llista" role="listbox">
        <li v-if="buida" class="sp-opcio sp-opcio-buida" :class="{ actiu: actiu === -1, triat: esBuida }"
          @mousedown.prevent="tria(buida.valor)" @mouseenter="actiu = -1">{{ buida.text }}</li>
        <li v-for="(p, i) in filtrades" :key="p[valorKey]" class="sp-opcio" :class="{ actiu: actiu === i, triat: p[valorKey] === modelValue }"
          role="option" @mousedown.prevent="tria(p[valorKey])" @mouseenter="actiu = i">
          <span>{{ p[textKey] }}</span>
          <span v-if="detallDe(p)" class="sp-detall">{{ detallDe(p) }}</span>
        </li>
        <li v-if="!filtrades.length" class="sp-res">Cap persona coincideix amb «{{ q }}»</li>
      </ul>
    </div>
  </div>
</template>

<script setup>
// Desplegable de persones amb cercador (substitueix els <select> de llistes llargues de plantilla).
//   <SelectorPersona v-model="filtre" :persones="workers" :buida="{ valor: 'all', text: 'Tots els treballadors' }" />
// - Cerca per nom, DNI o correu, sense tenir en compte accents ni majúscules.
// - Llista ordenada alfabèticament. Teclat: fletxes, Intro i Esc.
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelValue: { default: null },
  persones: { type: Array, default: () => [] },
  valorKey: { type: String, default: 'id' },
  textKey: { type: String, default: 'name' },
  buida: { type: Object, default: null },          // { valor, text }: opció «Tots…» o «— cap —»
  placeholder: { type: String, default: 'Tria una persona…' },
  detall: { type: [Function, String], default: null }, // text secundari: funció o camp (p. ex. 'dni')
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'change'])

const arrel = ref(null)
const cerca = ref(null)
const llista = ref(null)
const obert = ref(false)
const q = ref('')
const actiu = ref(0)

const norm = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim()
const detallDe = (p) => typeof props.detall === 'function' ? props.detall(p) : (props.detall ? p[props.detall] : '')

const ordenades = computed(() => [...props.persones].sort((a, b) =>
  String(a[props.textKey] ?? '').localeCompare(String(b[props.textKey] ?? ''), 'ca', { sensitivity: 'base' })))

const filtrades = computed(() => {
  const t = norm(q.value)
  if (!t) return ordenades.value
  const paraules = t.split(/\s+/)
  return ordenades.value.filter(p => {
    const text = norm(`${p[props.textKey]} ${p.dni ?? ''} ${p.email ?? ''} ${detallDe(p) ?? ''}`)
    return paraules.every(w => text.includes(w))
  })
})

const seleccionada = computed(() => props.persones.find(p => p[props.valorKey] === props.modelValue) || null)
const esBuida = computed(() => !!props.buida && props.modelValue === props.buida.valor)
const textSeleccio = computed(() => seleccionada.value
  ? seleccionada.value[props.textKey]
  : (esBuida.value ? props.buida.text : props.placeholder))

async function obre() {
  if (props.disabled) return
  obert.value = true
  q.value = ''
  const i = filtrades.value.findIndex(p => p[props.valorKey] === props.modelValue)
  actiu.value = i >= 0 ? i : (props.buida ? -1 : 0)
  await nextTick()
  cerca.value?.focus()
  llista.value?.querySelector('.actiu')?.scrollIntoView({ block: 'nearest' })
}
function tanca() { obert.value = false }
function commuta() { obert.value ? tanca() : obre() }
function tria(valor) {
  emit('update:modelValue', valor)
  emit('change', valor)
  tanca()
}
function mou(d) {
  const min = props.buida ? -1 : 0
  actiu.value = Math.max(min, Math.min(filtrades.value.length - 1, actiu.value + d))
  nextTick(() => llista.value?.querySelector('.actiu')?.scrollIntoView({ block: 'nearest' }))
}
function triaActiva() {
  if (actiu.value === -1 && props.buida) return tria(props.buida.valor)
  const p = filtrades.value[actiu.value] ?? filtrades.value[0]
  if (p) tria(p[props.valorKey])
}
const fora = (e) => { if (obert.value && arrel.value && !arrel.value.contains(e.target)) tanca() }
onMounted(() => document.addEventListener('mousedown', fora))
onBeforeUnmount(() => document.removeEventListener('mousedown', fora))
</script>

<style scoped>
.sp { position: relative; display: inline-block; min-width: 200px; }
.sp-boto { width: 100%; text-align: left; cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sp-buit { color: var(--color-text-muted, #9ca3af); }
.sp-panell { position: absolute; z-index: 60; top: calc(100% + 4px); left: 0; min-width: 100%; width: max-content; max-width: min(420px, 92vw); background: var(--color-surface, #fff); border: 1px solid var(--color-border, #dce4ee); border-radius: 12px; box-shadow: 0 12px 30px rgba(15, 35, 60, 0.16); padding: 8px; }
.sp-cerca { width: 100%; margin-bottom: 6px; }
.sp-llista { list-style: none; margin: 0; padding: 0; max-height: 280px; overflow-y: auto; }
.sp-opcio { padding: 7px 10px; border-radius: 8px; cursor: pointer; display: flex; justify-content: space-between; gap: 12px; font-size: 0.88rem; }
.sp-opcio.actiu { background: var(--color-bg, #f1f5f9); }
.sp-opcio.triat { font-weight: 700; color: var(--color-primary, #094e8c); }
.sp-opcio-buida { color: var(--color-text-secondary, #6b7280); border-bottom: 1px solid var(--color-border-light, #eef2f6); border-radius: 8px 8px 0 0; margin-bottom: 2px; }
.sp-detall { color: var(--color-text-muted, #9ca3af); font-size: 0.78rem; white-space: nowrap; }
.sp-res { padding: 10px; color: var(--color-text-muted, #9ca3af); font-size: 0.85rem; }
</style>
