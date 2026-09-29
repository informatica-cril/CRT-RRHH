<?php

namespace App\Support;

/**
 * Abast del bitllet del compte de servei que fa servir domi.
 *
 * PER QUÈ: el bitllet duia abilities ["*"] i el guarda era un prefix de ruta
 * (/api/v1/domi/*). Amb això, tota ruta NOVA sota aquell prefix neix oberta al
 * compte de servei sense que ningú ho decideixi. Aquí la relació s'inverteix:
 * cada ruta demana un permís concret i el que no surt al mapa no és abastable.
 *
 * Regla per a qui afegeixi rutes: ruta sense entrada al mapa → 403 per al compte
 * de servei. Cal decidir a quin permís pertany i, si és un permís nou, tornar a
 * emetre el bitllet perquè el porti (php artisan domi:service-account --rotate).
 */
class DomiScopes
{
    /** Ruta registrada (uri de Laravel) → permís que ha de portar el bitllet. */
    public const MAPA = [
        // Bitllet d'un sol ús del portal d'accés únic.
        'api/v1/domi/sso/valida'                    => 'domi:sso',

        // Plantilla, perfil i disponibilitat: el que domi llegeix per programar.
        'api/v1/users/bulk-index'                   => 'domi:plantilla',
        'api/v1/domi/perfil/{ident}'                => 'domi:plantilla',
        'api/v1/domi/disponibilitat'                => 'domi:plantilla',
        'api/v1/domi/festius'                       => 'domi:plantilla',
        'api/v1/domi/hores'                         => 'domi:plantilla',
        'api/v1/domi/vacances'                      => 'domi:plantilla',

        // Jornada i presència: el que domi escriu en nom del treballador.
        'api/v1/domi/jornada/start'                 => 'domi:jornada',
        'api/v1/domi/jornada/stop'                  => 'domi:jornada',
        'api/v1/domi/break/start'                   => 'domi:jornada',
        'api/v1/domi/break/complete'                => 'domi:jornada',
        'api/v1/domi/break-status/{ident}'          => 'domi:jornada',
        'api/v1/domi/pausa-dia'                     => 'domi:jornada',
        'api/v1/domi/pla-jornada'                   => 'domi:jornada',
        'api/v1/domi/hito'                          => 'domi:jornada',
        'api/v1/domi/geovalla'                      => 'domi:jornada',
        'api/v1/domi/incidencies-obertes'           => 'domi:jornada',

        // Hores complementàries i extraordinàries.
        'api/v1/domi/extraordinaries'               => 'domi:hores',
        'api/v1/domi/extraordinaries/{ref}'         => 'domi:hores',
        'api/v1/domi/complementaries'               => 'domi:hores',
        'api/v1/domi/complementaries/pacte/{ident}' => 'domi:hores',
        'api/v1/domi/complementaries/resolucio'     => 'domi:hores',

        // Sectorització: les zones es decideixen a domi i aquí s'upserten.
        'api/v1/domi/zones'                         => 'domi:zones',

        // Rendiment assistencial i elevació a l'expedient.
        'api/v1/domi/rendiment'                     => 'domi:rendiment',
        'api/v1/domi/element-disciplinari'          => 'domi:rendiment',

        // Telemetria del servei (paràmetres i senyals que domi publica).
        'api/v1/servei/parametres'                  => 'servei:telemetria',
        'api/v1/servei/senyals'                     => 'servei:telemetria',

        // Identitat del propi bitllet: cal per comprovar que segueix viu.
        'api/v1/users/me'                           => 'domi:plantilla',

        /* api/v1/domi/sancions-rlt NO hi és a posta: domi no la crida enlloc.
           Si algun dia la necessita, se li dona 'domi:rendiment' i es rota el bitllet. */
    ];

    /** Permisos que ha de portar el bitllet de domi. */
    public static function permisos(): array
    {
        return array_values(array_unique(array_values(self::MAPA)));
    }

    /** Permís exigit per una ruta, o null si la ruta no és abastable pel servei. */
    public static function permisDe(?string $uri): ?string
    {
        return $uri === null ? null : (self::MAPA[$uri] ?? null);
    }
}
