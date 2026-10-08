<template>
  <!-- Camp de contrasenya amb un ull per veure el que s'ha escrit. Els atributs (id, required,
       autocomplete, placeholder, class…) passen a l'input. -->
  <div class="cc">
    <input v-bind="$attrs" :type="visible ? 'text' : 'password'" :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)" class="cc-input" />
    <button type="button" class="cc-ull" @click="visible = !visible"
      :aria-label="visible ? 'Amaga la contrasenya' : 'Mostra la contrasenya'"
      :title="visible ? 'Amaga la contrasenya' : 'Mostra la contrasenya'" :aria-pressed="visible">
      <svg v-if="!visible" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" /><circle cx="12" cy="12" r="3" />
      </svg>
      <svg v-else viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-7 0-11-7-11-7a18.45 18.45 0 0 1 5.06-5.94" />
        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.16 3.19" />
        <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" /><line x1="1" y1="1" x2="23" y2="23" />
      </svg>
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'

defineOptions({ inheritAttrs: false })
defineProps({ modelValue: { type: String, default: '' } })
defineEmits(['update:modelValue'])

const visible = ref(false)
</script>

<style scoped>
.cc { position: relative; width: 100%; }
.cc-input { width: 100%; padding-right: 44px !important; box-sizing: border-box; }
.cc-ull { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px;
  display: flex; align-items: center; justify-content: center; border: none; background: transparent;
  color: var(--color-text-muted, #7a8aa0); cursor: pointer; border-radius: 8px; padding: 0; }
.cc-ull:hover { color: var(--color-primary, #094E8C); background: rgba(9, 78, 140, 0.06); }
.cc-ull:focus-visible { outline: 2px solid var(--color-primary, #094E8C); outline-offset: 1px; }
</style>
