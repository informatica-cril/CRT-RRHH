@php
  $tipus = ['fitxatge' => 'Fitxatge', 'horari' => 'Horari', 'nomina' => 'Nòmina', 'permisos' => 'Permisos i absències', 'app' => "L'aplicació", 'altres' => 'Altres'];
  $u = $incidencia->user;
@endphp
<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Incidència nova</title>
  <style>
    body { font-family: Arial, sans-serif; background: #eef1f6; margin: 0; padding: 0; }
    .wrapper { max-width: 580px; margin: 40px auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .header { background: #ffffff; padding: 28px 40px 20px; text-align: center; border-bottom: 3px solid #1a3c5e; }
    .header img { height: 52px; display: block; margin: 0 auto 12px; }
    .badge { display: inline-block; color: #fff; background: #d97706; font-size: 0.78rem; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; padding: 6px 14px; border-radius: 20px; }
    .body { padding: 32px 40px; }
    .fila { font-size: 0.92rem; color: #555; margin: 0 0 6px; }
    .fila strong { color: #1a3c5e; }
    .text { background: #f4f7fb; border-left: 4px solid #1a3c5e; border-radius: 8px; padding: 14px 16px; margin: 18px 0; color: #222; font-size: 0.95rem; line-height: 1.6; white-space: pre-wrap; }
    .cta-box { background: #1a3c5e; border-radius: 10px; padding: 20px; text-align: center; margin: 24px 0 8px; }
    .cta-btn { display: inline-block; background: #fff; color: #1a3c5e; font-weight: 700; font-size: 0.95rem; padding: 10px 24px; border-radius: 8px; text-decoration: none; }
    .text-small { font-size: 0.82rem; color: #888; line-height: 1.6; }
    .footer { background: #f4f7fb; padding: 18px 40px; text-align: center; font-size: 0.75rem; color: #888; border-top: 1px solid #e8ecf2; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <img src="https://crilsalutrrhh.app.crilsl.org/assets/cril-logo.png" alt="CRIL" />
      <div class="badge">🛎️ Incidència nova</div>
    </div>
    <div class="body">
      <p class="fila">De: <strong>{{ $u->name }}</strong> ({{ $u->email }})</p>
      <p class="fila">Tipus: <strong>{{ $tipus[$incidencia->tipus] ?? $incidencia->tipus }}</strong></p>
      <p class="fila">Assumpte: <strong>{{ $incidencia->assumpte }}</strong></p>
      <div class="text">{{ $incidencia->descripcio }}</div>
      <p class="text-small">Si respons aquest correu, la resposta arriba directament a {{ $u->name }}.</p>
      <div class="cta-box">
        <a href="https://crilsalutrrhh.app.crilsl.org/#/incidencies" class="cta-btn">Veure les incidències →</a>
      </div>
    </div>
    <div class="footer">
      <strong>CRIL &mdash; Centre de Rehabilitació i Logopèdia</strong><br>
      Aquest missatge és automàtic.
    </div>
  </div>
</body>
</html>
