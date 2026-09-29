<template>
  <div class="geo-consent-overlay">
    <div class="geo-consent-container">
      <!-- Header -->
      <div class="geo-consent-header">
        <div style="display:flex;align-items:center;gap:14px;">
          <div style="width:56px;height:56px;background:linear-gradient(135deg,#00806C,#3B82F6);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;flex-shrink:0;">📍</div>
          <div>
            <h2 style="font-size:1.25rem;font-weight:700;margin-bottom:4px;">Avís Legal de Geolocalització</h2>
            <div class="text-small" style="color:var(--color-text-secondary);">Centre de Rehabilitació Terapèutica (NIF PENDENT)</div>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="geo-consent-body">
        <div style="background:rgba(59,130,246,0.06);padding:16px;border-radius:10px;margin-bottom:16px;border-left:4px solid var(--color-primary);">
          <div style="font-weight:700;font-size:0.95rem;margin-bottom:8px;">🔒 Per què es recopilen les vostres dades de geolocalització?</div>
          <p style="font-size:0.88rem;line-height:1.7;color:var(--color-text-secondary);">
            Conforme a l'article <strong>34.9 de l'Estatut dels Treballadors</strong> (modificat pel <strong>RDL 8/2019</strong>), 
            l'empresa té l'<strong>obligació legal</strong> de garantir el registre diari de jornada de tots els treballadors. 
            Per als treballadors ambulatoris i domiciliaris, l'ús de la geolocalització és l'únic mecanisme viable per verificar 
            la presència al lloc de treball assignat.
          </p>
        </div>

        <div style="background:rgba(76,175,80,0.06);padding:16px;border-radius:10px;margin-bottom:16px;border-left:4px solid var(--color-success);">
          <div style="font-weight:700;font-size:0.95rem;margin-bottom:8px;">⚖️ Base Legal del Tractament</div>
          <ul style="font-size:0.85rem;line-height:1.8;color:var(--color-text-secondary);list-style:none;padding:0;">
            <li>✅ <strong>Art. 6.1.b RGPD</strong> — Execució del contracte de treball</li>
            <li>✅ <strong>Art. 6.1.c RGPD</strong> — Compliment d'una obligació legal (RDL 8/2019)</li>
            <li>✅ <strong>Art. 87 LOPDGDD</strong> — Dret a la intimitat en l'ús de dispositius digitals</li>
            <li>✅ <strong>Art. 90 LOPDGDD</strong> — Dret a la intimitat davant l'ús de sistemes de geolocalització</li>
            <li>✅ <strong>XII Conveni Col·lectiu</strong> del Sector Sanitari de Catalunya</li>
          </ul>
        </div>

        <div style="background:rgba(245,158,11,0.06);padding:16px;border-radius:10px;margin-bottom:16px;border-left:4px solid var(--color-warning);">
          <div style="font-weight:700;font-size:0.95rem;margin-bottom:8px;">📋 Què es registra exactament?</div>
          <ul style="font-size:0.85rem;line-height:1.8;color:var(--color-text-secondary);list-style:none;padding:0;">
            <li>📍 <strong>Coordenades GPS</strong> NOMÉS en els hitos (entrada/sortida i, si escau, sessions) — <strong>sense seguiment continu</strong></li>
            <li>🕐 <strong>Hora exacta</strong> del registre</li>
            <li>📱 <strong>Tipus de dispositiu</strong> i precisió del GPS</li>
            <li>🔐 El <strong>registre de jornada</strong> es conserva 4 anys (art. 34.9 ET), <strong>sense coordenades</strong></li>
            <li>📏 Pots veure a l'app la <strong>distància mesurada i el radi aplicat</strong> en cada marcatge; durant els primers <strong>60 dies (pilot)</strong> cap marcatge «fora de radi» tindrà efectes desfavorables</li>
            <li>🗑️ Les coordenades es <strong>purguen automàticament als 30 dies naturals</strong> (o en resoldre's la incidència associada, si n'hi ha)</li>
          </ul>
        </div>

        <div style="background:rgba(239,68,68,0.06);padding:16px;border-radius:10px;margin-bottom:16px;border-left:4px solid var(--color-danger);">
          <div style="font-weight:700;font-size:0.95rem;margin-bottom:8px;">🛡️ Els vostres drets (RGPD Arts. 15-22)</div>
          <p style="font-size:0.85rem;line-height:1.7;color:var(--color-text-secondary);">
            Teniu dret d'<strong>accés, rectificació, supressió, limitació del tractament, portabilitat i oposició</strong> 
            sobre les vostres dades personals. Podeu exercir-los contactant amb el Delegat de Protecció de Dades 
            de CRT a <strong>dpd@crtbcn.cat</strong>.
          </p>
          <p style="font-size:0.82rem;line-height:1.6;color:var(--color-text-secondary);margin-top:8px;">
            <strong>Important:</strong> El registre de jornada és una <strong>obligació legal</strong> per a l'empresa
            (art. 34.9 ET), i la geolocalització per al registre està emparada per l'art. 6.1.c del RGPD.
            L'ús del <strong>dispositiu personal és voluntari</strong>: si no vols instal·lar l'app al teu telèfon,
            disposes d'un <strong>mètode de registre alternatiu</strong> amb la mateixa validesa. Les dades
            <strong>només s'utilitzen per al registre de jornada</strong> i <strong>mai per a seguiment continu</strong>.
          </p>
          <p style="font-size:0.82rem;line-height:1.6;color:var(--color-text-secondary);margin-top:8px;font-weight:500;background:var(--color-bg);padding:8px;border-radius:6px;">
            ℹ️ Si retires els permisos d'ubicació, l'app no podrà fer el registre <strong>automàtic</strong>; en aquest cas
            has d'utilitzar el <strong>mètode de registre alternatiu</strong> (part de jornada). Havent prestat efectivament
            el servei, això <strong>NO es considerarà absència, incompliment ni motiu de sanció</strong>.
          </p>
        </div>
      </div>

      <!-- Footer -->
      <div class="geo-consent-footer">
        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin-bottom:16px;">
          <input type="checkbox" v-model="accepted" style="width:20px;height:20px;margin-top:2px;flex-shrink:0;accent-color:var(--color-primary);" />
          <span style="font-size:0.88rem;line-height:1.6;">
            <strong>Declaro</strong> que he estat informat/da sobre l'ús de la geolocalització per al registre de jornada, 
            comprenc la base legal del tractament i conec els meus drets conforme al RGPD i la LOPDGDD.
          </span>
        </label>
        <button
          class="btn btn-primary"
          style="width:100%;padding:14px;font-size:1rem;font-weight:600;"
          @click="acceptConsent"
          :disabled="!accepted || saving"
        >
          {{ saving ? '⏳ Desant...' : '✅ He llegit i comprenc l\'avís — Continuar' }}
        </button>
        <div class="text-small text-muted" style="text-align:center;margin-top:12px;line-height:1.5;">
          Responsable: Centre de Rehabilitació Terapèutica (NIF PENDENT) · DPD: dpd@crtbcn.cat · Reg. (UE) 2016/679 · LOPDGDD 3/2018 · RDL 8/2019
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../stores/auth'
import { db } from '../services/db'
import { auditLog } from '../services/audit'

const emit = defineEmits(['accepted'])
const authStore = useAuthStore()
const accepted = ref(false)
const saving = ref(false)

async function acceptConsent() {
  if (!accepted.value || saving.value) return
  saving.value = true
  try {
    const updatedUser = await db.acceptGeoConsent()
    if (authStore.user) authStore.user.geo_consent_accepted_at = updatedUser.geo_consent_accepted_at
  } catch (e) {
    // API failed (e.g. migration pending) — fallback to localStorage so the user isn't blocked
    console.warn('[GeoConsent] API unavailable, using localStorage fallback:', e)
    localStorage.setItem(`crt_geo_consent_${authStore.userId}`, JSON.stringify({
      accepted: true, date: new Date().toISOString(), fallback: true
    }))
    if (authStore.user) authStore.user.geo_consent_accepted_at = new Date().toISOString()
  } finally {
    saving.value = false
    auditLog(authStore.userId, 'ACCEPT_GEO_CONSENT', 'privacy', authStore.userId,
      `Acceptació avís geolocalització: ${authStore.userName}`)
    emit('accepted')
  }
}
</script>

<style scoped>
.geo-consent-overlay {
  position: fixed; inset: 0; z-index: 100001;
  background: rgba(0,0,0,0.75);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
  animation: fadeIn 0.3s ease;
}

.geo-consent-container {
  background: var(--color-bg-card, #fff);
  border-radius: 18px;
  width: 100%; max-width: 720px;
  max-height: 92vh;
  display: flex; flex-direction: column;
  overflow: hidden;
  box-shadow: 0 24px 80px rgba(0,0,0,0.5);
}

.geo-consent-header {
  padding: 24px;
  border-bottom: 2px solid var(--color-primary);
  background: linear-gradient(135deg, rgba(0,107,91,0.04), rgba(59,130,246,0.04));
}

.geo-consent-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
}

.geo-consent-footer {
  padding: 20px 24px;
  border-top: 1px solid var(--color-border-light);
  background: var(--color-bg, #f5f7fa);
}

@media (max-width: 600px) {
  .geo-consent-container {
    max-height: 100vh;
    border-radius: 0;
  }
  .geo-consent-header { padding: 16px; }
  .geo-consent-body { padding: 16px; }
  .geo-consent-footer { padding: 16px; }
}
</style>
