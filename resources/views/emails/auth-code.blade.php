<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Codi d'autoritzacio d'hores extra</title>
  <style>
    body { font-family: Arial, sans-serif; background: #EAF4F2; margin: 0; padding: 0; }
    .wrapper { max-width: 580px; margin: 40px auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.10); }
    .header { background: #ffffff; padding: 28px 40px 20px; text-align: center; border-bottom: 3px solid #00806C; }
    .header img { height: 52px; display: block; margin: 0 auto 12px; }
    .header-badge { display: inline-block; background-color: #00806C; color: #ffffff; font-size: 0.78rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; padding: 5px 14px; border-radius: 20px; margin-top: 4px; }
    .body { padding: 32px 40px; }
    .greeting { font-size: 1.05rem; color: #222222; margin-bottom: 8px; }
    .subtitle { font-size: 0.9rem; color: #666666; margin-bottom: 24px; line-height: 1.5; }
    .info-box { background-color: #F4F8F7; border-radius: 10px; padding: 18px 22px; margin-bottom: 24px; border: 1px solid #D3E8E3; }
    .info-box table { width: 100%; border-collapse: collapse; }
    .info-box td { font-size: 0.88rem; color: #555555; padding: 5px 0; vertical-align: top; }
    .info-box td:first-child { font-weight: 700; color: #00806C; width: 44%; }
    .divider { border: none; border-top: 1px solid #E1EEEB; margin: 0 0 24px; }
    .code-label { font-size: 0.75rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #00806C; text-align: center; margin-bottom: 10px; }
    .instructions { font-size: 0.87rem; color: #555555; line-height: 1.7; background-color: #fffceb; border-left: 4px solid #f59e0b; padding: 14px 18px; margin-bottom: 8px; }
    .instructions strong { color: #92650a; }
    .footer { background-color: #F4F8F7; padding: 18px 40px; text-align: center; font-size: 0.75rem; color: #aaaaaa; border-top: 1px solid #E1EEEB; }
    .footer strong { color: #888888; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <img src="https://crtrrhh.crtbcn.cat/assets/crt-logo.png" alt="CRT - Centre de Rehabilitació Terapèutica" />
      <div class="header-badge">Autoritzacio d'hores extra</div>
    </div>
    <div class="body">
      <p class="greeting">Hola, <strong>{{ $recipient->name }}</strong>,</p>
      <p class="subtitle">El teu responsable <strong>{{ $generatedBy->name }}</strong> t'ha generat un codi d'autoritzacio d'hores extra. Trobaras els detalls a continuacio.</p>
      <div class="info-box">
        <table>
          <tr><td>Concepte</td><td>{{ $authCode->concept }}</td></tr>
          <tr><td>Hores autoritzades</td><td><strong>{{ number_format($authCode->authorized_hours, 1) }} hores</strong></td></tr>
          <tr><td>Franja horaria</td><td>{{ $authCode->time_slot_start }} - {{ $authCode->time_slot_end }}</td></tr>
          <tr><td>Validesa</td><td>{{ \Carbon\Carbon::parse($authCode->valid_from)->format('d/m/Y') }} fins al {{ \Carbon\Carbon::parse($authCode->valid_to)->format('d/m/Y') }}</td></tr>
        </table>
      </div>
      <hr class="divider">
      <div class="code-label">El teu codi d'autoritzacio</div>
      <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
        <tr>
          <td align="center">
            <table cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td bgcolor="#00806C" align="center" style="background-color:#00806C;border-radius:12px;padding:24px 40px;">
                  <span style="color:#ffffff;font-size:2rem;font-weight:800;letter-spacing:8px;font-family:'Courier New',Courier,monospace;display:block;">{{ $authCode->code }}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
      <p style="font-size:0.82rem;color:#888888;text-align:center;margin-bottom:24px;">Codi d'un sol us &middot; caduca el <strong style="color:#00806C;">{{ \Carbon\Carbon::parse($authCode->valid_to)->format('d/m/Y') }}</strong></p>
      <div class="instructions">
        <strong>Com utilitzar-lo:</strong><br>
        Accedeix al portal del treballador &rarr; seccio Fitxatge &rarr; Autoritzar hores extra, selecciona el fitxatge corresponent i introdueix el codi que apareix mes amunt.
      </div>
    </div>
    <div class="footer">
      <strong>CRT &mdash; Centre de Rehabilitació Terapèutica</strong><br>
      Sistema de gestio de Recursos Humans &nbsp;&middot;&nbsp; Aquest missatge es automatic, no cal respondre.
    </div>
  </div>
</body>
</html>
