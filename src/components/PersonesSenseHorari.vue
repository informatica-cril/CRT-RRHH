<template>
  <div v-if="files.length" id="sense-horari" class="card psh">
    <div class="psh-cap">
      <h3 class="card-title">🗓️ Persones sense horari ({{ files.length }})</h3>
      <span class="text-small text-muted">Sense horari no es poden calcular les hores fora d'horari, les extres ni la pausa obligatòria. La persona veu un avís al seu tauler.</span>
    </div>
    <div class="table-container">
      <table>
        <thead><tr><th>Persona</th><th>Lloc</th><th>Què li falta</th><th>Alta</th><th></th></tr></thead>
        <tbody>
          <tr v-for="f in files" :key="f.id">
            <td>{{ f.name }}</td>
            <td>{{ f.job_profile || '—' }}</td>
            <td><span class="psh-motiu">{{ f.motiu || 'Sense horari' }}</span></td>
            <td>{{ String(f.created_at || '').slice(0, 10).split('-').reverse().join('/') }}</td>
            <td><router-link :to="`/employees?obre=${f.id}`" class="btn btn-outline btn-sm">🗓️ Assignar horari</router-link></td>
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

onMounted(async () => {
  try {
    const r = await api.get('/v1/users/sense-horari')
    files.value = Array.isArray(r) ? r : []
  } catch (e) {
    files.value = []
  }
})
</script>

<style scoped>
.psh { margin-top: 18px; }
.psh-cap { display: flex; flex-direction: column; gap: 2px; margin-bottom: 10px; }
.psh-motiu { display: inline-block; font-size: .74rem; font-weight: 600; color: #8a5a00; background: #fff4d6; border-radius: 99px; padding: 2px 10px; }
</style>
