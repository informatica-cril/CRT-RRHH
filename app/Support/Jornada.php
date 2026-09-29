<?php

namespace App\Support;

/**
 * FONT ÚNICA dels paràmetres de jornada de CRT. Cap altre fitxer pot tornar a
 * escriure aquests números: fins ara convivien dues jornades anuals (1726 h i
 * 1867,5 h calculades dia a dia) i dos multiplicadors d'hora extraordinària
 * (1,3 i 1,25) a la mateixa pantalla.
 *
 * Referències:
 *  - Jornada anual: 1726 h (art. 31.2 del conveni XII sanitari).
 *  - Jornada completa setmanal: 37,5 h.
 *  - Extraordinàries: màxim 80 h/any a 1,25× (art. 20.1 del conveni XII).
 *  - Complementàries: màxim 30% de la jornada bàsica pactada (art. 12.5 ET) i
 *    NOMÉS en contractes a temps parcial (art. 12.4.c ET).
 */
final class Jornada
{
    /** Jornada anual efectiva a temps complet. */
    public const ANUAL_COMPLETA_H = 1726.0;

    /** Jornada setmanal a temps complet. */
    public const SETMANA_COMPLETA_H = 37.5;

    /** Topall anual d'hores extraordinàries per persona. */
    public const EXTRA_ANUAL_H = 80.0;

    /** Multiplicador retributiu de l'hora extraordinària. */
    public const EXTRA_FACTOR = 1.25;

    /** Màxim legal d'hores complementàries sobre la jornada bàsica (art. 12.5 ET). */
    public const COMPLEMENTARY_MAX_RATIO = 0.30;

    /**
     * Els mateixos números per a les pantalles: la SPA no els ha de tornar a
     * escriure, els demana amb la resposta que ja consumeix.
     */
    public static function parametres(): array
    {
        return [
            'jornada_anual_h' => self::ANUAL_COMPLETA_H,
            'setmana_completa_h' => self::SETMANA_COMPLETA_H,
            'extra_cap_h' => self::EXTRA_ANUAL_H,
            'extra_factor' => self::EXTRA_FACTOR,
            'complementary_max_ratio' => self::COMPLEMENTARY_MAX_RATIO,
            'referencia' => 'Conveni XII sanitari, art. 31.2 (jornada) i art. 20.1 (extraordinàries)',
        ];
    }
}
