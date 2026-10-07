<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jornades sense sortida</title>
  <style>
    body { font-family: Arial, sans-serif; background: #eef1f6; margin: 0; padding: 0; }
    .wrapper { max-width: 580px; margin: 40px auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .header { background: #ffffff; padding: 28px 40px 20px; text-align: center; border-bottom: 3px solid #1a3c5e; }
    .header img { height: 52px; display: block; margin: 0 auto 12px; }
    .badge { display: inline-block; color: #fff; background: #dc2626; font-size: 0.78rem; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; padding: 6px 14px; border-radius: 20px; }
    .body { padding: 32px 40px; }
    .greeting { font-size: 1.05rem; color: #222; margin-bottom: 8px; }
    .subtitle { font-size: 0.94rem; color: #555; margin-bottom: 20px; line-height: 1.6; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 0.9rem; }
    th { text-align: left; color: #666; font-weight: 600; padding: 8px 0; border-bottom: 2px solid #e8ecf2; }
    td { padding: 8px 0; border-bottom: 1px solid #f0f0f0; color: #1a3c5e; font-weight: 700; }
    td.pendent { color: #dc2626; }
    .cta-box { background: #1a3c5e; border-radius: 10px; padding: 20px; text-align: center; margin: 24px 0; }
    .cta-box p { color: #fff; margin: 0 0 12px; font-size: 0.9rem; }
    .cta-btn { display: inline-block; background: #fff; color: #1a3c5e; font-weight: 700; font-size: 0.95rem; padding: 10px 24px; border-radius: 8px; text-decoration: none; }
    .text-small { font-size: 0.82rem; color: #888; line-height: 1.6; }
    .footer { background: #f4f7fb; padding: 18px 40px; text-align: center; font-size: 0.75rem; color: #888; border-top: 1px solid #e8ecf2; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <img src="https://crtrrhh.crtbcn.cat/assets/crt-logo.png" alt="CRT" />
      <div class="badge">🚪 Jornades sense sortida</div>
    </div>

    <div class="body">
      <p class="greeting">Hola, <strong>{{ $user->name }}</strong>,</p>
      <p class="subtitle">
        Aquests dies vas fitxar l'entrada però <strong>no la sortida</strong>. Mentre no es resolgui,
        la jornada compta 0 hores. El registre no es pot canviar, però pots <strong>declarar a quina hora
        vas acabar</strong> des del Tauler de l'aplicació. RRHH ho revisarà.
      </p>

      <table>
        <thead><tr><th>Dia</th><th>Entrada</th><th>Sortida</th></tr></thead>
        <tbody>
          @foreach($logs as $log)
            <tr>
              <td>{{ \Carbon\Carbon::parse($log->date)->format('d/m/Y') }}</td>
              {{-- Hora de Madrid sense zona: es llegeix del text, sense convertir-la. --}}
              <td>{{ substr((string) $log->getRawOriginal('start_time'), 11, 5) }}h</td>
              <td class="pendent">No fitxada</td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <div class="cta-box">
        <p>Entra a l'aplicació i declara l'hora de sortida</p>
        <a href="https://crtrrhh.crtbcn.cat" class="cta-btn">Obrir aplicació →</a>
      </div>

      <p class="text-small">
        Rebràs aquest avís cada matí fins que ho declaris. Si tens cap dubte, escriu a rrhh@lirc.es.
      </p>
    </div>

    <div class="footer">
      <strong>CRT &mdash; Centre de Rehabilitació Terapèutica</strong><br>
      Aquest missatge és automàtic. Registre de jornada conforme al RDL 8/2019.
    </div>
  </div>
</body>
</html>
