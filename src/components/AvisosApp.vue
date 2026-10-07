<template>
  <div class="avisos" aria-live="polite">
    <transition-group name="avis">
      <div v-for="a in avisos" :key="a.id" class="avis" :class="a.tipus" role="status">
        <span class="avis-icona">{{ a.tipus === 'ok' ? '✓' : a.tipus === 'error' ? '!' : 'i' }}</span>
        <div class="avis-text">{{ a.missatge }}</div>
        <button class="avis-tanca" @click="tanca(a.id)" aria-label="Tancar">✕</button>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { avisos, tanca } from '../utils/avisos'
</script>

<style scoped>
.avisos { position: fixed; right: 18px; bottom: 18px; z-index: 10000; display: flex; flex-direction: column; gap: 10px; max-width: min(420px, calc(100vw - 32px)); pointer-events: none; }
.avis { pointer-events: auto; display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border-radius: 12px; background: var(--color-surface, #fff); border: 1px solid var(--color-border-light, #e5e7eb); border-left: 4px solid #3d6fb6; box-shadow: 0 10px 28px rgba(15, 35, 60, 0.14); font-size: 0.9rem; color: var(--color-text, #1f2937); }
.avis.ok { border-left-color: #3e8e63; }
.avis.error { border-left-color: #b85c5c; }
.avis-icona { flex-shrink: 0; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.8rem; color: #fff; background: #3d6fb6; }
.avis.ok .avis-icona { background: #3e8e63; }
.avis.error .avis-icona { background: #b85c5c; }
.avis-text { flex: 1; white-space: pre-line; line-height: 1.45; padding-top: 2px; user-select: text; }
.avis-tanca { flex-shrink: 0; background: none; border: none; cursor: pointer; color: var(--color-text-muted, #9ca3af); font-size: 0.9rem; padding: 2px 4px; }
.avis-tanca:hover { color: var(--color-text, #374151); }
.avis-enter-active, .avis-leave-active { transition: all 0.25s ease; }
.avis-enter-from, .avis-leave-to { opacity: 0; transform: translateY(12px); }
@media (max-width: 768px) {
  .avisos { left: 16px; right: 16px; bottom: auto; top: 16px; max-width: none; }
}
</style>
