<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recordatori de jornada</title>
  <style>
    body { font-family: Arial, sans-serif; background: #EAF4F2; margin: 0; padding: 0; }
    .wrapper { max-width: 580px; margin: 40px auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .header { background: #ffffff; padding: 28px 40px 20px; text-align: center; border-bottom: 3px solid #00806C; }
    .header img { height: 52px; display: block; margin: 0 auto 12px; }
    .header-badge { display: inline-block; color: #fff; font-size: 0.78rem; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; padding: 6px 14px; border-radius: 20px; margin-top: 4px; }
    .badge-0 { background: #2563eb; }
    .badge-1 { background: #d97706; }
    .badge-2 { background: #dc2626; }
    .body { padding: 32px 40px; }
    .greeting { font-size: 1.05rem; color: #222; margin-bottom: 8px; }
    .subtitle { font-size: 0.94rem; color: #555; margin-bottom: 20px; line-height: 1.6; }
    .info-box { border-radius: 10px; padding: 18px 22px; margin-bottom: 22px; border: 1px solid; }
    .info-box-1 { background: #fffbeb; border-color: #fcd34d; }
    .info-box-2 { background: #fff5f5; border-color: #fca5a5; }
    .info-box p { margin: 0; font-size: 0.95rem; color: #333; }
    .data-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem; }
    .data-row:last-child { border-bottom: none; }
    .data-label { color: #666; }
    .data-value { font-weight: 700; color: #00806C; }
    .cta-box { background: #00806C; border-radius: 10px; padding: 20px; text-align: center; margin: 24px 0; }
    .cta-box p { color: #fff; margin: 0 0 12px; font-size: 0.9rem; }
    .cta-btn { display: inline-block; background: #fff; color: #00806C; font-weight: 700; font-size: 0.95rem; padding: 10px 24px; border-radius: 8px; text-decoration: none; }
    .text-small { font-size: 0.82rem; color: #888; line-height: 1.6; }
    .footer { background: #F4F8F7; padding: 18px 40px; text-align: center; font-size: 0.75rem; color: #888; border-top: 1px solid #E1EEEB; }
    .footer strong { color: #666; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <img src="https://crtrrhh.crtbcn.cat/assets/crt-logo.png" alt="CRT - Centre de Rehabilitació Terapèutica" />
      @if($reminderNumber === 0)
        <div class="header-badge badge-0">🔔 La teva jornada finalitza aviat</div>
      @elseif($reminderNumber === 1)
        <div class="header-badge badge-1">⏰ Recordatori de jornada</div>
      @else
        <div class="header-badge badge-2">⚠️ Jornada oberta a les 20:00h</div>
      @endif
    </div>

    <div class="body">
      <p class="greeting">Hola, <strong>{{ $log->user->name }}</strong>,</p>

      @if($reminderNumber === 0)
        <p class="subtitle">
          La teva jornada d'avui <strong>finalitza en uns 10 minuts</strong>.
          Recorda que en acabar has de registrar la sortida a l'aplicació perquè quedi
          constància de la teva hora real de fi de jornada.
        </p>
        <div class="info-box" style="background:#eff6ff;border-color:#93c5fd;">
          <p>🔔 Si avui treballes més hores de les previstes, necessitaràs un codi d'autorització del teu responsable per a les hores extra.</p>
        </div>
      @elseif($reminderNumber === 1)
        <p class="subtitle">
          La teva jornada d'avui <strong>ja ha finalitzat</strong> però no hem rebut el registre
          de sortida. Si us plau, accedeix a l'aplicació i tanca la jornada tan aviat com puguis.
        </p>
        <div class="info-box info-box-1">
          <p>⏰ Ja ha passat l'hora de fi de la teva jornada planificada d'avui. El registre de jornada ha de reflectir l'hora real de sortida.</p>
        </div>
      @else
        <p class="subtitle">
          Són les 20:00h i la teva jornada d'avui <strong>continua oberta</strong>.
          Per complir amb la normativa de registre de jornada (RDL 8/2019), cal que
          registris la sortida a l'aplicació.
        </p>
        <div class="info-box info-box-2">
          <p>⚠️ Si no recordes a quina hora has acabat, introdueix l'hora real de sortida. En cap cas el sistema modificarà automàticament el teu registre.</p>
        </div>
      @endif

      <div style="background:#f8fafc;border-radius:8px;padding:16px 20px;margin-bottom:20px;">
        <div class="data-row">
          <span class="data-label">Data de la jornada</span>
          <span class="data-value">{{ \Carbon\Carbon::parse($log->date)->format('d/m/Y') }}</span>
        </div>
        <div class="data-row">
          <span class="data-label">Hora d'entrada registrada</span>
          <span class="data-value">{{ substr((string) $log->getRawOriginal('start_time'), 11, 5) }}h</span>
        </div>
        <div class="data-row">
          <span class="data-label">Hora de sortida</span>
          <span class="data-value" style="color:#dc2626;">Pendent de registrar</span>
        </div>
      </div>

      <div class="cta-box">
        <p>Accedeix ara a l'aplicació per tancar la jornada</p>
        <a href="https://crtrrhh.crtbcn.cat" class="cta-btn">Obrir aplicació →</a>
      </div>

      <p class="text-small">
        Si ja has tancat la jornada i has rebut aquest correu per error, pots ignorar-lo.
        Si tens alguna incidència, contacta amb el departament de RRHH.
      </p>
    </div>

    <div class="footer">
      <strong>CRT &mdash; Centre de Rehabilitació Terapèutica</strong><br>
      Aquest missatge és automàtic. Registre de jornada conforme al RDL 8/2019.
    </div>
  </div>
</body>
</html>
