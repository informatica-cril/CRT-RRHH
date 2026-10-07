<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Procediment disciplinari</h1>
        <p class="page-subtitle">El sistema acredita i proposa; la qualificació i la sanció les decideix i signa una persona (RGPD art. 22)</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="obrintCas = !obrintCas">+ Obrir cas</button>
      </div>
    </div>

    <div v-if="error" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <div class="text-small" style="color:var(--color-danger);">{{ error }}</div>
    </div>

    <!-- Suggeriments d'obertura: la recollida proposa, la persona decideix -->
    <div v-if="!cas && suggeriments.length" class="card mb-lg" style="border-left:4px solid var(--color-warning);">
      <div class="card-header"><h3 class="card-title">💡 Suggeriments d'obertura ({{ suggeriments.length }}) — revisió humana</h3></div>
      <div class="text-small text-muted" style="margin-bottom:8px;">Dades suficients acumulades. Cap cas s'obre sol: obrir-lo (o descartar-ho) és decisió vostra.</div>
      <div v-for="(s, i) in suggeriments" :key="i" style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 0;border-top:1px solid var(--color-border,#eee);">
        <div class="text-small">
          <b>{{ s.professional }}</b> · {{ s.motiu }}
          <span v-if="s.base_legal" class="text-muted"> ({{ s.base_legal }})</span>
        </div>
        <button class="btn btn-sm btn-primary" @click="aplicarSuggeriment(s)">Obrir cas amb aquests {{ s.element_ids.length }} fets</button>
      </div>
    </div>

    <!-- Evidència objectiva de domi → elements (fonamentació del procediment) -->
    <div v-if="!cas" class="card mb-lg">
      <div class="card-header">
        <h3 class="card-title">📊 Evidència objectiva (domi) → elements</h3>
        <button class="btn btn-sm" @click="mostrarEvidencia = !mostrarEvidencia">{{ mostrarEvidencia ? 'Amagar' : 'Mostrar' }}</button>
      </div>
      <template v-if="mostrarEvidencia">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
          <div>
            <label class="text-small text-muted">Professional (domi)</label>
            <input class="form-input" v-model.trim="evi.professional" placeholder="p. ex. A.Martinez" style="width:200px;" />
          </div>
          <div><label class="text-small text-muted">Des de</label><input class="form-input" type="date" v-model="evi.desde" /></div>
          <div><label class="text-small text-muted">Fins</label><input class="form-input" type="date" v-model="evi.fins" /></div>
          <button class="btn btn-primary" :disabled="eviLoading || !evi.professional" @click="carregarEvidencia">{{ eviLoading ? 'Carregant…' : 'Carregar fets' }}</button>
        </div>
        <div v-if="eviDorment" class="text-small" style="color:var(--color-warning);margin-top:8px;">⚙ El motor de recollida està inactiu a domi (kill-switch).</div>
        <template v-if="eviFets.length">
          <div class="text-small text-muted" style="margin:10px 0 6px;">Seleccioneu els fets a incorporar i confirmeu-ne la imputabilitat (el que és «no» o «condicional» no computa per reincidència). El coneixement de l'empresa arrenca en incorporar-los (avui).</div>
          <table style="width:100%;border-collapse:collapse;">
            <thead><tr class="text-small text-muted" style="text-align:left;"><th style="padding:6px;"><input type="checkbox" @change="e => eviFets.forEach(f => f.sel = e.target.checked)" /></th><th>Dia</th><th>Indicador</th><th>Detall</th><th>Falta proposada</th><th>Imputable</th></tr></thead>
            <tbody>
              <tr v-for="(f, i) in eviFets" :key="i" style="border-top:1px solid var(--color-border,#eee);" class="text-small">
                <td style="padding:6px;"><input type="checkbox" v-model="f.sel" /></td>
                <td>{{ f.dia }}</td>
                <td>{{ f.bloc }}</td>
                <td>{{ f.detall }}</td>
                <td>{{ f.tipus_falta }}</td>
                <td>
                  <select class="form-select" v-model="f.imputable" style="padding:2px 6px;font-size:0.78rem;">
                    <option value="si">sí</option><option value="condicional">condicional</option><option value="no">no</option>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
          <button class="btn btn-primary" style="margin-top:10px;" :disabled="!eviFets.some(f => f.sel)" @click="incorporarFets">
            Incorporar {{ eviFets.filter(f => f.sel).length }} fets com a elements
          </button>
        </template>
      </template>
    </div>

    <!-- Obrir cas -->
    <div v-if="obrintCas" class="card mb-lg">
      <div class="card-header"><h3 class="card-title">Nou cas disciplinari</h3></div>
      <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));">
        <div>
          <label class="text-small text-muted">Professional (identificador domi)</label>
          <input class="form-input" v-model.trim="nou.professional" placeholder="p. ex. A.Martinez" @change="autoVincula" />
        </div>
        <div>
          <label class="text-small text-muted">Treballador (usuari de l'app — per a la notificació in-app)</label>
          <select class="form-select" v-model="nou.user_id">
            <option :value="null">— vincular després —</option>
            <option v-for="w in workers" :key="w.id" :value="w.id">
              {{ w.name }}<template v-if="w.domi_username"> ({{ w.domi_username }})</template><template v-if="w.relacio === 'autonom'"> — autònom: via laboral vetada</template>
            </option>
          </select>
          <span v-if="autoVinculat" class="text-small" style="color:var(--color-success);">✓ vinculat automàticament pel mapeig domi</span>
        </div>
        <div>
          <label class="text-small text-muted">Vincle</label>
          <select class="form-select" v-model="nou.vincle">
            <option value="laboral">Laboral</option>
            <option value="autonom">Autònom (vetat)</option>
          </select>
        </div>
        <div>
          <label class="text-small text-muted">Tipus de falta (conveni + ET)</label>
          <select class="form-select" v-model="nou.tipus_falta">
            <option v-for="t in faultTypes" :key="t.clau" :value="t.clau">{{ t.descripcio }} ({{ t.grau_base }})</option>
          </select>
        </div>
        <div>
          <label class="text-small text-muted">Data de coneixement (arrenca la prescripció)</label>
          <input class="form-input" type="date" v-model="nou.data_coneixement" />
        </div>
        <div>
          <label class="text-small text-muted">Data del fet (límit dur 6 mesos)</label>
          <input class="form-input" type="date" v-model="nou.data_fet" />
        </div>
      </div>
      <label class="text-small" style="display:flex;gap:8px;align-items:center;margin-top:10px;">
        <input type="checkbox" v-model="nou.es_representant" /> És representant dels treballadors (exigeix expedient contradictori)
      </label>
      <!--
        Veto d'autònom: el backend el mira per DUES vies —el camp «vincle» del formulari i la
        RELACIÓ que consta a la fitxa del titular a RRHH—. La segona no es comprovava aquí i el
        botó acabava en 422. Ara el control queda desactivat amb el motiu escrit.
      -->
      <div v-if="motiuVetat" class="text-small" style="color:var(--color-danger);margin-top:8px;">
        ⚠ {{ motiuVetat }}
      </div>
      <div v-else-if="mancaPerObrir" class="text-small text-muted" style="margin-top:8px;">
        Falta per poder obrir el cas: {{ mancaPerObrir }}.
      </div>
      <div style="display:flex;gap:8px;margin-top:12px;">
        <button class="btn btn-primary" :disabled="creant || !!motiuVetat || !!mancaPerObrir"
                :title="motiuVetat || mancaPerObrir || ''" @click="crearCas">
          {{ creant ? 'Obrint…' : 'Obrir cas' }}
        </button>
        <button class="btn" @click="obrintCas = false">Cancel·lar</button>
      </div>
    </div>

    <!-- Llista de casos -->
    <div v-if="!cas" class="card">
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr class="text-small text-muted" style="text-align:left;">
            <th style="padding:8px;">Professional</th><th>Falta</th><th>Gravetat</th><th>Estat</th><th>Prescripció</th><th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in casos" :key="c.id" style="border-top:1px solid var(--color-border,#eee);">
            <td style="padding:8px;">{{ c.professional }}</td>
            <td class="text-small">{{ c.tipus_falta }}</td>
            <td class="text-small">{{ c.gravetat }}</td>
            <td><span :style="estatStyle(c.estat)">{{ c.estat }}</span></td>
            <td class="text-small" :style="{color: c.dies_prescripcio!==null && c.dies_prescripcio<0 ? 'var(--color-danger)' : (c.dies_prescripcio!==null && c.dies_prescripcio<=7 ? 'var(--color-warning)' : '')}">
              {{ c.dies_prescripcio===null ? '—' : (c.dies_prescripcio<0 ? 'PRESCRITA' : c.dies_prescripcio+' dies') }}
            </td>
            <td style="text-align:right;"><button class="btn btn-sm" @click="obrir(c)">Obrir</button></td>
          </tr>
          <tr v-if="!casos.length"><td colspan="6" class="text-small text-muted" style="padding:12px;">Cap cas obert. El flux: element(s) imputables → cas → instrucció → comunicació → al·legacions → resolució signada → execució.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Detall del cas -->
    <template v-if="cas">
      <div class="card mb-lg">
        <div class="card-header">
          <h3 class="card-title">Cas #{{ cas.id }} · {{ cas.professional }} · {{ cas.tipus_falta }} ({{ cas.gravetat }})</h3>
          <button class="btn btn-sm" @click="cas = null">← Tornar a la llista</button>
        </div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;" class="text-small">
          <span>Estat: <b :style="{color:'var(--color-primary)'}">{{ cas.estat }}</b></span>
          <span>Coneixement: {{ cas.data_coneixement?.substring(0,10) }}</span>
          <span>Prescriu: <b>{{ cas.data_prescripcio?.substring(0,10) || '—' }}</b>
            ({{ cas.dies_prescripcio===null ? '—' : (cas.dies_prescripcio<0 ? 'PRESCRITA' : cas.dies_prescripcio+' dies') }})</span>
          <span v-if="cas.reincidencia?.llindar">Reincidència: <b>{{ cas.reincidencia.n }}/{{ cas.reincidencia.llindar }}</b> {{ cas.reincidencia.acreditada ? '· ACREDITADA' : '' }}</span>
        </div>
      </div>

      <!-- Raonament jurídic: per què aquest cas és com és -->
      <div v-if="cas.raonament" class="card mb-lg" style="border-left:4px solid var(--color-primary);">
        <div class="card-header"><h3 class="card-title">⚖ Per què això és així</h3></div>
        <div class="text-small" style="line-height:1.7;display:grid;gap:12px;">
          <div>
            <b>Norma que mana.</b><br>{{ cas.raonament.jerarquia }}
          </div>
          <div>
            <b>La falta.</b><br>
            <span :style="{color: cas.raonament.falta.ok ? '' : 'var(--color-danger)'}">{{ cas.raonament.falta.text }}</span>
          </div>
          <div>
            <b>Què es pot imposar.</b><br>
            <span :style="{color: cas.raonament.sancio.ok ? '' : 'var(--color-danger)'}">{{ cas.raonament.sancio.text }}</span>
          </div>
          <div>
            <b>Terminis.</b><br>{{ cas.raonament.terminis.text }}
            <div v-for="(a, i) in cas.raonament.terminis.avisos" :key="i"
                 :style="{marginTop:'8px',padding:'9px 13px',borderRadius:'8px',
                          background: a.startsWith('PRESCRIT') ? 'var(--color-danger-bg, #fdecec)' : 'var(--color-warning-bg, #fdf3e2)',
                          color: a.startsWith('PRESCRIT') ? 'var(--color-danger)' : 'var(--color-warning)',
                          fontWeight:'600'}">{{ a }}</div>
          </div>
          <div v-if="cas.raonament.obligacions.length">
            <b>Obligacions que porta aquest cas.</b>
            <div v-for="o in cas.raonament.obligacions" :key="o.clau"
                 style="display:flex;gap:8px;align-items:start;margin-top:7px;">
              <span :style="{color: o.compleix ? 'var(--color-success)' : 'var(--color-warning)'}">{{ o.compleix ? '✓' : '!' }}</span>
              <span>{{ o.text }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Garanties -->
      <div class="card mb-lg">
        <div class="card-header"><h3 class="card-title">🛡 Garanties (portes del procediment)</h3></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:8px;">
          <div v-for="g in cas.garanties" :key="g.clau" class="text-small" style="display:flex;gap:8px;align-items:center;padding:8px;background:var(--color-bg);border-radius:8px;">
            <span :style="{color: g.ok ? 'var(--color-success)' : 'var(--color-danger)'}">{{ g.ok ? '✓' : '✗' }}</span>
            <span>{{ g.detall }}</span>
          </div>
        </div>
        <!-- Instrucció: confirmacions humanes -->
        <div v-if="!['resolt','executat','arxivat'].includes(cas.estat)" style="display:flex;gap:16px;flex-wrap:wrap;margin-top:12px;" class="text-small">
          <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" v-model="cas.te_evidencia_licita" @change="desarInstruccio({te_evidencia_licita: cas.te_evidencia_licita})" /> Evidència amb licitud verificada</label>
          <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" v-model="cas.afectacio_servei_acreditada" @change="desarInstruccio({afectacio_servei_acreditada: cas.afectacio_servei_acreditada, afectacio_confirmada_per: usuari})" /> Afectació al servei confirmada (per {{ usuari }})</label>
          <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" v-model="cas.apercebiment_previ" @change="desarInstruccio({apercebiment_previ: cas.apercebiment_previ})" /> Apercebiment previ (rendiment)</label>
          <label v-if="cas.gravetat==='molt_greu'" style="display:flex;gap:6px;align-items:center;">
            <input type="checkbox" :checked="!!cas.audiencia_rlt_ts" @change="e => desarInstruccio({audiencia_rlt_ts: e.target.checked ? new Date().toISOString() : null})" /> Audiència a la RLT feta
          </label>
          <label v-if="cas.es_representant" style="display:flex;gap:6px;align-items:center;"><input type="checkbox" v-model="cas.expedient_contradictori" @change="desarInstruccio({expedient_contradictori: cas.expedient_contradictori})" /> Expedient contradictori</label>
        </div>
      </div>

      <!-- Fets del cas (la fonamentació objectiva) -->
      <div class="card mb-lg">
        <div class="card-header"><h3 class="card-title">📌 Fets que fonamenten el cas ({{ cas.elements?.length || 0 }})</h3></div>
        <table v-if="cas.elements?.length" style="width:100%;border-collapse:collapse;">
          <thead><tr class="text-small text-muted" style="text-align:left;"><th style="padding:6px;">Data del fet</th><th>Coneixement</th><th>Descripció</th><th>Font de la prova</th><th>Origen</th><th>Imputable</th><th>Al plec</th></tr></thead>
          <tbody>
            <tr v-for="el in cas.elements" :key="el.id" style="border-top:1px solid var(--color-border,#eee);" class="text-small">
              <td style="padding:6px;">{{ el.data_fet?.substring(0,10) }}</td>
              <td>{{ el.data_coneixement?.substring(0,10) }}</td>
              <td>{{ el.descripcio }}<span v-if="el.valor"> ({{ el.valor }})</span></td>
              <td>{{ el.font }}</td>
              <!-- L'origen de la prova es veu a la pantalla i s'imprimeix al plec: un indicador
                   teclejat a mà no pot passar per registre de l'aplicació assistencial. -->
              <td :style="{color: el.origen==='pont' ? 'var(--color-success)' : (el.origen==='manual' ? 'var(--color-warning)' : 'var(--color-danger)'), fontWeight:700}">
                {{ el.origen==='pont' ? 'automàtic (domi)' : (el.origen==='manual' ? 'manual (RRHH)' : 'no acreditat') }}
              </td>
              <td :style="{color: el.imputable==='si' ? 'var(--color-success)' : (el.imputable==='no' ? 'var(--color-danger)' : 'var(--color-warning)'), fontWeight:700}">{{ el.imputable }}</td>
              <!-- Aptitud per al plec. Una prova d'origen no acreditat no entra sola a cap escrit;
                   per imputar-la cal dir per escrit què n'acredita l'origen, i queda a l'auditoria. -->
              <td>
                <span v-if="el.apte_plec === false" style="color:var(--color-danger);font-weight:700;">fora del plec</span>
                <span v-else-if="el.apte_plec_ts" style="color:var(--color-warning);font-weight:700;" :title="el.apte_plec_motiu">rehabilitada</span>
                <span v-else class="text-muted">—</span>
                <button v-if="esAdmin && (el.apte_plec === false || el.apte_plec_ts)" class="btn btn-sm"
                  style="margin-left:6px;" @click="canviaAptitud(el)">
                  {{ el.apte_plec === false ? 'Rehabilitar…' : 'Tornar a bloquejar…' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-else class="text-small" style="color:var(--color-warning);">
          ⚠ Cap fet incorporat: un escrit sense fets concrets amb data i font és impugnable per indefensió.
          Incorporeu evidència des de «Evidència objectiva (domi)» abans de generar escrits.
        </div>
      </div>

      <!-- Tràmit d'audiència: què ha fet el treballador amb el seu termini -->
      <div class="card mb-lg" :style="{borderLeft: '4px solid ' + (cas.alegacions_ts ? 'var(--color-primary)' : 'var(--color-warning)')}">
        <div class="card-header"><h3 class="card-title">🗣 Tràmit d'audiència</h3></div>
        <div class="text-small" style="line-height:1.7;">
          <div v-if="!cas.comunicat_ts">Encara no s'ha comunicat res al treballador: el termini no ha començat.</div>
          <template v-else>
            <div>Comunicat el <b>{{ fmt(cas.comunicat_ts) }}</b> · termini fins al
              <b>{{ cas.termini_alegacions?.substring(0,10) || '—' }}</b></div>
            <div v-if="cas.alegacions_ts" style="margin-top:8px;">
              <b style="color:var(--color-primary);">Al·legacions presentades pel treballador el {{ fmt(cas.alegacions_ts) }}</b>
              <div v-if="cas.alegacions_text" style="margin-top:6px;padding:10px;background:var(--color-bg);border-radius:8px;white-space:pre-wrap;">{{ cas.alegacions_text }}</div>
              <div v-if="cas.alegacions_adjunt_nom" class="text-muted" style="margin-top:6px;">📎 adjunt: {{ cas.alegacions_adjunt_nom }}</div>
            </div>
            <div v-else-if="cas.renuncia_termini_ts" style="margin-top:8px;color:var(--color-warning);">
              El treballador va <b>renunciar expressament</b> al termini el {{ fmt(cas.renuncia_termini_ts) }}.
            </div>
            <div v-else style="margin-top:8px;color:var(--color-warning);">
              Sense al·legacions. Fins que el termini no venci —o el treballador no hi renunciï ell
              mateix— l'expedient no es pot resoldre (ET 55.1, art. 55.K.6 del conveni).
            </div>
          </template>
        </div>
      </div>

      <!-- Transicions -->
      <div class="card mb-lg">
        <div class="card-header"><h3 class="card-title">Flux del procediment</h3></div>
        <div class="text-small text-muted" style="margin-bottom:10px;">esborrany → instrucció → comunicat → al·legacions → resolt (signat per persona) → executat · o arxivat en qualsevol moment</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button v-for="t in cas.transicions" :key="t.estat" class="btn btn-sm" :class="{'btn-primary': t.ok}"
                  :disabled="!t.ok || t.estat===cas.estat" :title="t.ok ? '' : t.motiu"
                  @click="ferTransicio(t.estat)">
            {{ t.estat }}<span v-if="!t.ok"> 🔒</span>
          </button>
        </div>
        <!--
          El motiu del bloqueig es veu SEMPRE, no només en passar-hi el ratolí: aquesta pantalla
          es fa servir també en tauleta, on no hi ha «hover», i un botó apagat sense explicació
          fa pensar a qui el mira que ha fet alguna cosa malament.
        -->
        <div v-if="transicionsBloquejades.length" style="margin-top:10px;display:grid;gap:4px;">
          <div v-for="t in transicionsBloquejades" :key="t.estat" class="text-small text-muted">
            🔒 <b>{{ t.estat }}</b> — {{ t.motiu }}
          </div>
        </div>
        <div v-if="casTancat" class="text-small" style="margin-top:8px;color:var(--color-warning);">
          Aquest expedient està tancat en «{{ cas.estat }}»: l'historial és immutable i no es pot
          reobrir. Si calen actuacions noves sobre els mateixos fets, s'ha d'obrir un cas nou.
        </div>

        <!-- Resolució (humana) -->
        <div v-if="mostrarResolucio" style="margin-top:14px;padding:12px;background:var(--color-bg);border-radius:8px;">
          <div class="text-small" style="font-weight:700;margin-bottom:8px;">Resolució — la signa {{ usuari }} (persona, no el sistema)</div>
          <div style="display:grid;gap:10px;grid-template-columns:220px 1fr;">
            <select class="form-select" v-model="resolucio.tipus">
              <option value="amonestacio">Amonestació</option>
              <option value="suspensio">Suspensió de sou i feina</option>
              <option value="trasllat">Trasllat</option>
              <option value="inhabilitacio">Inhabilitació</option>
              <option value="acomiadament">Acomiadament</option>
              <option value="arxiu">Arxiu (sense sanció)</option>
            </select>
            <textarea class="form-input" v-model="resolucio.motivacio" rows="3" placeholder="Motivació de la resolució (obligatòria; la redacta la persona que signa)"></textarea>
          </div>
          <div style="display:flex;gap:8px;margin-top:10px;">
            <button class="btn btn-primary" :disabled="!resolucio.motivacio.trim()" @click="signarResolucio">Signar resolució</button>
            <button class="btn" @click="mostrarResolucio=false">Cancel·lar</button>
          </div>
        </div>
      </div>

      <!-- Documents -->
      <div class="card mb-lg">
        <div class="card-header">
          <h3 class="card-title">Documents del cas</h3>
          <div style="display:flex;gap:6px;">
            <select class="form-select" v-model="nouDocTipus" style="width:auto;">
              <option value="apercebiment">Apercebiment</option>
              <option value="amonestacio">Amonestació</option>
              <option value="plec_carrecs">Plec de càrrecs</option>
              <option value="resolucio">Resolució</option>
              <option value="carta_acomiadament">Carta d'acomiadament</option>
            </select>
            <button class="btn btn-sm btn-primary" @click="generarDocument">Generar esborrany</button>
          </div>
        </div>
        <div v-for="d in cas.documents" :key="d.id" style="border-top:1px solid var(--color-border,#eee);padding:10px 0;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
            <div class="text-small"><b>{{ d.tipus }}</b> · <span :style="estatStyle(d.estat)">{{ d.estat }}</span>
              <span v-if="d.signat_per"> · signat per {{ d.signat_per }}</span>
              <span v-if="d.notificat_ts"> · notificat {{ fmt(d.notificat_ts) }}</span>
              <span v-if="d.acus_ts" style="color:var(--color-success);font-weight:700;"> · ✓ acusat {{ fmt(d.acus_ts) }}</span>
              <span v-else-if="d.notificat_ts" style="color:var(--color-warning);font-weight:700;"> · pendent d'acusament</span></div>
            <div style="display:flex;gap:6px;">
              <button v-if="d.estat==='esborrany'" class="btn btn-sm" @click="editantDoc = editantDoc===d.id ? null : d.id">{{ editantDoc===d.id ? 'Tancar' : 'Editar' }}</button>
              <button v-if="d.estat==='esborrany'" class="btn btn-sm btn-primary" @click="signarDocument(d)">Signar (humà)</button>
              <button v-if="d.estat!=='esborrany'" class="btn btn-sm" @click="verificarDocument(d)">Verificar integritat</button>
            </div>
          </div>
          <!-- Un escrit signat que NO és el que va generar el motor s'ha de veure com a tal:
               el text que rep el treballador és el que val, i qui el revisi ha de saber-ho. -->
          <div v-if="d.apartat_del_motor" class="text-small" style="margin-top:6px;color:var(--color-danger);font-weight:700;">
            ⚠ Text reescrit a mà: el que es va signar no és l'escrit que va generar el motor.
          </div>
          <div v-if="d.contingut_hash" class="text-small text-muted" style="margin-top:4px;font-family:monospace;">
            empremta signada: {{ d.contingut_hash }}
          </div>
          <div v-if="verificacions[d.id]" class="text-small" style="margin-top:6px;padding:9px 13px;border-radius:8px;"
               :style="{background: verificacions[d.id].integre ? 'var(--color-success-bg,#e8f5ee)' : 'var(--color-danger-bg,#fdecec)',
                        color: verificacions[d.id].integre ? 'var(--color-success)' : 'var(--color-danger)', fontWeight:600}">
            {{ verificacions[d.id].motiu }}
          </div>
          <div v-if="editantDoc!==d.id" class="doc-paper" style="margin-top:8px;">
            <img src="/assets/crt-logo.png" alt="CRT" style="height:36px;margin-bottom:14px;" />
            <div class="doc-body" v-html="renderDoc(d.contingut)"></div>
          </div>
          <div v-else style="margin-top:6px;">
            <textarea class="form-input" v-model="d.contingut" rows="18" style="width:100%;font-family:monospace;font-size:0.8rem;"></textarea>
            <button class="btn btn-sm btn-primary" style="margin-top:6px;" @click="desarDocument(d)">Desar canvis</button>
          </div>
        </div>
        <div v-if="!cas.documents?.length" class="text-small text-muted">Cap document. Genereu l'esborrany i reviseu-lo abans de signar.</div>
      </div>

      <!-- Historial -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Historial (cadena de custòdia, només-afegir)</h3></div>
        <table style="width:100%;border-collapse:collapse;">
          <thead><tr class="text-small text-muted" style="text-align:left;"><th style="padding:6px;">Quan</th><th>Transició</th><th>Actor</th><th>Nota</th></tr></thead>
          <tbody>
            <tr v-for="ev in cas.events" :key="ev.id" style="border-top:1px solid var(--color-border,#eee);" class="text-small">
              <td style="padding:6px;white-space:nowrap;">{{ fmt(ev.created_at) }}</td>
              <td>{{ ev.estat_de || '·' }} → {{ ev.estat_a }}</td>
              <td>{{ ev.actor }}</td>
              <td>{{ ev.nota }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import { confirma, demana } from '../utils/dialegs'
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import api from '../services/apiClient'

const authStore = useAuthStore()
const usuari = computed(() => authStore.user?.name || '')
const esAdmin = computed(() => authStore.user?.role === 'admin')

const casos = ref([])
const faultTypes = ref([])
const cas = ref(null)
const error = ref('')
const obrintCas = ref(false)
const creant = ref(false)
const mostrarResolucio = ref(false)
const resolucio = ref({ tipus: 'amonestacio', motivacio: '' })
const nouDocTipus = ref('amonestacio')
const editantDoc = ref(null)
const verificacions = ref({})

const avui = new Date().toISOString().substring(0, 10)
const d90 = new Date(Date.now() - 90 * 86400000).toISOString().substring(0, 10)
const nou = ref({ professional: '', user_id: null, vincle: 'laboral', tipus_falta: '', data_coneixement: avui, data_fet: avui, es_representant: false, element_ids: [] })

// Suggeriments + treballadors + evidència de domi
const suggeriments = ref([])
const workers = ref([])
const mostrarEvidencia = ref(false)
const evi = ref({ professional: '', desde: d90, fins: avui })
const eviRrhhUserId = ref(null)
const eviFets = ref([])
const eviDorment = ref(false)
const eviLoading = ref(false)

async function load() {
  try {
    casos.value = await api.get('/v1/disciplinary/cases')
    if (!faultTypes.value.length) faultTypes.value = await api.get('/v1/disciplinary/fault-types')
    suggeriments.value = await api.get('/v1/disciplinary/suggestions')
    if (!workers.value.length) {
      const us = await api.get('/v1/users').catch(() => [])
      workers.value = (Array.isArray(us) ? us : (us?.data || [])).filter(u => u.role === 'worker')
    }
  } catch (e) { error.value = e?.message || 'Error carregant casos.' }
}

// ── Controls alineats amb les garanties del backend ──────────────────────────
// Cap d'aquestes comprovacions substitueix la del servidor: són l'espill que evita oferir un botó
// que el servidor rebutjarà (i, quan es desactiva, dir-ne el motiu en cristià).

/** Titular seleccionat al formulari d'obertura (null si encara no se n'ha triat cap). */
const titularSeleccionat = computed(() => workers.value.find(w => w.id === nou.value.user_id) || null)

/**
 * Motiu pel qual la via disciplinària LABORAL està vetada, o '' si no ho està.
 * Espill de DisciplinaryController@store: veta tant el vincle marcat al formulari com la relació
 * que consta a la fitxa del treballador a RRHH.
 */
const motiuVetat = computed(() => {
  if (nou.value.vincle === 'autonom') {
    return 'Vincle mercantil: la via disciplinària laboral està vetada. Gestioneu-ho com a '
         + 'incompliment contractual (mercantil), fora d\'aquest mòdul.'
  }
  if (titularSeleccionat.value?.relacio === 'autonom') {
    return `${titularSeleccionat.value.name} consta com a COL·LABORADOR AUTÒNOM a la seva fitxa de `
         + 'RRHH: no se li pot obrir expedient disciplinari laboral. Gestioneu-ho com a '
         + 'incompliment contractual (mercantil), fora d\'aquest mòdul.'
  }
  return ''
})

/** Camps obligatoris que encara falten, en llenguatge natural ('' si no en falta cap). */
const mancaPerObrir = computed(() => {
  const falta = []
  if (!nou.value.professional) falta.push('l\'identificador del professional')
  if (!nou.value.tipus_falta) falta.push('el tipus de falta')
  if (!nou.value.data_coneixement) falta.push('la data de coneixement')
  return falta.join(', ')
})

/** Transicions que el backend bloqueja ara mateix, amb el seu motiu (per ensenyar-lo a la vista). */
const transicionsBloquejades = computed(
  () => (cas.value?.transicions || []).filter(t => !t.ok && t.estat !== cas.value.estat)
)

/** Expedient tancat: l'historial és append-only i no es reobre (només un cas nou). */
const casTancat = computed(() => ['resolt', 'executat', 'arxivat'].includes(cas.value?.estat))

/** Vincula automàticament el treballador si el seu domi_username coincideix (mapeig per DNI). */
const autoVinculat = ref(false)
function autoVincula() {
  const w = workers.value.find(x => x.domi_username && x.domi_username === nou.value.professional)
  if (w && !nou.value.user_id) { nou.value.user_id = w.id; autoVinculat.value = true }
  else autoVinculat.value = false
}

/** Obre el formulari de cas prefixat amb els elements del suggeriment (decisió humana). */
function aplicarSuggeriment(s) {
  nou.value = {
    professional: s.professional, user_id: s.user_id || null, vincle: 'laboral',
    tipus_falta: s.tipus_falta, data_coneixement: avui, data_fet: avui,
    es_representant: false, element_ids: s.element_ids,
  }
  obrintCas.value = true
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

/** Carrega els fets detallats de domi (F1.bis) i els aplana per a la taula de selecció. */
async function carregarEvidencia() {
  eviLoading.value = true; error.value = ''; eviFets.value = []; eviDorment.value = false
  try {
    const qs = new URLSearchParams({ desde: evi.value.desde, fins: evi.value.fins }).toString()
    const r = await api.get(`/v1/expedient/${encodeURIComponent(evi.value.professional)}/detall?${qs}`)
    if (r?.dorment || r?.recollida?.dorment) { eviDorment.value = true; return }
    eviRrhhUserId.value = r?.rrhh_user_id || null // ID corporatiu que arriba de domi (autoritari)
    const blocs = { retards: 'Retard (marcatge)', durades: 'Durada anòmala', reprogramacions: 'Reprogramació', tecniques_no: 'Tècnica no aplicada' }
    const out = []
    for (const [k, label] of Object.entries(blocs)) {
      // Es guarda la POSICIÓ del fet dins del seu bloc: és el que el servidor farà servir per anar a
      // buscar-lo a domi i copiar-ne font i magnitud d'allà. El client ja no les envia: si les
      // enviés, un origen automàtic es podria teclejar.
      ;(r?.recollida?.[k] || []).forEach((it, idx) => {
        const detall = it.retard_min ? `${it.retard_min} min` : (it.minuts ? `${it.minuts} min` : (it.motiu || it.nota || it.estat || ''))
        out.push({ sel: false, dia: it.dia, bloc: k, blocLabel: label, idx, detall: String(detall),
                   tipus_falta: it.tipus_falta || 'negligencia_servei', imputable: it.imputable || 'condicional' })
      })
    }
    eviFets.value = out
    if (!out.length) error.value = 'Cap fet en aquest període per a aquest professional.'
  } catch (e) { error.value = e?.message || 'Error carregant l\'evidència de domi.' }
  finally { eviLoading.value = false }
}

/** Incorpora els fets seleccionats com a elements (imputabilitat confirmada per la persona). */
async function incorporarFets() {
  error.value = ''
  const items = eviFets.value.filter(f => f.sel).map(f => ({
    bloc: f.bloc, idx: f.idx, dia: f.dia, tipus_falta: f.tipus_falta, imputable: f.imputable,
  }))
  try {
    const r = await api.post('/v1/disciplinary/elements/bulk', {
      professional: evi.value.professional, user_id: eviRrhhUserId.value,
      desde: evi.value.desde, fins: evi.value.fins, items,
    })
    eviFets.value = eviFets.value.filter(f => !f.sel)
    error.value = ''
    await load()
    alert(`Incorporats ${r.creats} elements`
      + (r.omesos_duplicats ? ` (${r.omesos_duplicats} duplicats omesos)` : '')
      + (r.rebutjats?.length ? `\n${r.rebutjats.length} fets NO incorporats: no consten a la recollida de domi.` : ''))
  } catch (e) { error.value = e?.message || 'No s\'han pogut incorporar.' }
}

async function obrir(c) {
  error.value = ''
  try { cas.value = await api.get(`/v1/disciplinary/cases/${c.id}`) }
  catch (e) { error.value = e?.message || 'Error obrint el cas.' }
}

/**
 * Decisió expressa sobre una prova d'origen no acreditat. La motivació és obligatòria i el servidor
 * la torna a exigir: aquí es demana perquè qui decideix la pugui escriure, no com a validació.
 */
async function canviaAptitud(el) {
  const rehabilitar = el.apte_plec === false
  const motiu = await demana(rehabilitar
    ? 'Aquesta prova ve de l\'espai del pont però no consta d\'on surt, i la recollida de domi no la conté.\n\nPer imputar-la cal dir QUÈ n\'acredita l\'origen (mínim 20 caràcters). Quedarà a l\'auditoria amb el vostre nom:'
    : 'Motiu per tornar a deixar aquesta prova fora del plec (mínim 20 caràcters):')
  if (!motiu || motiu.trim().length < 20) {
    if (motiu !== null) error.value = 'La motivació ha de tenir com a mínim 20 caràcters: no s\'ha canviat res.'
    return
  }
  error.value = ''
  try {
    await api.post(`/v1/disciplinary/elements/${el.id}/rehabilita`, { apte: rehabilitar, motiu: motiu.trim() })
    if (cas.value) await obrir(cas.value)
  } catch (e) { error.value = e?.message || 'No s\'ha pogut canviar l\'aptitud de la prova.' }
}

async function crearCas() {
  if (motiuVetat.value) { error.value = motiuVetat.value; return }
  creant.value = true; error.value = ''
  try {
    const created = await api.post('/v1/disciplinary/cases', nou.value)
    obrintCas.value = false
    await load(); await obrir(created)
  } catch (e) { error.value = e?.message || 'No s\'ha pogut obrir el cas.' }
  finally { creant.value = false }
}

async function desarInstruccio(patch) {
  error.value = ''
  try { const updated = await api.put(`/v1/disciplinary/cases/${cas.value.id}`, patch); await obrir(updated) }
  catch (e) { error.value = e?.message || 'No s\'ha pogut desar.'; await obrir(cas.value) }
}

async function ferTransicio(estat) {
  // Espill de la guarda del motor: si el backend ja diu que aquesta porta està tancada, no s'hi
  // truca — s'ensenya el motiu que ell mateix ha escrit (mai un «error 422»).
  const t = (cas.value?.transicions || []).find(x => x.estat === estat)
  if (t && !t.ok) { error.value = t.motiu; return }
  if (estat === 'resolt') { mostrarResolucio.value = true; return }
  error.value = ''
  try { await api.post(`/v1/disciplinary/cases/${cas.value.id}/transition`, { estat }); await obrir(cas.value); await load() }
  catch (e) { error.value = e?.message || 'Transició bloquejada.' }
}

async function signarResolucio() {
  error.value = ''
  try {
    await api.post(`/v1/disciplinary/cases/${cas.value.id}/transition`, {
      estat: 'resolt', resolucio_tipus: resolucio.value.tipus, resolucio_motivacio: resolucio.value.motivacio,
    })
    mostrarResolucio.value = false
    await obrir(cas.value); await load()
  } catch (e) { error.value = e?.message || 'Resolució bloquejada.' }
}

async function generarDocument() {
  error.value = ''
  try { await api.post(`/v1/disciplinary/cases/${cas.value.id}/documents`, { tipus: nouDocTipus.value }); await obrir(cas.value) }
  catch (e) { error.value = e?.message || 'No s\'ha pogut generar.' }
}

async function desarDocument(d) {
  error.value = ''
  try { await api.put(`/v1/disciplinary/documents/${d.id}`, { contingut: d.contingut }); editantDoc.value = null; await obrir(cas.value) }
  catch (e) { error.value = e?.message || 'No s\'ha pogut desar el document.' }
}

async function signarDocument(d) {
  if (!await confirma(`Signar «${d.tipus}» com a ${usuari.value}? La signatura és personal i queda a l'historial.`)) return
  error.value = ''
  try { await api.post(`/v1/disciplinary/documents/${d.id}/sign`, {}); await obrir(cas.value) }
  catch (e) { error.value = e?.message || 'No s\'ha pogut signar.' }
}

/** Recalcula l'empremta de l'escrit i la contrasta amb la de la signatura i la del motor. */
async function verificarDocument(d) {
  error.value = ''
  try { verificacions.value = { ...verificacions.value, [d.id]: await api.get(`/v1/disciplinary/documents/${d.id}/verify`) } }
  catch (e) { error.value = e?.message || 'No s\'ha pogut verificar.' }
}

function estatStyle(estat) {
  const map = { esborrany: '#8a5a12', instruccio: 'var(--color-primary)', comunicat: '#6b46c1',
                alegacions: '#b7791f', resolt: 'var(--color-success)', executat: '#276749',
                arxivat: '#718096', signat: 'var(--color-success)', notificat: '#276749' }
  return { padding: '2px 8px', borderRadius: '10px', fontSize: '0.72rem', fontWeight: '700', color: '#fff', background: map[estat] || '#888' }
}
function fmt(ts) { return ts ? new Date(ts).toLocaleString('ca-ES') : '' }

// Render segur del markdown de l'escrit (escapa HTML; formata títols, negretes, línies i claudàtors).
function renderDoc(md) {
  const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  return esc(md || '')
    .replace(/^## (.*)$/gm, '<h3>$1</h3>')
    .replace(/^---$/gm, '<hr>')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/\[([^\]]+)\]/g, '<mark>[$1]</mark>')
    .replace(/\n/g, '<br>')
}

onMounted(load)
</script>

<style scoped>
.doc-paper {
  background: #fff; color: #1a202c; border: 1px solid #d8dee5; border-radius: 4px;
  padding: 34px 40px; max-width: 760px;
  font-family: Georgia, 'Times New Roman', serif; font-size: 0.86rem; line-height: 1.65;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}
.doc-body :deep(h3) { font-size: 1.05rem; text-align: center; letter-spacing: 0.04em; margin: 10px 0 4px; }
.doc-body :deep(hr) { border: 0; border-top: 1px solid #cbd5e0; margin: 14px 0; }
.doc-body :deep(mark) { background: #fff3bf; padding: 0 3px; border-radius: 3px; font-family: inherit; }
.doc-body :deep(strong) { color: #0A4D8C; }
</style>
