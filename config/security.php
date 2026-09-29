<?php

return [
    /*
     | SEGON FACTOR — tres interruptors, de menys a més abast. Vegeu docs/SEGON-FACTOR.md.
     |
     | 0. VOLUNTARI (estat per defecte, cap variable a tocar): el 2FA està DISPONIBLE però no
     |    s'imposa. Qui completa l'enrolament TOTP se l'exigeix a si mateix; la resta de la
     |    plantilla entra exactament com fins ara. No canvia la dinàmica de treball de ningú.
     |
     | 1. PER ROLS: SECOND_FACTOR_ROLES=admin,hr → obligatori NOMÉS per a aquests rols.
     |    És l'esglaó que permet a Direcció començar pels comptes amb més privilegi.
     |
     | 2. TOTHOM (Fase B): SECOND_FACTOR_REQUIRED=true → obligatori per a tota la plantilla.
     |
     | Kill-switch d'emergència per sobre de tots: SECOND_FACTOR_OFF=1 (deixa entrar sense
     | segon factor encara que els altres estiguin activats).
     */
    'second_factor_required' => env('SECOND_FACTOR_REQUIRED', false),

    // Llista de rols separats per comes: admin, coordinator, hr, worker. Buit = cap.
    'second_factor_roles' => env('SECOND_FACTOR_ROLES', ''),
];
