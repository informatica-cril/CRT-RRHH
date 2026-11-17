<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incidencia;
use App\Models\WorkLog;
use App\Models\WorkLogAlert;
use App\Services\HorariDia;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * GET /api/v1/notificacions — tot el que l'app ha d'avisar a la persona, en una sola crida:
 *  - les seves alertes pendents (fitxatge, pausa, audiència, tram rebutjat…),
 *  - les respostes que li han donat a les seves incidències i encara no ha vist,
 *  - si gestiona incidències (RRHH, Informàtica, Superior): les que són noves al seu àmbit.
 * Les notificacions no es guarden: són l'estat actual. Desapareixen soles quan la causa es resol
 * (alerta descartada, resposta vista, incidència atesa), així el comptador baixa de veres.
 */
class NotificacionsController extends Controller
{
    private const URGENTS = ['no_clock_out', 'break_required', 'audiencia', 'segment_rejected'];

    private const TITOLS = [
        'no_clock_in' => "No has fitxat l'entrada", 'no_clock_out' => 'Tens un fitxatge sense tancar',
        'break_required' => 'Cal fer la pausa obligatòria', 'audiencia' => 'Marcatge pendent de revisió',
        'segment_rejected' => 'Una part del fitxatge s\'ha rebutjat', 'out_of_zone' => 'Fitxatge fora de zona',
        'resolucio_rrhh' => 'Sol·licitud resolta',
    ];

    public function index(Request $request)
    {
        $u = $request->user();
        $items = [];

        $alertes = WorkLogAlert::where('user_id', $u->id)->pending()
            ->where(fn ($q) => $q->whereNull('sent_at')->orWhere('sent_at', '<=', now()))
            ->orderBy('scheduled_at')->limit(50)->get();
        foreach ($alertes as $a) {
            $detall = in_array($a->type, ['audiencia', 'segment_rejected'], true) && $a->work_log_id;
            $items[] = [
                'id' => "alerta-{$a->id}", 'tipus' => 'alerta', 'nivell' => in_array($a->type, self::URGENTS, true) ? 'urgent' : 'info',
                'titol' => self::TITOLS[$a->type] ?? 'Tens un avís pendent', 'text' => (string) $a->message,
                'enllac' => $detall ? "/work-logs/{$a->work_log_id}/detail" : null,
                'accio' => $detall ? 'Obrir i respondre' : null, 'alerta_id' => $a->id,
            ];
        }

        // Jornada d'avui sense tancar quan l'horari ja ha acabat (abans era un correu). Surt mentre continuï oberta.
        $ara = now('Europe/Madrid');
        $oberta = WorkLog::where('user_id', $u->id)->whereNull('end_time')->whereDate('date', $ara->toDateString())->latest('id')->first();
        if ($oberta) {
            $trams = HorariDia::trams($u, $ara->toDateString());
            $final = $trams ? (end($trams)['end'] ?? null) : null;
            if ($final && $ara->gte(Carbon::parse($ara->toDateString() . ' ' . $final, 'Europe/Madrid'))) {
                $items[] = [
                    'id' => "jornada-oberta-{$oberta->id}", 'tipus' => 'alerta', 'nivell' => 'urgent',
                    'titol' => 'Tens un fitxatge sense tancar',
                    'text' => "La teva jornada d'avui havia d'acabar a les " . substr($final, 0, 5) . ' i encara no has fitxat la sortida.',
                    'enllac' => '/worker', 'accio' => 'Anar a fitxar',
                ];
            }
        }

        $meves = Incidencia::with('missatges')->where('user_id', $u->id)->latest()->limit(100)->get();
        foreach ($meves as $i) {
            $vist = $i->vist_treballador_at;
            $ultim = $i->missatges->where('user_id', '!=', $i->user_id)->sortBy('id')->last();
            $quan = $ultim?->created_at;
            if ($i->resolta_at && (! $quan || $i->resolta_at->gt($quan))) $quan = $i->resolta_at;
            if ($quan && (! $vist || $quan->gt($vist))) {
                $items[] = [
                    'id' => "resposta-{$i->id}-" . $quan->timestamp, 'tipus' => 'incidencia', 'nivell' => 'info',
                    'titol' => 'Resposta a la teva incidència', 'text' => $i->assumpte,
                    'enllac' => '/worker/incidencia', 'accio' => 'Veure la resposta',
                ];
            }
        }

        $destinataris = IncidenciaController::destinatarisDe($u);
        if ($destinataris) {
            $noves = Incidencia::peraAtendre($destinataris);
            $n = $noves->count();
            if ($n > 0) {
                $items[] = [
                    'id' => 'incidencies-noves-' . $noves->max('id') . '-' . $noves->sum(fn ($i) => $i->missatges->count()), 'tipus' => 'incidencia', 'nivell' => 'urgent',
                    'titol' => $n === 1 ? 'Tens una incidència per atendre' : "Tens {$n} incidències per atendre",
                    'text' => 'Algú espera la teva resposta.', 'enllac' => '/incidencies', 'accio' => 'Obrir les incidències',
                ];
            }
        }

        // L'id és fix: si canvia qui hi és no és un avís nou (abans tornava a sortir després de la ✕).
        // Saldo de minuts: persones al tope. RRHH i Superior han de decidir què se'n fa; desapareix quan ho decideixen.
        if (in_array($u->role, ['admin', 'hr'], true)) {
            $tope = \App\Services\SaldoMinuts::alTope();
            if ($tope) {
                $noms = collect($tope)->take(3)->pluck('nom')->implode(', ');
                $mes = count($tope) - 3;
                $items[] = [
                    'id' => 'saldo-tope', 'tipus' => 'saldo', 'nivell' => 'urgent',
                    'titol' => count($tope) === 1 ? '1 persona ha arribat al límit del saldo de minuts' : count($tope) . ' persones han arribat al límit del saldo de minuts',
                    'text' => $noms . ($mes > 0 ? " i {$mes} més" : '') . '. Cal decidir què se\'n fa: compensar-lo, ajustar-lo o posar-lo a zero.',
                    'enllac' => '/work-logs?saldos=tope', 'accio' => 'Veure i decidir',
                ];
            }
        }

        return response()->json(['items' => $items, 'total' => count($items)]);
    }
}
