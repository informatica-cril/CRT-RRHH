<?php

namespace App\Support;

use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryFaultType;
use Carbon\Carbon;

class RaonamentDisciplinari
{
    private const TOPE_MESOS = 6;

    private const DIES_PRESCRIPCIO = [
        'lleu' => 10,
        'menys_greu' => 10,
        'greu' => 20,
        'molt_greu' => 60,
    ];

    public static function per(DisciplinaryCase $case): array
    {
        $tipus = DisciplinaryFaultType::where('clau', $case->tipus_falta)->first();
        $gravetat = $case->gravetat ?: ($tipus->grau_base ?? null);

        return [
            'falta' => self::falta($case, $tipus, $gravetat),
            'sancio' => self::sancio($gravetat),
            'terminis' => self::terminis($case, $gravetat),
            'obligacions' => self::obligacions($case, $gravetat),
            'jerarquia' => 'Mana el XII Conveni sanitari de Catalunya (art. 7: les seves normes s\'apliquen '
                         . 'amb caràcter preferent). L\'Estatut dels treballadors només entra on el Conveni no '
                         . 'regula res, i la resta de normes laborals després.',
        ];
    }

    private static function falta(DisciplinaryCase $case, ?DisciplinaryFaultType $tipus, ?string $gravetat): array
    {
        if (! $tipus) {
            return ['ok' => false, 'text' => 'El tipus de falta «' . $case->tipus_falta
                . '» no és al catàleg: no es pot dir de quin article penja.'];
        }
        $text = 'La conducta imputada és «' . $tipus->descripcio . '», que el Conveni tipifica a l\''
              . str_replace('art.', 'article ', (string) $tipus->base_conveni) . ' com a falta '
              . str_replace('_', ' ', (string) $tipus->grau_base) . '.';
        if ($tipus->base_et) {
            $text .= ' Arriba al Conveni a través de l\'article 64.4.A, que remet a l\''
                   . str_replace('art.', 'article ', (string) $tipus->base_et) . ' de l\'Estatut dels treballadors.';
        }
        if ($gravetat && $gravetat !== $tipus->grau_base) {
            $text .= ' En aquest cas s\'ha graduat com a ' . str_replace('_', ' ', $gravetat)
                   . ', diferent del grau base del tipus: la motivació ha d\'explicar per què.';
        }
        if ($tipus->requereix_afectacio_servei) {
            $text .= ' El tipus exigeix que el fet hagi afectat la bona marxa del servei; sense acreditar-ho, no encaixa.';
        }
        if ($tipus->requereix_apercebiment) {
            $text .= ' El tipus exigeix apercebiment previ.';
        }

        return ['ok' => true, 'text' => $text, 'article' => $tipus->base_conveni, 'grau_base' => $tipus->grau_base];
    }

    private static function sancio(?string $gravetat): array
    {
        $g = ConveniSancions::TAULA[$gravetat] ?? null;
        if (! $g) {
            return ['ok' => false, 'text' => 'Sense gravetat fixada no es pot dir quina forquilla de sanció permet l\'article 68.'];
        }
        $permeses = array_map([ConveniSancions::class, 'etiqueta'], array_diff($g['tipus'], ['arxiu']));

        return [
            'ok' => true,
            'text' => sprintf(
                'L\'article 68 del Conveni només permet, per a una falta %s: %s. La suspensió de feina i sou ha '
              . 'd\'anar de %d a %d dies. Qualsevol sanció fora d\'aquesta forquilla és impugnable sense més discussió.',
                str_replace('_', ' ', $gravetat), implode(', ', $permeses), $g['suspensio_min'], $g['suspensio_max']
            ),
            'tipus_permesos' => array_values(array_diff($g['tipus'], ['arxiu'])),
            'suspensio_min' => $g['suspensio_min'],
            'suspensio_max' => $g['suspensio_max'],
        ];
    }

    private static function terminis(DisciplinaryCase $case, ?string $gravetat): array
    {
        $dies = self::DIES_PRESCRIPCIO[$gravetat] ?? null;
        $coneixement = $case->data_coneixement ? Carbon::parse($case->data_coneixement) : null;
        $fet = $case->data_fet ? Carbon::parse($case->data_fet) : null;
        $avui = Carbon::today();

        $avisos = [];
        $prescrit = false;

        if ($coneixement && $dies) {
            $passats = (int) $coneixement->diffInDays($avui, false);
            $resten = $dies - $passats;
            if ($resten < 0) {
                $prescrit = true;
                $avisos[] = sprintf('PRESCRIT pel termini curt: han passat %d dies des que l\'empresa en va tenir '
                    . 'coneixement i l\'article 60.2 de l\'Estatut en dóna %d per a una falta %s.',
                    $passats, $dies, str_replace('_', ' ', (string) $gravetat));
            } elseif ($resten <= 3) {
                $avisos[] = sprintf('Queden %d dies del termini de %d que dóna l\'article 60.2 de l\'Estatut des '
                    . 'del coneixement. Si s\'ha de sancionar, ha de ser ja.', $resten, $dies);
            }
        }

        if ($fet) {
            $desDelFet = (int) $fet->diffInDays($avui, false);
            $topeDies = self::TOPE_MESOS * 30;
            if ($desDelFet > $topeDies) {
                $prescrit = true;
                $avisos[] = sprintf('PRESCRIT pel tope incondicional: han passat %d dies des del fet i l\'article '
                    . '60.2 prescriu tota falta als sis mesos d\'haver-se comès, sigui quina sigui la data de '
                    . 'coneixement.', $desDelFet);
            } elseif ($desDelFet > $topeDies - 30) {
                $avisos[] = sprintf('Queden uns %d dies del tope de sis mesos des del fet.', $topeDies - $desDelFet);
            }
        }

        return [
            'prescrit' => $prescrit,
            'dies_termini' => $dies,
            'avisos' => $avisos,
            'text' => 'El Conveni no regula la prescripció de les faltes (capítol 9, articles 61 a 68), així que '
                    . 'per l\'article 7 s\'aplica supletòriament l\'article 60.2 de l\'Estatut: 10 dies les lleus, '
                    . '20 les greus i 60 les molt greus des que l\'empresa en té coneixement, i en tot cas sis '
                    . 'mesos des que es va cometre. Les faltes MENYS GREUS són una categoria que crea el Conveni i '
                    . 'que l\'Estatut no coneix: aquí es fa servir el termini curt de 10 dies, que és la lectura '
                    . 'prudent, per decisió de Direcció del 06-08-2026.',
        ];
    }

    private static function obligacions(DisciplinaryCase $case, ?string $gravetat): array
    {
        $out = [];
        if ($gravetat === 'molt_greu') {
            $out[] = [
                'clau' => 'rlt_sancio',
                'compleix' => $case->rlt_informat_ts !== null,
                'text' => 'L\'article 55.I obliga a informar la representació legal de TOTES les sancions per '
                        . 'faltes molt greus. Aquesta encara consta com a no comunicada.',
            ];
        }
        if ($case->es_representant) {
            $out[] = [
                'clau' => 'representant',
                'compleix' => $case->expedient_contradictori,
                'text' => 'La persona és representant: cal expedient contradictori amb audiència de la resta de '
                        . 'la representació, i els delegats sindicals han de ser escoltats abans (article 56.3).',
            ];
        }
        $out[] = [
            'clau' => 'prova_licita',
            'compleix' => (bool) $case->te_evidencia_licita,
            'text' => 'L\'evidència ha de ser lícita i obtinguda d\'un tractament informat. Si ve del panell de '
                    . 'rendiment, la informació prèvia a les persones treballadores i a la representació ha '
                    . 'd\'estar donada abans del fet.',
        ];

        return $out;
    }
}
