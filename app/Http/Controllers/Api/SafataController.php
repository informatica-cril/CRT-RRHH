<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\ComiteHora;
use App\Models\DretsSollicitud;
use App\Models\Excedencia;
use App\Models\WorkLog;
use App\Services\DisciplinaryEngine;
use Illuminate\Http\Request;

/**
 * Recompte de la Safata per a la campana de la capçalera.
 *
 * La pantalla de la Safata descarrega els llistats sencers per comptar-los; la campana es refresca
 * cada minut i no pot fer el mateix. Aquí només hi ha COUNTs. Queda fora la conciliació amb domi:
 * depèn d'un servei extern i no es pot consultar cada minut des de la capçalera.
 */
class SafataController extends Controller
{
    public function resum(Request $request, DisciplinaryEngine $engine)
    {
        $gestio = in_array($request->user()->role, ['admin', 'hr'], true);

        $cues = [
            ['clau' => 'forazona', 'titol' => 'Fitxatges fora de zona', 'icona' => '📍', 'urgencia' => 'alta', 'enllac' => '/work-logs',
                'n' => WorkLog::where('status', 'pending')->where('hour_status', 'out_of_area')->count()],
            ['clau' => 'fitxatges', 'titol' => "Fitxatges pendents d'aprovar", 'icona' => '⏱', 'urgencia' => 'mitjana', 'enllac' => '/work-logs',
                'n' => WorkLog::where('status', 'pending')->count()],
            ['clau' => 'absencies', 'titol' => 'Permisos per aprovar', 'icona' => '📋', 'urgencia' => 'mitjana', 'enllac' => '/absences',
                'n' => Absence::whereNull('approved')->count()],
            ['clau' => 'excedencies', 'titol' => 'Excedències per resoldre', 'icona' => '📄', 'urgencia' => 'mitjana', 'enllac' => '/excedencies',
                'n' => Excedencia::where('status', 'pending')->count()],
        ];

        if ($gestio) {
            $suggeriments = 0;
            try {
                $suggeriments = count($engine->suggeriments());
            } catch (\Throwable $e) {
                report($e);
            }
            $cues[] = ['clau' => 'disciplinari', 'titol' => 'Suggeriments disciplinaris', 'icona' => '⚖️', 'urgencia' => 'alta', 'enllac' => '/disciplinary', 'n' => $suggeriments];
            $cues[] = ['clau' => 'drets', 'titol' => 'Drets RGPD per respondre', 'icona' => '🔐', 'urgencia' => 'alta', 'enllac' => '/privacy',
                'n' => DretsSollicitud::whereNull('resposta_ts')->count()];
            $cues[] = ['clau' => 'comite', 'titol' => 'Hores de comitè per validar', 'icona' => '🤝', 'urgencia' => 'mitjana', 'enllac' => '/comite',
                'n' => ComiteHora::where('estat', 'pendent')->count()];
        }

        $cues = array_values(array_filter($cues, fn ($c) => $c['n'] > 0));

        return response()->json([
            'total' => array_sum(array_column($cues, 'n')),
            'cues'  => $cues,
        ]);
    }
}
