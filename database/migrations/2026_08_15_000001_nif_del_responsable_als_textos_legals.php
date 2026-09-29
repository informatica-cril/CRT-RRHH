<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * IDENTITAT DEL RESPONSABLE als textos que la persona treballadora signa o acusa.
 *
 * L'art. 13.1.a RGPD exigeix la IDENTITAT del responsable del tractament. La política inicial deia
 * «CRT, amb NIF B-XXXXXXXX» —un document signat amb un buit— i la versió 2.0 que la substitueix
 * va corregir el buit suprimint la frase sencera: continua sense identificar el responsable pel seu
 * número. Aquí s'hi posa el NIF real i el domicili social.
 *
 * ⚠️ PENDENT (CRT): el NIF i el domicili social de Centre de Rehabilitació Terapèutica encara no
 * s'han facilitat. Abans de publicar, substituir «PENDENT» a RESP_NOU i ART90_NOU per les dades
 * del Registre Mercantil i confirmar la raó social exacta (p. ex. «... SL»).
 *
 * Per què cada text es tracta d'una manera diferent:
 *   · documents (text SIGNABLE) — la firma segella el contingut i entra al llibre d'integritat. Si
 *     la versió 2.0 encara no té cap firma, el text no segella res i es corregeix in situ: obligar
 *     tota la plantilla a signar una versió nova per un NIF que ningú no havia llegit encara seria
 *     soroll. Si ja en té alguna, NO es toca: neix la versió 2.1 i la 2.0 deixa d'exigir firma.
 *   · compliance_documents (text ACUSABLE) — l'app té l'invariant «un document publicat no s'edita;
 *     una versió nova és un document nou» (ComplianceController::update i ::publish). No s'hi fa
 *     excepció: neix la versió 3.1 i la 3.0 queda arxivada.
 *
 * Idempotent: el text es pedaça per substitució del paràgraf concret (no es reescriu sencer, per no
 * trepitjar cap altra correcció posterior) i tot està guardat per l'existència prèvia del NIF.
 */
return new class extends Migration
{
    private const NIF = 'PENDENT';

    private const DOC_V2 = 'Política de Protecció de Dades (RGPD) — v2.0';

    private const DOC_V21 = 'Política de Protecció de Dades (RGPD) — v2.1';

    private const RESP_ANTIC = "1. RESPONSABLE DEL TRACTAMENT\nCRT és el responsable del tractament de les dades personals de les persones treballadores.";

    private const RESP_NOU = "1. RESPONSABLE DEL TRACTAMENT\nCentre de Rehabilitació Terapèutica, amb NIF PENDENT i domicili social PENDENT, és el responsable del tractament de les dades personals de les persones treballadores.";

    private const ART90_ANTIC = '1. **Responsable del tractament.** CRT. Delegat de Protecció de Dades: protecciodedades@crtbcn.cat';

    private const ART90_NOU = '1. **Responsable del tractament.** Centre de Rehabilitació Terapèutica, amb NIF PENDENT i domicili social PENDENT. '
        . "Delegat de Protecció de Dades: protecciodedades@crtbcn.cat";

    public function up(): void
    {
        $ara = now();

        // ── 1. Text signable ───────────────────────────────────────────────────────────────
        $doc = DB::table('documents')->where('title', self::DOC_V2)->first();
        if ($doc && ! str_contains((string) $doc->content, self::NIF)) {
            $contingut = str_replace(self::RESP_ANTIC, self::RESP_NOU, (string) $doc->content);
            $firmes = DB::table('document_signatures')->where('document_id', $doc->id)->count();

            if ($firmes === 0) {
                // Cap firma: el text no segella res i la correcció no fa dir res nou a ningú.
                DB::table('documents')->where('id', $doc->id)->update([
                    'content' => $contingut,
                    'updated_at' => $ara,
                ]);
            } elseif (! DB::table('documents')->where('title', self::DOC_V21)->exists()) {
                // Hi ha firmes: el contingut és intocable i cal versió nova que es torni a signar.
                DB::table('documents')->insert([
                    'title' => self::DOC_V21,
                    'description' => 'Substitueix la versió 2.0. Únic canvi: identificació del responsable '
                        . 'del tractament pel seu NIF i domicili social (art. 13.1.a RGPD).',
                    'content' => str_replace('POLÍTICA DE PROTECCIÓ DE DADES — versió 2.0',
                        'POLÍTICA DE PROTECCIÓ DE DADES — versió 2.1', $contingut),
                    'file_name' => null,
                    'file_path' => null,
                    'file_mime' => null,
                    'category' => 'normativa',
                    'requires_signature' => 1,
                    'is_urgent' => 1,
                    'target_users' => json_encode('all'),
                    'created_by' => null,
                    'created_at' => $ara,
                    'updated_at' => $ara,
                ]);
                DB::table('documents')->where('id', $doc->id)->update([
                    'requires_signature' => 0,
                    'description' => 'SUBSTITUÏDA el 15/08/2026 per la versió 2.1. Es conserva perquè hi '
                        . 'apunten les firmes ja donades.',
                    'updated_at' => $ara,
                ]);
            }
        }

        // ── 2. Text acusable (art. 90 LOPDGDD) ─────────────────────────────────────────────
        $art90 = DB::table('compliance_documents')
            ->where('tipus', 'info_art90')->where('versio', '3.0')->first();

        if ($art90 && ! DB::table('compliance_documents')
            ->where('tipus', 'info_art90')->where('versio', '3.1')->exists()) {
            DB::table('compliance_documents')->insert([
                'tipus' => 'info_art90',
                'titol' => $art90->titol,
                'versio' => '3.1',
                'contingut' => str_replace(
                    ['Versió 3.0. Substitueix la versió 2.0', self::ART90_ANTIC],
                    ['Versió 3.1. Substitueix la versió 3.0', self::ART90_NOU],
                    (string) $art90->contingut
                ),
                'base_legal' => $art90->base_legal,
                'estat' => 'publicat',
                'requereix_acus' => 1,
                'published_at' => $ara,
                'published_by' => null,
                'created_at' => $ara,
                'updated_at' => $ara,
            ]);

            DB::table('compliance_documents')->where('id', $art90->id)
                ->update(['estat' => 'arxivat', 'updated_at' => $ara]);
        }
    }

    public function down(): void
    {
        DB::table('documents')->where('title', self::DOC_V21)->delete();
        DB::table('documents')->where('title', self::DOC_V2)->update([
            'requires_signature' => 1,
            'content' => DB::raw("REPLACE(content, "
                . DB::connection()->getPdo()->quote(self::RESP_NOU) . ', '
                . DB::connection()->getPdo()->quote(self::RESP_ANTIC) . ')'),
        ]);
        DB::table('compliance_documents')->where('tipus', 'info_art90')->where('versio', '3.1')->delete();
        DB::table('compliance_documents')->where('tipus', 'info_art90')->where('versio', '3.0')
            ->update(['estat' => 'publicat']);
    }
};
