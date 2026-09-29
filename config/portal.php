<?php

/*
 * Catàleg d'apps del portal d'accés únic (CRT Accés).
 *
 * La clau és l'identificador que viatja a grants i tokens; NO canviar-la un cop
 * hi ha grants creats. `url_entrada` és el punt d'aterratge SSO de cada app:
 * rebrà ?token=<bitllet d'un sol ús> i validarà contra /api/v1/domi/sso/valida.
 */
return [

    'apps' => [
        'domi' => [
            'nom'        => 'CRT Domiciliària',
            'descripcio' => 'Pacients, agenda i sessions a domicili',
            'url_entrada' => env('PORTAL_DOMI_URL', 'https://domi.crtbcn.cat') . '/sso-entrada.php',
        ],
    ],

    /* Vida del bitllet en segons: el just per a una redirecció de navegador. */
    'token_ttl' => (int) env('PORTAL_TOKEN_TTL', 60),
];
