<?php

namespace App\Support;

class DocumentCorporatiu
{
    private static function logo(): string
    {
        foreach (['assets/crt-logo.png', 'logo/CRT-LOGO-removebg2.png', 'logo/CRT-LOGO.png'] as $rel) {
            $f = public_path($rel);
            if (is_file($f)) {
                return 'data:image/png;base64,' . base64_encode((string) file_get_contents($f));
            }
        }

        return '';
    }

    private static function estils(): string
    {
        return <<<'CSS'
@page { size: A4; margin: 22mm 18mm 20mm 18mm; }
* { box-sizing: border-box; }
body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; color: #1d2733;
       font-size: 10.5pt; line-height: 1.62; margin: 0; background: #f0f3f7; }
.full { max-width: 210mm; margin: 0 auto; background: #fff; padding: 18mm 16mm 14mm;
        box-shadow: 0 2px 18px rgba(10,42,74,.10); }
.cap { display: flex; justify-content: space-between; align-items: flex-start;
       border-bottom: 2.5px solid #094E8C; padding-bottom: 11px; margin-bottom: 20px; }
.cap img { height: 46px; }
.cap .emp { text-align: right; font-size: 8.4pt; color: #5D6B7E; line-height: 1.45; }
.cap .emp b { color: #0A2A4A; font-size: 9.6pt; display: block; }
.ref { font-size: 8.2pt; color: #7a8aa0; letter-spacing: .04em; text-transform: uppercase; }
h1 { font-size: 15pt; color: #0A2A4A; margin: 0 0 3px; line-height: 1.28; }
.sub { color: #5D6B7E; font-size: 9.4pt; margin-bottom: 18px; }
.meta { background: #f4f8fc; border-left: 3px solid #094E8C; padding: 10px 14px;
        margin-bottom: 20px; font-size: 9.2pt; color: #3c4a5c; }
.meta span { display: inline-block; margin-right: 22px; }
.meta b { color: #0A2A4A; }
.cos { white-space: pre-wrap; text-align: justify; }
.cos-titol { font-weight: 700; color: #0A2A4A; margin-top: 15px; }
.signatura { margin-top: 30px; border-top: 1px solid #dbe4ef; padding-top: 14px;
             display: flex; justify-content: space-between; gap: 22px; font-size: 9pt; }
.signatura .box { flex: 1; }
.signatura .et { color: #7a8aa0; text-transform: uppercase; font-size: 7.8pt; letter-spacing: .05em; }
.signatura .val { color: #0A2A4A; font-weight: 700; margin-top: 2px; }
.segell { margin-top: 12px; font-size: 7.6pt; color: #8a97a8; word-break: break-all; }
.peu { margin-top: 24px; border-top: 1px solid #e6edf5; padding-top: 9px;
       font-size: 7.8pt; color: #8a97a8; text-align: center; line-height: 1.5; }
.eines { max-width: 210mm; margin: 14px auto 0; text-align: right; }
.eines button { border: 0; background: #094E8C; color: #fff; border-radius: 8px;
                padding: 10px 20px; font-weight: 700; cursor: pointer; font-size: 10pt; }
@media print {
  body { background: #fff; }
  .full { box-shadow: none; padding: 0; max-width: none; }
  .eines { display: none; }
}
CSS;
    }

    /**
     * @param  array{titol:string,subtitol?:string,referencia?:string,meta?:array<string,string>,
     *               cos:string,signant?:string,signat_ts?:string,hash?:string,peu?:string}  $d
     */
    public static function html(array $d): string
    {
        $logo = self::logo();
        $e = fn ($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');

        $meta = '';
        foreach (($d['meta'] ?? []) as $k => $v) {
            $meta .= '<span>' . $e($k) . ': <b>' . $e($v) . '</b></span>';
        }

        $cos = self::cos((string) ($d['cos'] ?? ''));

        $signatura = '';
        if (! empty($d['signant'])) {
            $signatura = '<div class="signatura">'
                . '<div class="box"><div class="et">Signat per</div><div class="val">' . $e($d['signant']) . '</div></div>'
                . '<div class="box"><div class="et">Data</div><div class="val">' . $e($d['signat_ts'] ?? '') . '</div></div>'
                . '<div class="box"><div class="et">Segell del document</div><div class="val" style="font-weight:400">'
                . $e(substr((string) ($d['hash'] ?? ''), 0, 32)) . '…</div></div>'
                . '</div>'
                . '<div class="segell">Qualsevol modificaci&oacute; del text posterior a la signatura invalida aquest segell.</div>';
        }

        return '<!DOCTYPE html><html lang="ca"><head><meta charset="utf-8">'
            . '<title>' . $e($d['titol']) . '</title><style>' . self::estils() . '</style></head><body>'
            . '<div class="full">'
            . '<div class="cap">'
            . ($logo ? '<img src="' . $logo . '" alt="CRT">' : '<b style="font-size:15pt;color:#0A2A4A">CRT</b>')
            . '<div class="emp"><b>CRT</b>Centre de Rehabilitaci&oacute; i Logop&egrave;dia<br>'
            . 'Rehabilitaci&oacute; domicili&agrave;ria concertada amb el CatSalut</div>'
            . '</div>'
            . (! empty($d['referencia']) ? '<div class="ref">' . $e($d['referencia']) . '</div>' : '')
            . '<h1>' . $e($d['titol']) . '</h1>'
            . (! empty($d['subtitol']) ? '<div class="sub">' . $e($d['subtitol']) . '</div>' : '')
            . ($meta ? '<div class="meta">' . $meta . '</div>' : '')
            . '<div class="cos">' . $cos . '</div>'
            . $signatura
            . '<div class="peu">' . ($d['peu'] ?? 'CRT &middot; document generat des de CRT') . '</div>'
            . '</div>'
            . '<div class="eines"><button onclick="window.print()">Imprimir o desar com a PDF</button></div>'
            . '</body></html>';
    }

    private static function cos(string $text): string
    {
        $out = '';
        foreach (explode("\n", $text) as $linia) {
            $t = rtrim($linia);
            if ($t === '') {
                $out .= "\n";
                continue;
            }
            $esTitol = $t === mb_strtoupper($t, 'UTF-8') && mb_strlen(trim($t)) > 3
                       && ! preg_match('/^[-\d]/', trim($t));
            $out .= $esTitol
                ? '<span class="cos-titol">' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</span>' . "\n"
                : htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . "\n";
        }

        return $out;
    }
}
