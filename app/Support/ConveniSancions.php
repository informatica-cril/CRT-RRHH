<?php

namespace App\Support;

class ConveniSancions
{
    public const TAULA = [
        'lleu' => [
            'tipus' => ['amonestacio', 'suspensio', 'arxiu'],
            'suspensio_min' => 1,
            'suspensio_max' => 2,
        ],
        'menys_greu' => [
            'tipus' => ['suspensio', 'arxiu'],
            'suspensio_min' => 3,
            'suspensio_max' => 10,
        ],
        'greu' => [
            'tipus' => ['suspensio', 'arxiu'],
            'suspensio_min' => 10,
            'suspensio_max' => 20,
        ],
        'molt_greu' => [
            'tipus' => ['suspensio', 'trasllat', 'inhabilitacio', 'acomiadament', 'arxiu'],
            'suspensio_min' => 21,
            'suspensio_max' => 60,
        ],
    ];

    public static function etiqueta(string $tipus): string
    {
        return [
            'amonestacio' => 'amonestació',
            'suspensio' => 'suspensió de feina i sou',
            'trasllat' => 'trasllat de departament o servei',
            'inhabilitacio' => 'inhabilitació per pujar de categoria',
            'acomiadament' => 'acomiadament',
            'arxiu' => 'arxiu',
        ][$tipus] ?? $tipus;
    }

    /** [$ok, $motiu] segons l'article 68 del Conveni. */
    public static function valida(?string $gravetat, string $tipus, ?int $dies): array
    {
        if ($tipus === 'arxiu') {
            return [true, null];
        }
        $g = self::TAULA[$gravetat] ?? null;
        if (! $g) {
            return [false, 'La gravetat del cas no està fixada: sense ella no es pot comprovar que la sanció encaixi amb l\'article 68 del Conveni.'];
        }
        if (! in_array($tipus, $g['tipus'], true)) {
            $permeses = implode(', ', array_map([self::class, 'etiqueta'], array_diff($g['tipus'], ['arxiu'])));

            return [false, sprintf(
                'L\'article 68 del Conveni no preveu %s per a una falta %s. Per a aquesta gravetat només: %s.',
                self::etiqueta($tipus), str_replace('_', ' ', (string) $gravetat), $permeses
            )];
        }
        if ($tipus === 'suspensio') {
            if ($dies === null) {
                return [false, 'Cal indicar quants dies de suspensió: l\'article 68 en fixa una forquilla i sense el número no es pot comprovar.'];
            }
            if ($dies < $g['suspensio_min'] || $dies > $g['suspensio_max']) {
                return [false, sprintf(
                    'L\'article 68 fixa de %d a %d dies de suspensió per a una falta %s; se n\'han indicat %d.',
                    $g['suspensio_min'], $g['suspensio_max'], str_replace('_', ' ', (string) $gravetat), $dies
                )];
            }
        }
        if ($tipus === 'trasllat' && $dies !== null && ($dies < 90 || $dies > 365)) {
            return [false, 'L\'article 68 fixa el trasllat de 3 mesos a 1 any (de 90 a 365 dies).'];
        }
        if ($tipus === 'inhabilitacio' && $dies !== null && $dies > 1095) {
            return [false, 'L\'article 68 limita la inhabilitació per pujar de categoria a 3 anys (1.095 dies).'];
        }

        return [true, null];
    }
}
