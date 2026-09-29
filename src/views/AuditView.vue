<template>
  <div>
    <div class="page-header">
      <div><h1 class="page-title">{{ t('audit_logs') }}</h1></div>
      <div class="page-actions">
        <input class="form-input" style="width:200px;" v-model="searchQuery" :placeholder="t('search')" />
      </div>
    </div>

    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>{{ t('date') }}</th>
              <th>{{ t('employee') }}</th>
              <th>{{ t('action') }}</th>
              <th>{{ t('details') }}</th>
              <th>{{ t('ip_address') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in filteredLogs" :key="log.id">
              <td>{{ formatDateTime(log.created_at) }}</td>
              <td>{{ getUserName(log.user_id) }}</td>
              <td><span class="badge badge-primary">{{ log.action }}</span></td>
              <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;">{{ log.details || '—' }}</td>
              <td><code style="font-size:0.8rem;">{{ log.ip_address }}</code></td>
            </tr>
            <tr v-if="filteredLogs.length === 0">
              <td colspan="5" class="text-center text-muted" style="padding:32px;">No hi ha registres d'auditoria</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <p class="rdl-notice">Registre d'auditoria conforme a l'ENS i RGPD Art. 30</p>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { db } from '../services/db'
import { i18n } from '../i18n'

const t = (key) => i18n.t(key)
const searchQuery = ref('')
const logs = ref([])
const users = ref([])

async function fetchData() {
  try {
    logs.value = await db.getAuditLogs()
    users.value = await db.getUsers()
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)

const filteredLogs = computed(() => {
  let result = logs.value.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(l => 
      l.action?.toLowerCase().includes(q) || 
      l.details?.toLowerCase().includes(q) ||
      getUserName(l.user_id).toLowerCase().includes(q)
    )
  }
  return result.slice(0, 100)
})

function getUserName(id) { 
  return users.value.find(u => u.id === id)?.name || 'Sistema' 
}
function formatDateTime(d) { return new Date(d).toLocaleString('ca-ES', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) }
</script>
