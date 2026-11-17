<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\SaldoMinuts;
use App\Support\DocumentCorporatiu;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Full mensual d'hores d'una persona, per imprimir (PDF des del navegador) o baixar en Word, amb dues
 * signatures: la de la persona i la de l'empresa. Complementa l'Excel de WorkLogExportController.
 * No porta coordenades ni cap altra dada de ubicació. Sense dependències noves: és HTML.
 */
class FullMensualController extends Controller
{
    public function show(Request $request)
    {
        $data = $request->validate([
            'mes'     => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'user_id' => 'required|integer|exists:users,id',
            'format'  => 'nullable|in:pdf,word',
        ]);
        $format = $data['format'] ?? 'pdf';
        $ini = Carbon::createFromFormat('Y-m-d', $data['mes'] . '-01');
        $persona = User::findOrFail($data['user_id']);

        $logs = WorkLog::where('user_id', $persona->id)
            ->whereBetween('date', [$ini->toDateString(), $ini->copy()->endOfMonth()->toDateString()])
            ->whereNotNull('end_time')->where('status', '!=', 'rejected')
            ->orderBy('date')->orderBy('start_time')->get();

        $html = $this->html($persona, $ini, $logs, $format);

        try {
            AuditLog::create([
                'user_id' => $request->user()->id, 'action' => 'EXPORT_FULL_MENSUAL', 'entity_type' => 'work_log',
                'description' => "Full mensual d'hores {$data['mes']} de {$persona->name} ({$format}, {$logs->count()} registres)",
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($format === 'word') {
            // Word obre sense problema un HTML amb aquest tipus: no cal cap llibreria.
            return response($html, 200, [
                'Content-Type' => 'application/msword; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="full_mensual_' . $data['mes'] . '_' . $persona->id . '.doc"',
                'Cache-Control' => 'no-store, no-cache',
            ]);
        }

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store, no-cache']);
    }

    private function html(User $persona, Carbon $ini, $logs, string $format): string
    {
        $e = fn ($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
        $hm = function (float $h): string {
            $m = (int) round($h * 60);

            return intdiv($m, 60) . ' h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT) . ' min';
        };
        $mes = mb_convert_case($ini->copy()->locale('ca')->translatedFormat('F Y'), MB_CASE_TITLE);
        $saldoActiu = SaldoMinuts::activa();
        $apunts = $saldoActiu
            ? \App\Models\SaldoMinutsApunt::whereIn('work_log_id', $logs->pluck('id'))->get()->keyBy('work_log_id')
            : collect();

        $files = '';
        $total = 0.0;
        $extraAut = 0.0;
        $extraPend = 0.0;
        foreach ($logs as $l) {
            $ent = (string) $l->getRawOriginal('start_time');
            $sor = (string) $l->getRawOriginal('end_time');
            $h = (float) ($l->effective_hours ?? $l->total_hours_worked ?? 0);
            $total += $h;
            $extraAut += (float) $l->extra_hours_authorized;
            $extraPend += (float) $l->extra_hours_unauthorized;
            $pausa = ($l->getRawOriginal('break_start_time') && $l->getRawOriginal('break_end_time'))
                ? substr((string) $l->getRawOriginal('break_start_time'), 11, 5) . '–' . substr((string) $l->getRawOriginal('break_end_time'), 11, 5) : '';
            $ap = $apunts->get($l->id);
            $nota = $ap && (int) $ap->delta_aplicat !== 0 ? ('saldo ' . ((int) $ap->delta_aplicat > 0 ? '+' : '') . (int) $ap->delta_aplicat . ' min') : '';
            $dia = Carbon::parse(substr((string) $l->getRawOriginal('date'), 0, 10))->locale('ca');
            $files .= '<tr><td>' . $e($dia->translatedFormat('D j')) . '</td><td>' . $e(substr($ent, 11, 5)) . '</td><td>'
                . $e(substr($sor, 0, 10) !== substr($ent, 0, 10) ? Carbon::parse($sor)->format('d/m H:i') : substr($sor, 11, 5))
                . '</td><td>' . $e($pausa) . '</td><td class="d">' . $e($hm($h)) . '</td><td>' . $e($nota) . '</td></tr>';
        }
        if ($files === '') $files = '<tr><td colspan="6" style="text-align:center">Cap fitxatge aquest mes.</td></tr>';

        $saldo = $saldoActiu ? 'Saldo de minuts actual: <b>' . (SaldoMinuts::saldo($persona->id)) . ' min</b>' : '';
        $logo = DocumentCorporatiu::logo();
        $estils = 'body{font-family:Arial,Helvetica,sans-serif;font-size:10.5pt;color:#1d2733;margin:18mm 16mm}'
            . 'h1{font-size:15pt;color:#0A2A4A;margin:0 0 4px}.sub{color:#5D6B7E;margin-bottom:12px}'
            . 'table{border-collapse:collapse;width:100%;margin-top:10px}th,td{border:1px solid #c9d3df;padding:4px 7px;text-align:left;font-size:9.5pt}'
            . 'th{background:#0A2A4A;color:#fff}td.d{text-align:right}tfoot td{font-weight:700;background:#eef3f8}'
            . '.sig{width:100%;margin-top:46px}.sig td{border:0;width:50%;padding:0 18px;vertical-align:bottom}'
            . '.lin{border-top:1px solid #1d2733;margin-top:60px;padding-top:4px;font-size:9.5pt}.peu{margin-top:26px;font-size:8pt;color:#8a97a8;text-align:center}'
            . '@page{size:A4;margin:12mm}@media print{.eines{display:none}}';

        return '<!DOCTYPE html><html lang="ca"><head><meta charset="utf-8"><title>Full mensual ' . $e($mes) . ' · ' . $e($persona->name) . '</title>'
            . '<style>' . $estils . '</style></head><body>'
            . ($logo ? '<img src="' . $logo . '" alt="CRT" style="height:44px"><br>' : '<b style="font-size:15pt">CRT</b><br>')
            . '<h1>Full mensual d\'hores · ' . $e($mes) . '</h1>'
            . '<div class="sub">' . $e($persona->name) . ($persona->dni ? ' · DNI ' . $e($persona->dni) : '') . ' · Registre de jornada (art. 34.9 ET)</div>'
            . '<table><thead><tr><th>Dia</th><th>Entrada</th><th>Sortida</th><th>Pausa</th><th>Hores</th><th>Notes</th></tr></thead><tbody>'
            . $files . '</tbody><tfoot><tr><td colspan="4">Total del mes (' . $logs->count() . ' jornades)</td><td class="d">' . $e($hm($total)) . '</td><td></td></tr></tfoot></table>'
            . '<p>Hores de més acceptades: <b>' . $e($hm($extraAut)) . '</b> · Sense autoritzar: <b>' . $e($hm($extraPend)) . '</b>' . ($saldo ? ' · ' . $saldo : '') . '</p>'
            . '<table class="sig"><tr><td><div class="lin">Signatura del treballador/a<br>' . $e($persona->name) . '<br>Data:</div></td>'
            . '<td><div class="lin">Signatura de l\'empresa (CRT)<br>Nom i càrrec:<br>Data:</div></td></tr></table>'
            . '<div class="peu">Document generat des de CRT RRHH el ' . now('Europe/Madrid')->format('d/m/Y H:i') . '</div>'
            . ($format === 'pdf' ? '<div class="eines" style="text-align:right;margin-top:14px"><button onclick="window.print()">Imprimir o desar com a PDF</button></div>' : '')
            . '</body></html>';
    }
}
