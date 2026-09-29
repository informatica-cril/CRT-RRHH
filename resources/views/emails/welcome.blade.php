<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Benvingut/da a CRT RRHH</title>
  <!--[if mso]>
  <noscript>
    <xml>
      <o:OfficeDocumentSettings>
        <o:PixelsPerInch>96</o:PixelsPerInch>
      </o:OfficeDocumentSettings>
    </xml>
  </noscript>
  <![endif]-->
</head>
<body style="margin:0; padding:0; background-color:#EAF4F2; font-family:Arial, sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EAF4F2;">
    <tr>
      <td align="center" style="padding:40px 16px;">

        <!-- Card -->
        <table width="580" cellpadding="0" cellspacing="0" border="0" style="max-width:580px; width:100%; background-color:#ffffff; border-radius:14px;">

          <!-- Header -->
          <tr>
            <td align="center" style="padding:28px 40px 20px; border-bottom:3px solid #00806C; background-color:#ffffff;">
              <img src="https://crtrrhh.crtbcn.cat/assets/crt-logo.png" alt="CRT - Centre de Rehabilitació Terapèutica" height="52" style="display:block; margin:0 auto 12px;" />
              <table cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="background-color:#00806C; border-radius:20px; padding:6px 14px;">
                    <span style="color:#ffffff; font-size:11px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; font-family:Arial, sans-serif;">Benvingut/da a CRT RRHH</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:32px 40px;">

              <p style="margin:0 0 8px 0; font-size:15px; color:#222222; font-family:Arial, sans-serif;">
                Hola, <strong>{{ $user->name }}</strong>,
              </p>

              <p style="margin:0 0 24px 0; font-size:14px; color:#555555; line-height:1.6; font-family:Arial, sans-serif;">
                El teu compte a <strong>CRT RRHH</strong> ha estat creat correctament.
                A continuació trobaràs les teves credencials d'accés inicials.
              </p>

              <!-- Info box -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F8F7; border-radius:10px; border:1px solid #D3E8E3; margin-bottom:24px;">
                <tr>
                  <td style="padding:18px 22px;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td style="font-size:13px; font-weight:700; color:#00806C; padding:5px 0; width:44%; font-family:Arial, sans-serif;">Correu electrònic</td>
                        <td style="font-size:13px; color:#333333; padding:5px 0; font-family:Arial, sans-serif;">{{ $user->email }}</td>
                      </tr>
                      <tr>
                        <tr>
			  <td style="font-size:13px; font-weight:700; color:#00806C; padding:5px 0; font-family:Arial, sans-serif;">Perfil professional</td>
  			  <td style="font-size:13px; color:#333333; padding:5px 0; font-family:Arial, sans-serif;">{{ $user->job_profile ?? '-' }}</td>
			</tr>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <!-- Code label -->
              <p style="margin:0 0 10px 0; font-size:11px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:#00806C; text-align:center; font-family:Arial, sans-serif;">
                Contrasenya inicial
              </p>

              <!-- Password box -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
                <tr>
                  <td align="center" style="background-color:#00806C; border-radius:12px; padding:24px 20px;">
                    <span style="color:#ffffff; font-size:28px; font-weight:800; letter-spacing:4px; font-family:'Courier New', Courier, monospace;">{{ $password }}</span>
                  </td>
                </tr>
              </table>

              <!-- Warning box -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
                <tr>
                  <td width="4" style="background-color:#f59e0b;">&nbsp;</td>
                  <td style="background-color:#fffceb; padding:14px 18px; border-radius:0 10px 10px 0;">
                    <p style="margin:0; font-size:13px; color:#555555; line-height:1.7; font-family:Arial, sans-serif;">
                      <strong style="color:#92650a;">Important:</strong>
                      Per motius de seguretat, et recomanem canviar la contrasenya un cop hagis iniciat sessió per primera vegada.
                    </p>
                  </td>
                </tr>
              </table>

              <p style="margin:0; font-size:13px; color:#666666; line-height:1.6; font-family:Arial, sans-serif;">
                Accedeix al portal a través de:
                <a href="https://crtrrhh.crtbcn.cat" style="color:#00806C; font-weight:700;">crtrrhh.crtbcn.cat</a>
              </p>

            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#F4F8F7; padding:18px 40px; text-align:center; border-top:1px solid #E1EEEB;">
              <p style="margin:0; font-size:11px; color:#888888; font-family:Arial, sans-serif;">
                <strong style="color:#666666;">CRT &mdash; Centre de Rehabilitació Terapèutica</strong><br>
                Aquest missatge és automàtic, no cal respondre.
              </p>
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>

</body>
</html>
