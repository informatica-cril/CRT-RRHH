<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DECLARACIÓ del tractament de rendiment assistencial i de l'escaneig del xat.
 *
 * Fins ara cap dels textos que la persona treballadora signa o acusa deia que la Domiciliària
 * envia mensualment tretze indicadors per professional a Recursos Humans, ni que els missatges del
 * xat corporatiu es comparen amb una llista de paraules. Controlar el rendiment és lícit (art. 20.3
 * ET); fer-ho sense informar-ne prèviament, no (art. 13 i 14 RGPD, art. 89 i 90 LOPDGDD).
 *
 * Per què s'INSEREIXEN files noves i no es reescriuen les existents:
 *   · documents/document_signatures — la firma segella el contingut (document_hash i
 *     signing_payload) i entra al llibre d'integritat. Canviar el text d'un document ja signat
 *     invalidaria les firmes i, sobretot, faria dir a vuit persones que van acceptar una cosa que
 *     no van llegir. La versió antiga es conserva intacta i deixa d'exigir firma.
 *   · compliance_documents — l'app ja imposa aquesta regla ("Només es pot editar un esborrany.
 *     Publica una versió nova") i el pendent d'acusar es calcula per FILA, de manera que una fila
 *     nova torna a demanar acusament a tothom.
 */
return new class extends Migration
{
    private const DOC_ANTIC = 'Política de Protecció de Dades (RGPD)';

    private const DOC_NOU = 'Política de Protecció de Dades (RGPD) — v2.0';

    public function up(): void
    {
        $ara = now();

        // ── 1. Text signable: nova versió de la política de protecció de dades ──────────────
        if (! DB::table('documents')->where('title', self::DOC_NOU)->exists()) {
            DB::table('documents')->insert([
                'title' => self::DOC_NOU,
                'description' => 'Substitueix la versió inicial. Incorpora el tractament de rendiment '
                    . 'assistencial i la revisió automàtica del xat corporatiu.',
                'content' => $this->politicaRgpd(),
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
        }

        // La versió antiga NO es toca en el text (les firmes donades hi apunten); només deixa
        // d'exigir firma, perquè ningú no ha de signar de nou una política ja substituïda.
        DB::table('documents')
            ->where('title', self::DOC_ANTIC)
            ->update([
                'requires_signature' => 0,
                'description' => 'SUBSTITUÏDA el 14/08/2026 per la versió 2.0. Es conserva perquè hi '
                    . 'apunten les firmes ja donades.',
                'updated_at' => $ara,
            ]);

        // ── 2. Informació de l'art. 90 LOPDGDD: versió nova que cal tornar a acusar ─────────
        if (! DB::table('compliance_documents')->where('tipus', 'info_art90')->where('versio', '3.0')->exists()) {
            DB::table('compliance_documents')->insert([
                'tipus' => 'info_art90',
                'titol' => 'Informació sobre protecció de dades (control laboral i rendiment assistencial)',
                'versio' => '3.0',
                'contingut' => $this->infoArt90(),
                'base_legal' => 'RGPD art. 13 i 14 · LOPDGDD art. 11, 89 i 90 · ET art. 20.3 i 64.4.d',
                'estat' => 'publicat',
                'requereix_acus' => 1,
                'published_at' => $ara,
                'published_by' => null,
                'created_at' => $ara,
                'updated_at' => $ara,
            ]);
        }

        DB::table('compliance_documents')
            ->where('tipus', 'info_art90')->where('versio', '2.0')
            ->update(['estat' => 'arxivat', 'updated_at' => $ara]);

        // ── 3. Política del xat: el text vigent era un titular sense contingut ──────────────
        // Es completa perquè la pantalla del xat digui el que el sistema fa de debò. Atenció:
        // chat_policy_acceptances no desa quina versió es va acceptar, així que aquest canvi NO
        // torna a demanar acceptació a qui ja la va donar (queda anotat a l'adenda de l'EIPD).
        DB::table('chat_settings')->update([
            'chat_policy_text' => $this->politicaXat(),
            'updated_at' => $ara,
        ]);
    }

    public function down(): void
    {
        DB::table('documents')->where('title', self::DOC_NOU)->delete();
        DB::table('documents')->where('title', self::DOC_ANTIC)->update([
            'requires_signature' => 1,
            'description' => null,
        ]);
        DB::table('compliance_documents')->where('tipus', 'info_art90')->where('versio', '3.0')->delete();
        DB::table('compliance_documents')->where('tipus', 'info_art90')->where('versio', '2.0')
            ->update(['estat' => 'publicat']);
        DB::table('chat_settings')->update(['chat_policy_text' => "POLÍTICA D'ÚS DEL XAT CORPORATIU — CRT..."]);
    }

    private function politicaRgpd(): string
    {
        return <<<'TXT'
POLÍTICA DE PROTECCIÓ DE DADES — versió 2.0

CRT — Centre de Rehabilitació Terapèutica

Aquesta versió substitueix la política signada anteriorment. Incorpora dos tractaments que fins ara
no hi constaven: el seguiment del rendiment assistencial i la revisió automàtica del xat corporatiu.

1. RESPONSABLE DEL TRACTAMENT
CRT és el responsable del tractament de les dades personals de les persones treballadores.
Delegat de Protecció de Dades: protecciodedades@crtbcn.cat

2. FINALITATS DEL TRACTAMENT
- Gestió de la relació laboral.
- Control horari (art. 34.9 de l'Estatut dels Treballadors, redacció del RDL 8/2019).
- Geolocalització en el registre de jornada.
- Prevenció de riscos laborals.
- Verificació del compliment de les obligacions laborals contractades, inclòs el seguiment del
  rendiment assistencial (art. 20.3 de l'Estatut dels Treballadors).
- Detecció de situacions greus comunicades pel xat corporatiu i conservació de la prova.

3. SEGUIMENT DEL RENDIMENT ASSISTENCIAL

3.1 Què es tracta
Un cop tancat cada mes natural, l'aplicació CRT Domiciliària calcula i tramet a CRT RRHH,
per a cada professional d'atenció domiciliària, els indicadors següents referits a aquell mes:
  a) sessions de rehabilitació signades pel pacient;
  b) compliment de la programació (sessions signades sobre sessions programades), en percentatge;
  c) rigor documental (sessions signades sobre visites iniciades), en percentatge;
  d) altes sense informe d'alta vigent;
  e) processos assistencials tancats dins el període;
  f) adherència al protocol (sessions fetes sobre sessions pautades per la patologia), en percentatge;
  g) processos tancats amb menys del 70% de les sessions pautades;
  h) puntualitat (visites iniciades amb 15 minuts o menys de diferència respecte de l'hora
     planificada), en percentatge;
  i) retard mitjà a l'inici de la visita, en minuts;
  j) aportacions rebudes dels pacients atesos al canal del portal del pacient, amb el desglossament
     en agraïments i queixes. Per sota de cinc aportacions al període no es desglossa.
La tramesa inclou el DNI o l'identificador intern de la persona, exclusivament per atribuir cada
fila a qui correspon. Quan un DNI consta a més d'un compte, la fila no s'envia.

3.2 Què NO es tracta
No es mesura el ritme personal, ni el temps davant de la pantalla, ni la conducta, ni el contingut
de les comunicacions amb els pacients. Els registres d'accés a la història clínica tenen una altra
finalitat i no s'incorporen a aquest seguiment.

3.3 Base jurídica
Execució del contracte de treball (art. 6.1.b RGPD) i interès legítim de l'empresa a organitzar i
verificar l'activitat productiva (art. 6.1.f RGPD), en el marc de la facultat de vigilància i control
que reconeix l'art. 20.3 de l'Estatut dels Treballadors.

3.4 Origen de les dades
Fets registrats a l'aplicació CRT Domiciliària: signatures de sessió a la tauleta, agenda
planificada, marcatges d'entrada a la visita, informes d'alta, protocols per patologia i aportacions
del portal del pacient. No hi ha cap altra font.

3.5 Qui hi accedeix
A CRT RRHH, els perfils de Direcció i de Responsable de Recursos Humans. A CRT
Domiciliària, el perfil d'Administració. Totes dues aplicacions deixen registre de qui consulta el
rendiment de qui i quan.

3.6 Destinataris
No es preveuen cessions a tercers, llevat d'obligació legal (Tresoreria General de la Seguretat
Social, Inspecció de Treball i Seguretat Social, òrgans judicials). La representació legal de les
persones treballadores rep informació sobre els paràmetres del sistema en els termes de l'art.
64.4.d de l'Estatut dels Treballadors.

3.7 Conservació
Els indicadors es conserven durant la relació laboral i, un cop finalitzada, mentre puguin derivar-se
responsabilitats; en tot cas, com a màxim quatre anys des del tancament del període, per coherència
amb el termini de conservació del registre de jornada.

3.8 Cap decisió automatitzada
Cap indicador desencadena per si mateix cap conseqüència laboral. Cap sanció ni cap mesura
disciplinària es deriva d'una xifra: la decisió la pren sempre una persona, amb la seva pròpia
motivació i pel procediment contradictori que preveu el conveni.

3.9 Dret a conèixer el càlcul i a rebatre'l
A CRT RRHH, la pantalla «El meu rendiment» mostra a cada persona les mateixes xifres que veu
la Direcció d'ella, amb l'explicació de què mesura cada indicador i d'on surt la dada. Des d'aquella
mateixa pantalla es pot presentar per escrit la disconformitat amb un indicador concret d'un període
concret. L'escrit queda desat amb data, arriba a Direcció i a Recursos Humans i ha de tenir resposta
escrita.

4. REVISIÓ AUTOMÀTICA DEL XAT CORPORATIU
Els missatges enviats pel xat corporatiu de CRT RRHH es comparen automàticament amb una llista
de paraules aprovada per la Direcció, orientada a detectar situacions greus (entre altres,
assetjament, amenaces, violència, discriminació o frau). Quan un missatge conté una d'aquestes
paraules, el sistema genera una alerta que desa la paraula detectada i un extracte de fins a cent
caràcters del missatge. Les alertes són accessibles als perfils de Direcció i de Coordinació. Cap
persona aliena a aquests perfils hi té accés i les alertes no es publiquen ni es comuniquen a
tercers, llevat d'obligació legal. El xat corporatiu és una eina de treball: no s'hi ha de fer un ús
privat. Base jurídica: interès legítim de l'empresa a prevenir i acreditar conductes greus i a
complir el deure de protecció de la salut de la plantilla (art. 6.1.f RGPD i art. 20.3 de l'Estatut
dels Treballadors).

5. DRETS DE LES PERSONES TREBALLADORES
Accés, rectificació, supressió, limitació del tractament, portabilitat i oposició, davant el Delegat
de Protecció de Dades a protecciodedades@crtbcn.cat. Es pot presentar reclamació davant l'Autoritat
Catalana de Protecció de Dades. L'exercici d'aquests drets no substitueix el canal específic de
disconformitat amb el rendiment descrit al punt 3.9, que és complementari.

6. CONSERVACIÓ DE DADES
- Registre horari: 4 anys (art. 34.9 de l'Estatut dels Treballadors).
- Coordenades de geolocalització: purgades de forma automàtica als 30 dies.
- Indicadors de rendiment: segons el punt 3.7.
- Alertes del xat: mentre siguin necessàries per a la finalitat que les va originar.
- Dades laborals: durant la relació laboral i els terminis legals de conservació posteriors.

7. MESURES DE SEGURETAT
Conformement a l'Esquema Nacional de Seguretat (RD 311/2022), categoria ALTA:
- Xifratge de dades sensibles en repòs.
- Control d'accés per rols.
- Registre d'auditoria de les consultes i de les accions.
- Segell diari d'integritat sobre els registres probatoris.
- Còpies de seguretat periòdiques.

Signant aquest document declaro haver llegit i entès aquesta política, inclosos el seguiment del
rendiment assistencial i la revisió automàtica del xat corporatiu.
TXT;
    }

    private function infoArt90(): string
    {
        return <<<'MD'
# CRT — INFORMACIÓ SOBRE PROTECCIÓ DE DADES (CONTROL LABORAL I RENDIMENT ASSISTENCIAL)

Versió 3.0. Substitueix la versió 2.0 i n'amplia el contingut amb el seguiment del rendiment
assistencial i la revisió automàtica del xat corporatiu, que no hi constaven.

1. **Responsable del tractament.** CRT. Delegat de Protecció de Dades: protecciodedades@crtbcn.cat

2. **Finalitat.** Verificar el compliment per part de la persona treballadora de les seves
obligacions i deures laborals: control de puntualitat i presència; verificació de la jornada;
seguiment de la prestació efectiva del servei i del rendiment assistencial; detecció de situacions
greus comunicades pel xat corporatiu; gestió del règim disciplinari.

3. **Base jurídica.** Execució del contracte de treball (art. 6.1.b RGPD) i interès legítim de
l'empresa a organitzar i supervisar l'activitat productiva (art. 6.1.f RGPD), conforme a l'art. 20.3
de l'Estatut dels Treballadors. La informació prèvia és requisit de licitud (art. 13 i 14 RGPD, art.
89 i 90 LOPDGDD).

4. **Categories de dades.**
   - Dades identificatives i registres d'accés.
   - Registre de jornada i marcatges, amb la geolocalització del punt de fitxatge.
   - Indicadors mensuals de rendiment assistencial per professional: sessions signades; compliment de
     la programació; rigor documental; altes sense informe; processos tancats; adherència al protocol;
     processos tancats sota el 70% de les sessions pautades; puntualitat; retard mitjà en minuts;
     aportacions dels pacients atesos amb desglossament en agraïments i queixes.
   - Alertes del xat corporatiu: paraula detectada i extracte de fins a cent caràcters del missatge.

5. **Origen.** Els indicadors de rendiment no s'obtenen de la persona treballadora sinó de fets
registrats a l'aplicació CRT Domiciliària (signatures de sessió, agenda planificada, marcatges
d'entrada a la visita, informes d'alta, protocols per patologia i canal d'aportacions del portal del
pacient). Es calculen un cop tancat cada mes natural i es trameten a CRT RRHH ja tancats per
període, identificats pel DNI o per l'identificador intern.

6. **Periodicitat i conservació.** Tramesa mensual. Els indicadors es conserven durant la relació
laboral i, després, mentre puguin derivar-se responsabilitats; com a màxim, quatre anys des del
tancament del període.

7. **Destinataris.** A CRT RRHH hi accedeixen els perfils de Direcció i de Responsable de
Recursos Humans; a CRT Domiciliària, el perfil d'Administració. Les alertes del xat són
accessibles als perfils de Direcció i de Coordinació. No es preveuen cessions a tercers, llevat
d'obligació legal (Seguretat Social, Inspecció de Treball, òrgans judicials). La representació legal
de les persones treballadores rep informació sobre els paràmetres del sistema conforme a l'art.
64.4.d de l'Estatut dels Treballadors.

8. **Decisions automatitzades.** No se n'adopten. Cap indicador desencadena per si mateix una
conseqüència laboral. Tota mesura disciplinària exigeix la valoració i la signatura d'una persona i
el procediment contradictori previst al conveni.

9. **Drets.** Accés, rectificació, supressió, oposició, limitació i portabilitat davant el Delegat de
Protecció de Dades. Es pot reclamar davant l'Autoritat Catalana de Protecció de Dades.

10. **Dret específic a conèixer el càlcul i a rebatre'l.** A CRT RRHH, la pantalla «El meu
rendiment» mostra a cada persona les seves pròpies xifres, què mesura cada indicador i d'on surt la
dada. Des d'aquella pantalla es pot presentar per escrit la disconformitat amb un indicador concret
d'un període concret; l'escrit queda desat amb data, arriba a Direcció i a Recursos Humans i ha de
tenir resposta escrita.

11. **Traçabilitat.** Les dues aplicacions registren qui consulta el rendiment de qui i quan. Aquest
registre és consultable pel Delegat de Protecció de Dades.
MD;
    }

    private function politicaXat(): string
    {
        return <<<'TXT'
POLÍTICA D'ÚS DEL XAT CORPORATIU — CRT

1. FINALITAT DE L'EINA
El xat de CRT RRHH és una eina de treball per a la comunicació interna entre professionals i
amb Coordinació, Recursos Humans i Direcció. No està previst per a un ús privat.

2. REVISIÓ AUTOMÀTICA DELS MISSATGES
Cada missatge enviat es compara automàticament amb una llista de paraules aprovada per la Direcció,
orientada a detectar situacions greus (entre altres, assetjament, amenaces, violència, discriminació
o frau). Quan un missatge conté una d'aquestes paraules, el sistema genera una alerta que desa la
paraula detectada i un extracte de fins a cent caràcters del missatge.

3. QUI HI ACCEDEIX
Les alertes són accessibles únicament als perfils de Direcció i de Coordinació, que en deixen
constància quan les revisen. La cerca global de missatges està limitada als mateixos perfils. No es
comuniquen a tercers, llevat d'obligació legal.

4. BASE JURÍDICA
Interès legítim de l'empresa a prevenir i acreditar conductes greus i a complir el deure de protecció
de la salut de la plantilla (art. 6.1.f RGPD), en el marc de la facultat de vigilància i control de
l'art. 20.3 de l'Estatut dels Treballadors i amb la informació prèvia que exigeixen els art. 13 RGPD
i 87 i 89 LOPDGDD.

5. CAP DECISIÓ AUTOMÀTICA
Una alerta no és una acusació ni obre cap expedient: és un avís que una persona ha de revisar.

6. DRETS
Accés, rectificació, supressió, limitació i oposició davant el Delegat de Protecció de Dades
(protecciodedades@crtbcn.cat), i reclamació davant l'Autoritat Catalana de Protecció de Dades.
TXT;
    }
};
