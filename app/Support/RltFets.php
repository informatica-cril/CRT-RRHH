<?php

namespace App\Support;

use App\Models\DisciplinaryCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RltFets
{
    public const TIPUS = [
        'sistemes' => [
            'nom' => 'Sistemes d\'organització i control del treball, algoritmes i IA',
            'articles' => 'Articles 55.K.6 i 55.H del Conveni',
            'natura' => 'informe_previ',
            'periodicitat' => 'Abans d\'implantar cada sistema i cada cop que se\'n revisen els paràmetres.',
        ],
        'sancions' => [
            'nom' => 'Sancions per faltes molt greus',
            'articles' => 'Article 55.I del Conveni',
            'natura' => 'informacio',
            'periodicitat' => 'Cada cop que se n\'imposa una.',
        ],
        'complementaries' => [
            'nom' => 'Contractació a temps parcial i hores complementàries',
            'articles' => 'Article 48.4 del Conveni',
            'natura' => 'informacio',
            'periodicitat' => 'Trimestral.',
        ],
        'flexibilitat' => [
            'nom' => 'Flexibilitat horària aplicada',
            'articles' => 'Article 74.i del Conveni',
            'natura' => 'informacio',
            'periodicitat' => 'Semestral.',
        ],
        'general' => [
            'nom' => 'Situació de l\'empresa, ocupació, absentisme i sinistralitat',
            'articles' => 'Article 55 del Conveni (títol 3 de l\'Estatut dels treballadors)',
            'natura' => 'informacio',
            'periodicitat' => 'Trimestral.',
        ],
    ];

    public static function per(string $tipus, string $desde, string $fins): array
    {
        $base = [
            'tipus' => $tipus,
            'titol' => self::TIPUS[$tipus]['nom'] ?? $tipus,
            'articles' => self::TIPUS[$tipus]['articles'] ?? '',
            'natura' => self::TIPUS[$tipus]['natura'] ?? 'informacio',
            'periode' => ['desde' => $desde, 'fins' => $fins],
        ];

        return $base + match ($tipus) {
            'sistemes' => self::sistemes(),
            'sancions' => self::sancions($desde, $fins),
            'complementaries' => self::complementaries($desde, $fins),
            'flexibilitat' => self::flexibilitat($desde, $fins),
            'general' => self::general($desde, $fins),
            default => [],
        };
    }

    private static function parametres(?string $clau = null): array
    {
        $q = DB::table('servei_parametres');
        if ($clau) {
            $q->where('clau', $clau);
        }

        return $q->orderBy('servei')->get()->map(fn ($r) => [
            'servei' => $r->servei,
            'clau' => $r->clau,
            'valor' => json_decode($r->valor, true),
            'rebut' => $r->rebut_ts,
        ])->all();
    }

    private static function sistemes(): array
    {
        $files = self::parametres();
        if (! $files) {
            return [
                'serveis' => [],
                'avis' => 'Cap servei ha enviat encara els seus paràmetres. Sense això l\'escrit no pot descriure '
                        . 'els sistemes: cal executar el cron d\'enviament de paràmetres a cada aplicació.',
            ];
        }

        $perServei = [];
        foreach ($files as $f) {
            $perServei[$f['servei']][$f['clau']] = $f['valor'];
        }

        return [
            'serveis' => array_keys($perServei),
            'sistemes' => $perServei,
            'rebut' => collect($files)->max('rebut'),
        ];
    }

    private static function sancions(string $desde, string $fins): array
    {
        $casos = DisciplinaryCase::where('gravetat', 'molt_greu')
            ->whereNotNull('resolucio_tipus')
            ->where('resolucio_tipus', '!=', 'arxiu')
            ->whereBetween('updated_at', [$desde . ' 00:00:00', $fins . ' 23:59:59'])
            ->get();

        return [
            'total' => $casos->count(),
            'pendents_informar' => $casos->whereNull('rlt_informat_ts')->count(),
            'sancions' => $casos->map(fn ($c) => [
                'data' => optional($c->updated_at)->toDateString(),
                'tipus_falta' => $c->tipus_falta,
                'sancio' => ConveniSancions::etiqueta((string) $c->resolucio_tipus),
                'dies' => $c->resolucio_dies,
                'ja_informada' => $c->rlt_informat_ts !== null,
            ])->values()->all(),
            'nota' => 'L\'escrit no ha d\'identificar la persona sancionada: l\'article 55.I obliga a informar de '
                    . 'les sancions, no a difondre dades personals més enllà del necessari.',
        ];
    }

    private static function complementaries(string $desde, string $fins): array
    {
        $parcials = User::where('role', 'worker')->get()
            ->filter(fn ($u) => ! method_exists($u, 'esAutonom') || ! $u->esAutonom());

        $minuts = DB::table('work_logs')
            ->whereBetween('date', [$desde, $fins])
            ->selectRaw('user_id, SUM(COALESCE(complementary_minutes,0)) m')
            ->groupBy('user_id')->pluck('m', 'user_id');

        $files = [];
        foreach ($parcials as $u) {
            $m = (int) ($minuts[$u->id] ?? 0);
            if ($m <= 0) {
                continue;
            }
            $files[] = ['persona' => $u->name, 'hores' => round($m / 60, 1)];
        }

        return [
            'plantilla_total' => $parcials->count(),
            'persones_amb_complementaries' => count($files),
            'hores_totals' => round(array_sum(array_column($files, 'hores')), 1),
            'detall' => $files,
            'topalls' => 'El Conveni limita les complementàries pactades al 50% de les ordinàries, exigeix un '
                       . 'preavís de 7 dies i prohibeix que contracte, complementàries i extraordinàries superin '
                       . 'la jornada màxima (article 48.4).',
        ];
    }

    private static function flexibilitat(string $desde, string $fins): array
    {
        $files = self::parametres('flexibilitat_periode');
        $dades = [];
        foreach ($files as $f) {
            $dades[$f['servei']] = $f['valor'];
        }

        return [
            'per_servei' => $dades,
            'avis' => $dades ? null : 'Cap servei ha enviat encara les dades de flexibilitat del període.',
            'topalls' => 'Màxim 120 hores l\'any i 60 el semestre per persona, proporcionals a la jornada '
                       . 'contractada (article 74).',
        ];
    }

    private static function general(string $desde, string $fins): array
    {
        $plantilla = User::where('role', 'worker')->count();
        $altes = User::where('role', 'worker')
            ->whereBetween('created_at', [$desde . ' 00:00:00', $fins . ' 23:59:59'])->count();

        $absencies = DB::table('absences as a')
            ->leftJoin('absence_types as t', 't.id', '=', 'a.absence_type_id')
            ->whereBetween('a.start_date', [$desde, $fins])
            ->selectRaw('COALESCE(t.name, "sense tipus") tipus, COUNT(*) n')
            ->groupBy('tipus')->get()
            ->mapWithKeys(fn ($r) => [$r->tipus => (int) $r->n])->all();

        $hores = DB::table('work_logs')
            ->whereBetween('date', [$desde, $fins])
            ->selectRaw('ROUND(SUM(COALESCE(effective_hours,0)),1) h, COUNT(*) jornades')
            ->first();

        return [
            'plantilla' => $plantilla,
            'altes_al_periode' => $altes,
            'jornades_registrades' => (int) ($hores->jornades ?? 0),
            'hores_efectives' => (float) ($hores->h ?? 0),
            'absencies_per_tipus' => $absencies,
            'pendent_a_ma' => [
                'Evolució econòmica del sector i situació de l\'empresa.',
                'Previsions de nova contractació i modalitats previstes.',
                'Sinistralitat i malalties professionals, si n\'hi ha hagut.',
                'Aplicació del dret d\'igualtat entre dones i homes (dada anual).',
            ],
        ];
    }
}
