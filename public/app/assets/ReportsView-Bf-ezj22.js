import{C as e,R as t,U as n,a as r,c as i,ct as a,d as o,f as s,k as c,l,lt as u,o as d,r as f,ut as p,x as m,y as ee}from"./runtime-core.esm-bundler-I9YwJFgV.js";import{c as h,r as g,t as te}from"./auth-Y6VJrO_K.js";import{a as ne,t as re}from"./index-DFjmPh-W.js";var ie={class:`page-header`},ae={class:`page-title`},oe={class:`page-actions`,style:{"flex-wrap":`wrap`}},se=[`value`],ce=[`value`],le={class:`stats-grid mb-lg`},ue={class:`stat-card primary`},de={class:`stat-label`},fe={class:`stat-value`},pe={class:`stat-card`},me={class:`stat-label`},he={class:`stat-value`},ge={class:`stat-card`,style:{"border-bottom":`3px solid var(--color-success)`}},_e={class:`stat-value`,style:{color:`var(--color-success)`}},ve={class:`stat-card`,style:{"border-bottom":`3px solid var(--color-danger)`}},ye={class:`stat-value`,style:{color:`var(--color-danger)`}},be={class:`stat-card`},xe={class:`stat-label`},Se={class:`stat-value`},_={key:0,class:`card mb-lg`},v={class:`card-header`},y={class:`card-title`},b={style:{display:`grid`,"grid-template-columns":`repeat(auto-fit,minmax(160px,1fr))`,gap:`12px`,"margin-bottom":`16px`}},x={style:{background:`var(--color-bg)`,padding:`12px`,"border-radius":`8px`}},S={style:{"font-size":`1.3rem`,"font-weight":`700`}},C={style:{background:`var(--color-bg)`,padding:`12px`,"border-radius":`8px`}},w={style:{"font-size":`1.3rem`,"font-weight":`700`}},T={style:{background:`rgba(76,175,80,0.08)`,padding:`12px`,"border-radius":`8px`,"border-bottom":`3px solid var(--color-success)`}},E={style:{"font-size":`1.3rem`,"font-weight":`700`,color:`var(--color-success)`}},D={style:{background:`rgba(239,68,68,0.08)`,padding:`12px`,"border-radius":`8px`,"border-bottom":`3px solid var(--color-danger)`}},O={style:{"font-size":`1.3rem`,"font-weight":`700`,color:`var(--color-danger)`}},k={style:{background:`var(--color-bg)`,padding:`12px`,"border-radius":`8px`}},A={style:{"font-size":`1.3rem`,"font-weight":`700`}},j={style:{background:`rgba(239,68,68,0.06)`,padding:`12px`,"border-radius":`8px`,"border-bottom":`3px solid var(--color-warning)`}},Ce={style:{"font-size":`1.3rem`,"font-weight":`700`,color:`var(--color-warning)`}},we={key:0,style:{"border-top":`1px solid var(--color-border-light)`,"padding-top":`16px`}},Te={class:`table-container`,style:{"max-height":`220px`,"overflow-y":`auto`}},Ee={class:`badge badge-warning`,style:{"font-size":`0.7rem`}},De={class:`text-small`},Oe={class:`text-small`},ke={class:`card`},Ae={class:`card-header`,style:{"flex-wrap":`wrap`,gap:`8px`}},je={class:`card-title`},Me={style:{display:`flex`,gap:`8px`,"margin-left":`auto`}},Ne={class:`table-responsive`},Pe={key:0},Fe={key:0,style:{"font-weight":`500`}},Ie={style:{"font-size":`0.82rem`}},Le={style:{"font-variant-numeric":`tabular-nums`}},Re={style:{"font-variant-numeric":`tabular-nums`,color:`var(--color-text-secondary)`}},M={key:0,style:{color:`var(--color-success)`,"font-weight":`600`}},ze={key:1,style:{color:`var(--color-text-muted)`}},Be={key:0,style:{color:`var(--color-danger)`,"font-weight":`600`}},Ve={key:1,style:{color:`var(--color-text-muted)`}},He={key:0,style:{color:`var(--color-danger)`,"font-size":`0.78rem`}},Ue={key:1,style:{color:`var(--color-success)`,"font-size":`0.82rem`}},We={key:2,style:{color:`var(--color-text-muted)`}},Ge={key:0},Ke=[`colspan`],qe={key:0},Je={style:{background:`var(--color-bg)`,"font-weight":`700`,"border-top":`2px solid var(--color-border-light)`}},Ye={key:0},Xe={style:{color:`var(--color-text-muted)`,"font-size":`0.78rem`}},Ze={style:{color:`var(--color-primary)`}},Qe={style:{color:`var(--color-success)`}},$e={style:{color:`var(--color-danger)`}},et={class:`rdl-notice`},tt={__name:`ReportsView`,setup(tt){let N=te(),P=re(),F=e=>ne.t(e),I=new Date,L=t(`${I.getFullYear()}-${String(I.getMonth()+1).padStart(2,`0`)}`),R=t(N.isAdmin?`all`:N.userId),z=t([]),B=t([]),V=t([]);async function nt(){try{let e=await g.getUsers();B.value=e,z.value=e.filter(e=>e.role===`worker`),V.value=await g.getAbsences(),await P.loadLogs()}catch(e){console.error(e)}}ee(nt);let rt=r(()=>{let e=[];for(let t=0;t<12;t++){let n=new Date(I.getFullYear(),I.getMonth()-t,1);e.push({value:`${n.getFullYear()}-${String(n.getMonth()+1).padStart(2,`0`)}`,label:n.toLocaleDateString(`ca-ES`,{month:`long`,year:`numeric`})})}return e}),H=r(()=>{let e=P.logs.filter(e=>e.date?.startsWith(L.value));return R.value!==`all`&&(e=e.filter(e=>e.user_id==R.value)),N.isWorker&&(e=e.filter(e=>e.user_id===N.userId)),e.sort((e,t)=>new Date(e.date)-new Date(t.date))}),U=r(()=>R.value===`all`?[]:H.value.filter(e=>e.location_match===!1)),W=r(()=>{let e=H.value;return{totalHours:Number(e.reduce((e,t)=>e+Number(t.total_hours_worked||0),0)).toFixed(1),theoreticalHours:(e.length*8).toFixed(0),authorizedHours:Number(e.reduce((e,t)=>e+Number(t.extra_hours_authorized||0),0)).toFixed(1),unauthorizedHours:Number(e.reduce((e,t)=>e+Number(t.extra_hours_unauthorized||0),0)).toFixed(1),absenceDays:V.value.filter(e=>R.value===`all`||e.user_id==R.value).length,approvedCount:e.filter(e=>e.status===`approved`).length}}),G=r(()=>{let e=H.value;return e.length?Number(e.reduce((e,t)=>e+Number(t.total_hours_worked||0),0)/e.length).toFixed(1):`0`}),K=r(()=>V.value.filter(e=>e.user_id==R.value).length);function q(e){return B.value.find(t=>t.id===e)?.postal_code_assigned||`—`}function J(e){return B.value.find(t=>t.id===e)?.name||`—`}function Y(e){return new Date(e).toLocaleDateString(`ca-ES`,{weekday:`short`,day:`2-digit`,month:`short`})}function X(e){return e?new Date(e).toLocaleTimeString(`es-ES`,{hour:`2-digit`,minute:`2-digit`,timeZone:`Europe/Madrid`}):`—`}function Z(e){let t=Number(e)||0,n=Math.floor(t),r=Math.round((t-n)*60);return n===0?r+`min`:r===0?n+`h`:n+`h `+r+`min`}function Q(e){return{pending:`badge-pending`,approved:`badge-success`,rejected:`badge-danger`}[e]||`badge-primary`}let it=r(()=>{let[e,t]=L.value.split(`-`);return new Date(e,t-1,1).toLocaleDateString(`ca-ES`,{month:`long`,year:`numeric`})});function $(e){let t=H.value,n=R.value===`all`?`Tots els treballadors`:J(R.value),r=it.value;if(e===`excel`){let e=R.value===`all`?[`Treballador`,`Data`,`Entrada`,`Sortida`,`Total h.`,`H. Autorit.`,`H. No aut.`,`Estat`]:[`Data`,`Entrada`,`Sortida`,`Total h.`,`H. Autorit.`,`H. No aut.`,`Estat`],i=t.map(e=>{let t=[Y(e.date),X(e.start_time),e.end_time?X(e.end_time):`—`,Number(e.total_hours_worked||0).toFixed(2),Number(e.extra_hours_authorized||0).toFixed(2),Number(e.extra_hours_unauthorized||0).toFixed(2),e.status||`—`];return R.value===`all`?[`"${J(e.user_id)}"`,...t]:t}),a=R.value===`all`?[`TOTAL`,``,``,``,W.value.totalHours,W.value.authorizedHours,W.value.unauthorizedHours,``]:[`TOTAL`,``,``,W.value.totalHours,W.value.authorizedHours,W.value.unauthorizedHours,``],o=R.value!==`all`&&U.value.length>0?[``,`"⚠️ Fitxatges fora del codi postal assignat (${U.value.length})"`,[`Data`,`CP Assignat`,`Entrada`,`Sortida`,`Hores`,`Estat`].join(`,`),...U.value.map(e=>[Y(e.date),e.assigned_postal_code||q(e.user_id)||`—`,X(e.start_time),e.end_time?X(e.end_time):`—`,Number(e.total_hours_worked||0).toFixed(2),e.status||`—`].join(`,`))]:[],s=[`"Informe CRT RRHH — ${r} — ${n}"`,``,e.join(`,`),...i.map(e=>e.join(`,`)),``,a.join(`,`),...o].join(`
`),c=new Blob([`﻿`+s],{type:`text/csv;charset=utf-8;`}),l=URL.createObjectURL(c),u=document.createElement(`a`);u.href=l,u.download=`informe_${L.value}_${R.value===`all`?`tots`:n.replace(/\s+/g,`_`)}.csv`,u.click(),URL.revokeObjectURL(l)}if(e===`pdf`){let e={approved:`Aprovat`,pending:`Pendent`,rejected:`Rebutjat`},i=R.value===`all`,a=e=>{let t=Number(e)||0;if(t<.05)return`—`;let n=Math.floor(t),r=Math.round((t-n)*60);return n===0?r+`min`:r===0?n+`h`:n+`h `+r+`min`},o=t.map(t=>`
      <tr style="${t.status===`rejected`?`background:#fff5f5;`:t.status===`pending`?`background:#fffbeb;`:``}">
        ${i?`<td>${J(t.user_id)}</td>`:``}
        <td>${Y(t.date)}</td>
        <td style="font-variant-numeric:tabular-nums;">${X(t.start_time)}</td>
        <td style="font-variant-numeric:tabular-nums;color:#64748b;">${t.end_time?X(t.end_time):`—`}</td>
        <td style="font-weight:700;">${a(t.total_hours_worked)}</td>
        <td style="color:#16a34a;">${Number(t.extra_hours_authorized||0)>=.05?`+`+a(t.extra_hours_authorized):`—`}</td>
        <td style="color:#dc2626;">${a(t.extra_hours_unauthorized)}</td>
        <td><span style="padding:2px 8px;border-radius:4px;font-size:0.73rem;font-weight:600;background:${t.status===`approved`?`#dcfce7`:t.status===`rejected`?`#fee2e2`:`#fef9c3`};color:${t.status===`approved`?`#166534`:t.status===`rejected`?`#991b1b`:`#854d0e`};">${e[t.status]||t.status}</span></td>
      </tr>`).join(``),s=a(W.value.totalHours),c=Number(W.value.authorizedHours)>=.05?`+`+a(W.value.authorizedHours):`—`,l=a(W.value.unauthorizedHours),u=i?``:`
  <div style="margin-bottom:18px;padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
    <div style="font-weight:700;color:#00806C;margin-bottom:10px;font-size:12px;">Informe individual: ${n}</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <div style="flex:1;min-width:100px;background:white;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;">
        <div style="font-size:8px;color:#64748b;text-transform:uppercase;">Mitjana diària</div>
        <div style="font-size:14px;font-weight:700;">${G.value}h</div>
      </div>
      <div style="flex:1;min-width:100px;background:white;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;">
        <div style="font-size:8px;color:#64748b;text-transform:uppercase;">Dies treballats</div>
        <div style="font-size:14px;font-weight:700;">${t.length}</div>
      </div>
      <div style="flex:1;min-width:100px;background:#f0fdf4;padding:8px 12px;border-radius:6px;border:1px solid #16a34a;">
        <div style="font-size:8px;color:#16a34a;text-transform:uppercase;">✓ H. Autoritzades</div>
        <div style="font-size:14px;font-weight:700;color:#16a34a;">${c}</div>
      </div>
      <div style="flex:1;min-width:100px;background:#fff5f5;padding:8px 12px;border-radius:6px;border:1px solid #dc2626;">
        <div style="font-size:8px;color:#dc2626;text-transform:uppercase;">✗ No autoritzades</div>
        <div style="font-size:14px;font-weight:700;color:#dc2626;">${l}</div>
      </div>
      <div style="flex:1;min-width:100px;background:white;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;">
        <div style="font-size:8px;color:#64748b;text-transform:uppercase;">Permisos usats</div>
        <div style="font-size:14px;font-weight:700;">${K.value}</div>
      </div>
      ${U.value.length>0?`<div style="flex:1;min-width:100px;background:#fffbeb;padding:8px 12px;border-radius:6px;border:1px solid #f59e0b;">
        <div style="font-size:8px;color:#d97706;text-transform:uppercase;">⚠️ Fora CP</div>
        <div style="font-size:14px;font-weight:700;color:#d97706;">${U.value.length}</div>
      </div>`:``}
    </div>
  </div>`,d=t.length>0?`
  <tfoot><tr style="background:#f1f5f9;font-weight:700;border-top:2px solid #00806C;">
    ${i?`<td></td>`:``}
    <td style="color:#64748b;font-size:9px;">${t.length} jornades</td>
    <td></td><td></td>
    <td style="color:#00806C;">${s}</td>
    <td style="color:#16a34a;">${c}</td>
    <td style="color:#dc2626;">${l}</td>
    <td></td>
  </tr></tfoot>`:``,f=!i&&U.value.length>0?`
  <div style="margin-top:20px;margin-bottom:6px;">
    <div style="font-weight:700;color:#d97706;font-size:11px;margin-bottom:8px;border-left:3px solid #f59e0b;padding-left:8px;">⚠️ Fitxatges fora del codi postal assignat (${U.value.length})</div>
    <table>
      <thead><tr style="background:#92400e;">
        <th>Data</th><th>CP Assignat</th><th>Entrada</th><th>Sortida</th><th>Hores</th><th>Estat</th>
      </tr></thead>
      <tbody>${U.value.map(t=>`
        <tr style="background:#fffbeb;">
          <td>${Y(t.date)}</td>
          <td><span style="background:#fef9c3;color:#854d0e;padding:2px 6px;border-radius:3px;font-size:9px;font-weight:600;">${t.assigned_postal_code||q(t.user_id)}</span></td>
          <td style="font-variant-numeric:tabular-nums;">${X(t.start_time)}</td>
          <td style="font-variant-numeric:tabular-nums;color:#64748b;">${t.end_time?X(t.end_time):`—`}</td>
          <td style="font-weight:700;">${Number(t.total_hours_worked||0).toFixed(2)}h</td>
          <td><span style="padding:2px 6px;border-radius:3px;font-size:9px;font-weight:600;background:${t.status===`approved`?`#dcfce7`:t.status===`rejected`?`#fee2e2`:`#fef9c3`};color:${t.status===`approved`?`#166534`:t.status===`rejected`?`#991b1b`:`#854d0e`};">${e[t.status]||t.status}</span></td>
        </tr>`).join(``)}
      </tbody>
    </table>
  </div>`:``,p=`<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <title>Informe ${r} — ${n}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 11px; color: #1e293b; padding: 24px; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 2px solid #00806C; padding-bottom: 12px; }
    .header h1 { font-size: 17px; color: #00806C; }
    .header p { font-size: 11px; color: #64748b; margin-top: 2px; }
    .summary { display: flex; gap: 10px; margin-bottom: 16px; }
    .summary-card { flex: 1; border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 12px; }
    .summary-card .label { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px; }
    .summary-card .value { font-size: 15px; font-weight: 700; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #00806C; color: white; padding: 7px 8px; text-align: left; font-size: 10px; }
    td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
    .footer { margin-top: 16px; font-size: 8.5px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; display:flex; justify-content:space-between; }
    @media print { body { padding: 10px; } @page { margin: 1.2cm; size: A4 landscape; } }
  </style>
</head>
<body>
  <div class="header">
    <div>
      <h1>Informe mensual · CRT RRHH</h1>
      <p>${r} · ${n}</p>
    </div>
    <div style="text-align:right;font-size:10px;color:#64748b;">
      Generat: ${new Date().toLocaleDateString(`ca-ES`,{day:`2-digit`,month:`long`,year:`numeric`})}
    </div>
  </div>

  <div class="summary">
    <div class="summary-card"><div class="label">Hores treballades</div><div class="value">${s}</div></div>
    <div class="summary-card"><div class="label">Hores teòriques</div><div class="value">${W.value.theoreticalHours}h</div></div>
    <div class="summary-card" style="border-color:#16a34a;"><div class="label" style="color:#16a34a;">✓ H. Autoritzades</div><div class="value" style="color:#16a34a;">${c}</div></div>
    <div class="summary-card" style="border-color:#dc2626;"><div class="label" style="color:#dc2626;">✗ H. No autoritzades</div><div class="value" style="color:#dc2626;">${l}</div></div>
    <div class="summary-card"><div class="label">Absències</div><div class="value">${W.value.absenceDays}</div></div>
  </div>

  ${u}

  ${f}

  <table>
    <thead><tr>
      ${i?`<th>Treballador</th>`:``}
      <th>Data</th><th>Entrada</th><th>Sortida</th><th>Total h.</th>
      <th style="color:#86efac;">✓ Autorit.</th><th style="color:#fca5a5;">✗ No aut.</th><th>Estat</th>
    </tr></thead>
    <tbody>${o}</tbody>
    ${d}
  </table>

  <div class="footer">
    <span>RDL 8/2019 Art. 34.9 ET · RGPD (UE) 2016/679 · LOPDGDD LO 3/2018 · Retenció mínima 4 anys</span>
    <span>CRT — Document confidencial</span>
  </div>
  <script>window.onload = () => { window.print(); }<\/script>
</body>
</html>`,m=window.open(``,`_blank`);m.document.write(p),m.document.close()}}return(t,r)=>(m(),l(`div`,null,[d(`div`,ie,[d(`div`,null,[d(`h1`,ae,p(F(`reports`)),1),r[8]||=d(`p`,{class:`page-subtitle`},`Informes configurables i dades de nòmines per treballador i període`,-1)]),d(`div`,oe,[c(d(`select`,{class:`form-select`,"onUpdate:modelValue":r[0]||=e=>L.value=e,style:{width:`180px`}},[(m(!0),l(f,null,e(rt.value,e=>(m(),l(`option`,{key:e.value,value:e.value},p(e.label),9,se))),128))],512),[[h,L.value]]),n(N).isAdmin?c((m(),l(`select`,{key:0,class:`form-select`,"onUpdate:modelValue":r[1]||=e=>R.value=e,style:{width:`200px`}},[r[9]||=d(`option`,{value:`all`},`Tots els treballadors`,-1),(m(!0),l(f,null,e(z.value,e=>(m(),l(`option`,{key:e.id,value:e.id},p(e.name),9,ce))),128))],512)),[[h,R.value]]):i(``,!0)])]),d(`div`,le,[d(`div`,ue,[d(`div`,de,p(F(`worked_hours`)),1),d(`div`,fe,p(Z(W.value.totalHours)),1)]),d(`div`,pe,[d(`div`,me,p(F(`theoretical_hours`)),1),d(`div`,he,p(W.value.theoreticalHours)+`h`,1)]),d(`div`,ge,[r[10]||=d(`div`,{class:`stat-label`,style:{color:`var(--color-success)`}},`✓ H. Autoritzades`,-1),d(`div`,_e,p(Number(W.value.authorizedHours)>=.05?W.value.authorizedHours+`h`:`—`),1)]),d(`div`,ve,[r[11]||=d(`div`,{class:`stat-label`,style:{color:`var(--color-danger)`}},`✗ H. No autoritzades`,-1),d(`div`,ye,p(Number(W.value.unauthorizedHours)>=.05?W.value.unauthorizedHours+`h`:`—`),1)]),d(`div`,be,[d(`div`,xe,p(F(`absences`)),1),d(`div`,Se,p(W.value.absenceDays),1)])]),R.value===`all`?i(``,!0):(m(),l(`div`,_,[d(`div`,v,[d(`h3`,y,`Informe individual: `+p(J(R.value)),1)]),d(`div`,b,[d(`div`,x,[r[12]||=d(`div`,{class:`text-small text-muted`},`Mitjana diària`,-1),d(`div`,S,p(G.value)+`h`,1)]),d(`div`,C,[r[13]||=d(`div`,{class:`text-small text-muted`},`Dies treballats`,-1),d(`div`,w,p(H.value.length),1)]),d(`div`,T,[r[14]||=d(`div`,{class:`text-small`,style:{color:`var(--color-success)`}},`✓ Hores autoritzades`,-1),d(`div`,E,p(W.value.authorizedHours)+`h`,1)]),d(`div`,D,[r[15]||=d(`div`,{class:`text-small`,style:{color:`var(--color-danger)`}},`✗ No autoritzades`,-1),d(`div`,O,p(W.value.unauthorizedHours)+`h`,1)]),d(`div`,k,[r[16]||=d(`div`,{class:`text-small text-muted`},`Permisos usats`,-1),d(`div`,A,p(K.value),1)]),d(`div`,j,[r[17]||=d(`div`,{class:`text-small`,style:{color:`var(--color-warning)`}},`📍 Fitxatges fora de CP`,-1),d(`div`,Ce,p(U.value.length),1)])]),U.value.length>0?(m(),l(`div`,we,[r[19]||=d(`h4`,{style:{color:`var(--color-warning)`,"margin-bottom":`12px`}},`⚠️ Fitxatges fora del codi postal assignat`,-1),d(`div`,Te,[d(`table`,null,[r[18]||=d(`thead`,null,[d(`tr`,null,[d(`th`,null,`Data`),d(`th`,null,`CP Assignat`),d(`th`,null,`Inici`),d(`th`,null,`Fi`),d(`th`,null,`Hores`),d(`th`,null,`Estat`)])],-1),d(`tbody`,null,[(m(!0),l(f,null,e(U.value,e=>(m(),l(`tr`,{key:e.id,style:{background:`rgba(239,68,68,0.03)`}},[d(`td`,null,p(Y(e.date)),1),d(`td`,null,[d(`span`,Ee,p(e.assigned_postal_code||q(e.user_id)),1)]),d(`td`,De,p(X(e.start_time)),1),d(`td`,Oe,p(e.end_time?X(e.end_time):`—`),1),d(`td`,null,[d(`strong`,null,p(Number(e.total_hours_worked||0).toFixed(2))+`h`,1)]),d(`td`,null,[d(`span`,{class:a([`badge`,Q(e.status)])},p(F(e.status)||e.status),3)])]))),128))])])])])):i(``,!0)])),d(`div`,ke,[d(`div`,Ae,[d(`h3`,je,p(F(`monthly_report`)),1),d(`div`,Me,[d(`button`,{onClick:r[2]||=e=>$(`pdf`),style:{display:`flex`,"align-items":`center`,gap:`6px`,padding:`7px 14px`,"border-radius":`8px`,border:`1.5px solid #dc2626`,background:`rgba(220,38,38,0.06)`,color:`#dc2626`,"font-weight":`600`,"font-size":`0.8rem`,cursor:`pointer`,transition:`all 0.15s`},onMouseenter:r[3]||=e=>e.currentTarget.style.background=`rgba(220,38,38,0.14)`,onMouseleave:r[4]||=e=>e.currentTarget.style.background=`rgba(220,38,38,0.06)`},[...r[20]||=[o(`<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="13" x2="15" y2="13"></line><line x1="9" y1="17" x2="15" y2="17"></line></svg> Descarregar PDF `,2)]],32),d(`button`,{onClick:r[5]||=e=>$(`excel`),style:{display:`flex`,"align-items":`center`,gap:`6px`,padding:`7px 14px`,"border-radius":`8px`,border:`1.5px solid #16a34a`,background:`rgba(22,163,74,0.06)`,color:`#16a34a`,"font-weight":`600`,"font-size":`0.8rem`,cursor:`pointer`,transition:`all 0.15s`},onMouseenter:r[6]||=e=>e.currentTarget.style.background=`rgba(22,163,74,0.14)`,onMouseleave:r[7]||=e=>e.currentTarget.style.background=`rgba(22,163,74,0.06)`},[...r[21]||=[d(`svg`,{width:`14`,height:`14`,viewBox:`0 0 24 24`,fill:`none`,stroke:`currentColor`,"stroke-width":`2.5`},[d(`rect`,{x:`3`,y:`3`,width:`18`,height:`18`,rx:`2`}),d(`path`,{d:`M3 9h18M9 21V9`})],-1),s(` Descarregar Excel `,-1)]],32)])]),d(`div`,Ne,[d(`table`,null,[d(`thead`,null,[d(`tr`,null,[R.value===`all`?(m(),l(`th`,Pe,`Treballador`)):i(``,!0),r[22]||=d(`th`,null,`Data`,-1),r[23]||=d(`th`,null,`Entrada`,-1),r[24]||=d(`th`,null,`Sortida`,-1),r[25]||=d(`th`,null,`Total h.`,-1),r[26]||=d(`th`,{style:{color:`var(--color-success)`}},`✓ Autorit.`,-1),r[27]||=d(`th`,{style:{color:`var(--color-danger)`}},`✗ No aut.`,-1),r[28]||=d(`th`,null,`📍 CP`,-1),r[29]||=d(`th`,null,`Estat`,-1)])]),d(`tbody`,null,[(m(!0),l(f,null,e(H.value,e=>(m(),l(`tr`,{key:e.id,style:u(e.status===`rejected`?`background:rgba(239,68,68,0.04);`:e.status===`pending`?`background:rgba(245,158,11,0.03);`:``)},[R.value===`all`?(m(),l(`td`,Fe,p(J(e.user_id)),1)):i(``,!0),d(`td`,Ie,p(Y(e.date)),1),d(`td`,Le,p(X(e.start_time)),1),d(`td`,Re,p(e.end_time?X(e.end_time):`—`),1),d(`td`,null,[d(`strong`,null,p(Number(e.total_hours_worked||0).toFixed(2))+`h`,1)]),d(`td`,null,[Number(e.extra_hours_authorized||0)>=.05?(m(),l(`span`,M,` +`+p(Number(e.extra_hours_authorized).toFixed(1))+`h `,1)):(m(),l(`span`,ze,`—`))]),d(`td`,null,[Number(e.extra_hours_unauthorized||0)>=.05?(m(),l(`span`,Be,p(Number(e.extra_hours_unauthorized).toFixed(1))+`h `,1)):(m(),l(`span`,Ve,`—`))]),d(`td`,null,[e.location_match===!1?(m(),l(`span`,He,`⚠️ Fora`)):e.location_match===!0?(m(),l(`span`,Ue,`✓`)):(m(),l(`span`,We,`—`))]),d(`td`,null,[d(`span`,{class:a([`badge`,Q(e.status)]),style:{"font-size":`0.7rem`}},p(e.status===`approved`?`Aprovat`:e.status===`rejected`?`Rebutjat`:`Pendent`),3)])],4))),128)),H.value.length===0?(m(),l(`tr`,Ge,[d(`td`,{colspan:R.value===`all`?9:8,style:{"text-align":`center`,padding:`32px`,color:`var(--color-text-muted)`}},`Cap registre per aquest període`,8,Ke)])):i(``,!0)]),H.value.length>0?(m(),l(`tfoot`,qe,[d(`tr`,Je,[R.value===`all`?(m(),l(`td`,Ye)):i(``,!0),d(`td`,Xe,p(H.value.length)+` jornades`,1),r[30]||=d(`td`,null,null,-1),r[31]||=d(`td`,null,null,-1),d(`td`,Ze,p(Z(W.value.totalHours)),1),d(`td`,Qe,p(Number(W.value.authorizedHours)>=.05?`+`+Z(W.value.authorizedHours):`—`),1),d(`td`,$e,p(Number(W.value.unauthorizedHours)>=.05?Z(W.value.unauthorizedHours):`—`),1),r[32]||=d(`td`,null,null,-1),r[33]||=d(`td`,null,null,-1)])])):i(``,!0)])])]),d(`div`,et,p(F(`rdl_notice`))+` · `+p(F(`rdl_retention`)),1)]))}};export{tt as default};