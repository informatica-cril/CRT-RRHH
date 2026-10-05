<template>
  <div v-if="visible" class="break-timer-overlay">
    <div class="break-timer-modal">
      <h2>⏱️ Pausa Obligatòria</h2>
      <p class="break-message">{{ message }}</p>
      <div class="countdown">
        <span class="minutes">{{ displayMinutes }}</span>
        <span class="separator">:</span>
        <span class="seconds">{{ displaySeconds }}</span>
      </div>
      <div class="progress-bar">
        <div class="progress-fill" :style="{ width: progressPercent + '%' }"></div>
      </div>
      <p class="break-info">La pausa obligatòria és de {{ durationMinutes }} minuts. No es pot ometre.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  durationMinutes: { type: Number, default: 20 },
  startTime: { type: String, default: null },
  // Diferencia (ms) entre el rellotge del servidor i el del dispositiu, calculada
  // un cop fora d'aquí. Si el rellotge del dispositiu va desquadrat, el comptador
  // seguiria sent correcte perque no depen que aquell rellotge sigui fiable.
  clockOffsetMs: { type: Number, default: 0 },
  message: { type: String, default: 'Has de fer una pausa obligatòria de 20 minuts.' }
})

const emit = defineEmits(['complete'])

const remainingSeconds = ref(0)
let intervalId = null

const displayMinutes = computed(() => String(Math.floor(remainingSeconds.value / 60)).padStart(2, '0'))
const displaySeconds = computed(() => String(remainingSeconds.value % 60).padStart(2, '0'))
const progressPercent = computed(() => {
  const total = props.durationMinutes * 60
  return Math.max(0, Math.min(100, ((total - remainingSeconds.value) / total) * 100))
})

watch(() => props.visible, (val) => {
  if (val && props.startTime) {
    startCountdown()
  } else {
    stopCountdown()
  }
})

function startCountdown() {
  const start = new Date(props.startTime).getTime()
  const total = props.durationMinutes * 60 * 1000
  const end = start + total

  const update = () => {
    const now = Date.now() + props.clockOffsetMs
    const remaining = Math.max(0, end - now)
    remainingSeconds.value = Math.floor(remaining / 1000)
    if (remaining <= 0) {
      stopCountdown()
      emit('complete')
    }
  }

  update()
  intervalId = setInterval(update, 1000)
}

function stopCountdown() {
  if (intervalId) {
    clearInterval(intervalId)
    intervalId = null
  }
}

onUnmounted(() => stopCountdown())
</script>

<style scoped>
.break-timer-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0, 0, 0, 0.85); z-index: 9999;
  display: flex; align-items: center; justify-content: center;
}
.break-timer-modal {
  background: #fff; border-radius: 16px; padding: 40px;
  text-align: center; max-width: 400px; width: 90%;
  box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}
.break-timer-modal h2 { margin: 0 0 16px; font-size: 1.5em; }
.break-message { color: #666; margin-bottom: 24px; }
.countdown {
  font-size: 4em; font-weight: bold; color: #1976d2;
  font-variant-numeric: tabular-nums; margin: 20px 0;
}
.separator { color: #ccc; }
.progress-bar {
  width: 100%; height: 8px; background: #e0e0e0;
  border-radius: 4px; overflow: hidden; margin: 16px 0;
}
.progress-fill {
  height: 100%; background: #4caf50;
  transition: width 1s linear;
}
.break-info { color: #999; font-size: 0.85em; margin: 12px 0; }
.btn-skip {
  margin-top: 16px; background: #ff9800; color: white;
  border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;
  font-size: 0.85em;
}
</style>
