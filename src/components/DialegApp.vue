<template>
  <transition name="dlg">
    <div v-if="dialeg.obert" class="dlg-fons" @click.self="respon(false)" @keydown.esc="respon(false)">
      <div class="dlg" role="dialog" aria-modal="true">
        <div class="dlg-cap">
          <span class="dlg-icona" :class="{ perill: dialeg.perill }">{{ dialeg.perill ? '!' : (dialeg.tipus === 'demana' ? '✎' : '?') }}</span>
          <div class="dlg-text">{{ dialeg.missatge }}</div>
        </div>

        <FrasesRapides v-if="dialeg.tipus === 'demana' && dialeg.frases" :clau="dialeg.frases" v-model="dialeg.valor" />
        <textarea v-if="dialeg.tipus === 'demana'" ref="camp" v-model="dialeg.valor" class="form-input dlg-camp" rows="3"
          :placeholder="dialeg.placeholder" @keydown.enter.ctrl="pot && respon(true)"></textarea>
        <div v-if="dialeg.tipus === 'demana' && dialeg.minim > 1" class="dlg-compte" :class="{ ok: pot }">
          {{ dialeg.valor.trim().length }} / {{ dialeg.minim }} caràcters mínim
        </div>

        <div class="dlg-botons">
          <button class="btn btn-outline" @click="respon(false)">Cancel·lar</button>
          <button ref="ok" class="btn" :class="dialeg.perill ? 'dlg-perill' : 'btn-primary'" :disabled="!pot" @click="respon(true)">
            {{ dialeg.boto }}
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { dialeg, respon } from '../utils/dialegs'
import FrasesRapides from './FrasesRapides.vue'

const camp = ref(null)
const ok = ref(null)
const pot = computed(() => dialeg.tipus !== 'demana' || dialeg.valor.trim().length >= (dialeg.obligatori ? dialeg.minim : 0))

watch(() => dialeg.obert, async (obert) => {
  if (!obert) return
  await nextTick()
  ;(dialeg.tipus === 'demana' ? camp.value : ok.value)?.focus()
})

// Esc tanca encara que el focus no sigui dins la finestra.
if (typeof window !== 'undefined') {
  window.addEventListener('keydown', (e) => { if (e.key === 'Escape' && dialeg.obert) respon(false) })
}
</script>

<style scoped>
.dlg-fons { position: fixed; inset: 0; z-index: 10001; background: rgba(15, 35, 60, 0.35); display: flex; align-items: center; justify-content: center; padding: 16px; }
.dlg { width: 100%; max-width: 440px; background: var(--color-surface, #fff); border-radius: 16px; box-shadow: 0 20px 50px rgba(15, 35, 60, 0.25); padding: 20px; display: flex; flex-direction: column; gap: 14px; }
.dlg-cap { display: flex; gap: 12px; align-items: flex-start; }
.dlg-icona { flex-shrink: 0; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #fff; background: #3d6fb6; }
.dlg-icona.perill { background: #b85c5c; }
.dlg-text { flex: 1; white-space: pre-line; line-height: 1.5; font-size: 0.95rem; color: var(--color-text, #1f2937); padding-top: 4px; }
.dlg-camp { width: 100%; resize: vertical; }
.dlg-compte { margin-top: -8px; font-size: 0.78rem; color: #b85c5c; text-align: right; }
.dlg-compte.ok { color: #3e8e63; }
.dlg-botons { display: flex; justify-content: flex-end; gap: 8px; }
.dlg-perill { background: #b85c5c; color: #fff; border: none; }
.dlg-perill:hover { background: #a24f4f; }
.dlg-enter-active, .dlg-leave-active { transition: opacity 0.18s ease; }
.dlg-enter-from, .dlg-leave-to { opacity: 0; }
</style>
