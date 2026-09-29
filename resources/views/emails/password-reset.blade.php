<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperació de contrasenya</title>
  <style>
    body { font-family: Arial, sans-serif; background: #EAF4F2; margin: 0; padding: 0; }
    .wrapper { max-width: 580px; margin: 40px auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .header { background: #ffffff; padding: 28px 40px 20px; text-align: center; border-bottom: 3px solid #00806C; }
    .header img { height: 52px; display: block; margin: 0 auto 12px; }
    .header-badge { display: inline-block; background: #00806C; color: #fff; font-size: 0.78rem; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; padding: 6px 14px; border-radius: 20px; margin-top: 4px; }
    .body { padding: 32px 40px; }
    .greeting { font-size: 1.05rem; color: #222; margin-bottom: 8px; }
    .subtitle { font-size: 0.94rem; color: #555; margin-bottom: 20px; line-height: 1.6; }
    .info-box { background: #F4F8F7; border-radius: 10px; padding: 18px 22px; margin-bottom: 22px; border: 1px solid #D3E8E3; }
    .info-box p { margin: 0; font-size: 0.95rem; color: #333; }
    .code-label { font-size: 0.78rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #00806C; text-align: center; margin-bottom: 10px; }
    .code-box { background: linear-gradient(135deg, #00806C 0%, #235a8c 100%); border-radius: 12px; padding: 24px 20px; text-align: center; margin-bottom: 16px; }
    .code-box .code { color: #fff; font-size: 1.9rem; font-weight: 800; letter-spacing: 4px; font-family: 'Courier New', monospace; }
    .text-small { font-size: 0.85rem; color: #666; line-height: 1.6; }
    .footer { background: #F4F8F7; padding: 18px 40px; text-align: center; font-size: 0.75rem; color: #888; border-top: 1px solid #E1EEEB; }
    .footer strong { color: #666; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <img src="https://crtrrhh.crtbcn.cat/assets/crt-logo.png" alt="CRT - Centre de Rehabilitació Terapèutica" />
      <div class="header-badge">Recuperació de contrasenya</div>
    </div>

    <div class="body">
      <p class="greeting">Hola, <strong>{{ $user->name }}</strong>,</p>
      <p class="subtitle">
        S'ha generat una nova contrasenya per al teu compte de CRT RRHH.
        Utilitza-la per iniciar sessió i després canvia-la per una de pròpia.
      </p>

      <div class="info-box">
        <p>Si no has sol·licitat aquest correu, ignora-ho. Aquesta contrasenya serà vàlida immediatament.</p>
      </div>

      <div class="code-label">Nova contrasenya</div>
      <div class="code-box">
        <div class="code">{{ $password }}</div>
      </div>

      <p class="text-small">
        Per motius de seguretat, et recomanem canviar la contrasenya un cop hagis iniciat sessió.
      </p>
    </div>

    <div class="footer">
      <strong>CRT &mdash; Centre de Rehabilitació Terapèutica</strong><br>
      Aquest missatge és automàtic, no cal respondre.
    </div>
  </div>
</body>
</html>
