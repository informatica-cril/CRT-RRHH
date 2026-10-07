<template>
  <div v-if="files.length" id="sense-sortida" class="card jss">
    <div class="jss-cap">
      <h3 class="card-title">🚪 Jornades sense sortida ({{ files.length }})</h3>
      <span class="text-small text-muted">Compten 0 hores. El registre no es modifica: la persona pot declarar l'hora i RRHH decideix.</span>
    </div>
    <div class="table-container">
      <table>
        <thead><tr><th>Persona</th><th>Dia</th><th>Entrada</th><th>Declaració de la persona</th><th></th></tr></thead>
        <tbody>
          <tr v-for="f in files" :key="f.id">
            <td>{{ f.nom }}</td>
            <td>{{ dataCurta(f.date) }}</td>
            <td>{{ hora(f.start_time) }}</td>
            <td>
              <template v-if="f.declaracio">
                Sortida a les <strong>{{ hora(f.declaracio.sortida) }}</strong>
                <span v-if="dia(f.declaracio.sortida) !== f.date"> del {{ dataCurta(f.declaracio.sortida) }}</span>
                <div class="text-small text-muted">«{{ f.declaracio.explicacio }}»</div>
              </template>
              <span v-else class="text-small" style="color:var(--color-danger);">Encara no ha declarat res</span>
            </td>
            <td><router-link :to="`/work-logs/${f.id}/detail`" class="btn btn-outline btn-sm">🔍 Detall</router-link></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/apiClient'

const files = ref([])
const hora = (t) => String(t || '').slice(11, 16)
const dia = (t) => String(t || '').slice(0, 10)
const dataCurta = (t) => dia(t).split('-').reverse().join('/')

onMounted(async () => {
  try {
    const r = await api.get('/v1/work-logs/sense-sortida/equip')
    files.value = Array.isArray(r) ? r : []
  } catch (e) {
    files.value = []
  }
})
</script>

<style scoped>
.jss { margin-top: 18px; }
.jss-cap { display: flex; flex-direction: column; gap: 2px; margin-bottom: 10px; }
</style>
