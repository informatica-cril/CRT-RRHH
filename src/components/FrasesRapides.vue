<template>
  <!-- Frases ràpides: un clic escriu la frase al camp (es pot retocar); escriure a mà sempre val. -->
  <div v-if="llista.length" class="fr" role="group" aria-label="Frases ràpides">
    <button v-for="f in llista" :key="f" type="button" class="fr-xip" :class="{ 'fr-triada': modelValue === f }"
      :disabled="disabled" @click="tria(f)">{{ f }}</button>
    <span class="fr-o">o escriu-ho a mà</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { frases } from '../utils/frasesRapides'

const props = defineProps({
  clau: { type: String, required: true },
  modelValue: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const llista = computed(() => frases(props.clau))
function tria(f) {
  emit('update:modelValue', props.modelValue === f ? '' : f)
}
</script>

<style scoped>
.fr { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin: 0 0 6px; }
.fr-xip { font-size: .78rem; line-height: 1.25; text-align: left; padding: 5px 10px; border-radius: 99px; cursor: pointer;
  border: 1px solid var(--color-border, #DCE4EE); background: var(--color-bg, #f3f6fa); color: var(--color-text, #233);
  transition: background .12s, border-color .12s; }
.fr-xip:hover:not(:disabled) { border-color: var(--color-primary, #094E8C); color: var(--color-primary, #094E8C); }
.fr-xip.fr-triada, .fr-xip.fr-triada:hover:not(:disabled) { background: var(--color-primary, #094E8C); border-color: var(--color-primary, #094E8C); color: #fff; }
.fr-xip:disabled { opacity: .5; cursor: default; }
.fr-o { font-size: .74rem; color: var(--color-text-muted, #7a8aa0); margin-left: 2px; }
</style>
