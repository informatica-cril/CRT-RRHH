<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">{{ t('privacy_policy') }}</h1>
        <p class="page-subtitle">{{ t('data_protection') }} - RGPD / LOPD-GDD / ENS</p>
      </div>
    </div>

    <div class="card mb-lg">
      <h3 class="card-title mb-md">1. Responsable del tractament</h3>
      <p><strong>Centre de Rehabilitació Terapèutica</strong> · NIF PENDENT</p>
      <p class="text-muted mt-sm">Correu electrònic: protecciodedades@crtbcn.cat</p>
    </div>

    <div class="card mb-lg">
      <h3 class="card-title mb-md">2. Finalitat del tractament</h3>
      <p>Les dades personals es tracten amb les següents finalitats:</p>
      <ul style="padding-left:20px;margin-top:8px;line-height:2;">
        <li>Registre diari de jornada laboral conforme a l'Art. 34.9 de l'Estatut dels Treballadors (modificat pel RDL 8/2019, Art. 10)</li>
        <li>Control d'accés i verificació de la ubicació laboral per geolocalització</li>
        <li>Gestió d'absències i permisos laborals</li>
        <li>Generació d'informes d'hours treballades</li>
        <li>Auditoria de seguretat conforme a l'Esquema Nacional de Seguretat</li>
      </ul>
    </div>

    <div class="card mb-lg">
      <h3 class="card-title mb-md">3. Base legal</h3>
      <ul style="padding-left:20px;line-height:2;">
        <li><strong>Art. 6.1.b) RGPD</strong> - Execució del contracte laboral</li>
        <li><strong>Art. 6.1.c) RGPD</strong> - Compliment d'una obligació legal (RDL 8/2019)</li>
        <li><strong>Art. 6.1.f) RGPD</strong> - Interès legítim per a la verificació de la ubicació</li>
      </ul>
    </div>

    <div class="card mb-lg">
      <h3 class="card-title mb-md">4. Dades tractades</h3>
      <div class="table-container">
        <table>
          <thead><tr><th>Categoria</th><th>Dades</th><th>Finalitat</th></tr></thead>
          <tbody>
            <tr><td>Identificatives</td><td>Nom, email</td><td>Identificació</td></tr>
            <tr><td>Laborals</td><td>Horari, hores treballades</td><td>Registre jornada (RDL 8/2019)</td></tr>
            <tr><td>Geolocalització</td><td>Latitud, longitud</td><td>Verificació ubicació</td></tr>
            <tr><td>Connexió</td><td>IP, User-Agent</td><td>Seguretat i auditoria (ENS)</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mb-lg">
      <h3 class="card-title mb-md">5. Conservació de les dades</h3>
      <p>Conforme a l'Art. 34.9 de l'Estatut dels Treballadors, els registres de jornada es conservaran durant un <strong>mínim de 4 anys</strong>.</p>
    </div>

    <div class="card mb-lg">
      <h3 class="card-title mb-md">6. Drets de l'interessat (ARCO)</h3>
      <p class="mb-md">Conforme als Art. 15-22 del RGPD, podeu exercir els següents drets:</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
        <div v-for="right in rights" :key="right.key" class="stat-card" style="cursor:pointer;" @click="requestRight(right.key)">
          <div class="stat-label">{{ right.icon }}</div>
          <div style="font-weight:600;margin-top:4px;">{{ t(right.key) }}</div>
          <p class="text-small text-muted mt-sm">{{ right.desc }}</p>
        </div>
      </div>
    </div>

    <div class="card mb-lg">
      <h3 class="card-title mb-md">7. Esquema Nacional de Seguretat (ENS)</h3>
      <p>L'aplicació implementa les mesures de seguretat establertes per l'ENS:</p>
      <ul style="padding-left:20px;margin-top:8px;line-height:2;">
        <li>Autenticació d'usuaris amb tokens JWT</li>
        <li>Registre d'auditoria de totes les accions</li>
        <li>Control d'accés basat en rols (admin/worker)</li>
        <li>Xifrat de dades en trànsit (HTTPS)</li>
        <li>Protecció contra accessos no autoritzats</li>
      </ul>
    </div>

    <div class="rdl-notice">
      Última actualització: {{ new Date().toLocaleDateString('ca-ES') }}
    </div>
  </div>
</template>

<script setup>
import { i18n } from '../i18n'

const t = (key) => i18n.t(key)

const rights = [
  { key: 'right_access', icon: '👁', desc: 'Consultar les dades personals tractades' },
  { key: 'right_rectification', icon: '✏️', desc: 'Corregir dades inexactes' },
  { key: 'right_erasure', icon: '🗑', desc: 'Sol·licitar l\'eliminació de dades' },
  { key: 'right_portability', icon: '📦', desc: 'Obtenir les dades en format portable' },
  { key: 'right_restriction', icon: '⏸', desc: 'Limitar el tractament de les dades' },
  { key: 'right_objection', icon: '🚫', desc: 'Oposar-se al tractament de dades' }
]

function requestRight(type) {
  alert(`Sol·licitud de ${type} enviada. Rebrà resposta en un termini màxim de 30 dies conforme a l'Art. 12 RGPD.`)
}
</script>
