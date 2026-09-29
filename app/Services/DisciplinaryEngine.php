<?php

namespace App\Services;

use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryElement;
use App\Models\DisciplinaryFaultType;
use Carbon\Carbon;

/**
 * Motor del procediment disciplinari (port del motor de domi a Laravel).
 *
 * Principis inviolables (RGPD 22, conveni, ET): el motor ACREDITA i PROPOSA; la qualificació, la
 * voluntarietat i la SANCIÓ les decideix i signa una PERSONA. Cap transició a 'resolt' sense actor
 * humà. Només dades IMPUTABLES. Autònom fora de la via disciplinària laboral. Prescripció ET 60.2.
 */
class DisciplinaryEngine
{
    /**
     * ÚNICA FONT DE VERITAT dels terminis de prescripció: són de l'ET art. 60.2, no parametritzables.
     * El catàleg (disciplinary_fault_types.prescripcio_dies) n'és un MIRALL derivat del grau —el
     * model el recalcula en desar—, perquè cap formulari pugui escurçar o allargar un termini legal.
     */
    private const DIES = ['lleu' => 10, 'menys_greu' => 10, 'greu' => 20, 'molt_greu' => 60];

    /**
     * Identificació de l'entitat que sanciona, (CRT: raó social, NIF i domicili PENDENTS). Va aquí i no
     * a la configuració perquè no és un paràmetre d'explotació: si algú el pogués canviar des d'una
     * pantalla, les cartes ja signades i les futures dirien coses diferents sense deixar rastre.
     */
    private const NIF_EMPRESA = 'PENDENT';

    private const DOMICILI_EMPRESA = 'PENDENT'; // CRT: domicili social pendent de facilitar

    /** Termini legal en dies per a un grau (ET 60.2). null si el grau no és del catàleg legal. */
    public function diesPrescripcio(?string $grau): ?int
    {
        return self::DIES[$grau] ?? null;
    }

    /** Data en què PRESCRIU (ET 60.2 des del coneixement; límit dur 6 mesos des de la comissió). */
    public function prescripcio(string $grau, ?string $dataConeixement, ?string $dataFet = null): ?string
    {
        if (! $dataConeixement || ! isset(self::DIES[$grau])) {
            return null;
        }
        $perConeixement = Carbon::parse($dataConeixement)->addDays(self::DIES[$grau]);
        if ($dataFet) {
            $per6m = Carbon::parse($dataFet)->addMonths(6);
            return $perConeixement->min($per6m)->toDateString();
        }
        return $perConeixement->toDateString();
    }

    /** Dies fins a la prescripció (negatiu = ja prescrita). */
    public function diesRestants(?string $dataPrescripcio): ?int
    {
        if (! $dataPrescripcio) {
            return null;
        }
        return (int) floor(Carbon::today()->diffInDays(Carbon::parse($dataPrescripcio), false));
    }

    /**
     * Un FET (element) està prescrit? Es calcula amb el grau del catàleg i les DATES DEL FET, no amb
     * la data de prescripció del cas: un cas viu pot arrossegar fets ja morts, i aquests no poden
     * fonamentar cap càrrec (ET 60.2).
     */
    public function elementPrescrit(DisciplinaryElement $el): bool
    {
        $grau = DisciplinaryFaultType::where('clau', $el->tipus_falta)->value('grau_base');
        if (! $grau || ! $el->data_coneixement) {
            return false;   // sense grau o sense coneixement no es pot afirmar que hagi prescrit
        }
        $presc = $this->prescripcio($grau, $el->data_coneixement->toDateString(), $el->data_fet?->toDateString());
        $dies  = $this->diesRestants($presc);

        return $dies !== null && $dies < 0;
    }

    /** Reincidència: només elements IMPUTABLES ('si') del mateix tipus compten. */
    public function reincidencia(string $professional, ?string $tipusFalta): array
    {
        $llindar = (int) DisciplinaryFaultType::where('clau', $tipusFalta)->value('llindar_reincidencia');
        if ($llindar <= 0) {
            return ['acreditada' => false, 'n' => 0, 'llindar' => 0];
        }
        // La prova bloquejada tampoc AGREUJA: si no pot fonamentar un fet, encara menys pot fer que
        // el conjunt es qualifiqui de reincident i pugi de grau la sanció.
        $n = DisciplinaryElement::where('professional', $professional)
            ->where('tipus_falta', $tipusFalta)
            ->where('estat', '!=', 'descartat')
            ->apte()
            ->where('imputable', 'si')->count();

        return ['acreditada' => $n >= $llindar, 'n' => $n, 'llindar' => $llindar];
    }

    /**
     * Guarda de la màquina d'estats (les GARANTIES com a porta). Retorna [ok(bool), motiu(string)].
     * Aquí és on cap sanció s'automatitza.
     */
    public function potTransicio(DisciplinaryCase $cas, string $estatDesti): array
    {
        // El veto no depèn només del camp del formulari: si la FITXA del titular a RRHH diu autònom,
        // la via disciplinària laboral està vetada encara que el cas s'hagi obert com a 'laboral'.
        if ($cas->vincle === 'autonom' || $cas->user?->esAutonom()) {
            return [false, 'Vincle mercantil: via disciplinària laboral vetada'];
        }
        // Un cas TANCAT no retrocedeix: l'historial és append-only i la sanció ja s'ha notificat.
        // Entre estats tancats només queden els moviments cap endavant ('resolt'→'executat',
        // arxiu), que les seves pròpies portes ja governen. Reobrir exigeix un cas nou.
        $tancats = ['resolt', 'executat', 'arxivat'];
        if (in_array($cas->estat, $tancats, true) && ! in_array($estatDesti, $tancats, true)) {
            return [false, "Cas TANCAT en «{$cas->estat}»: no pot retrocedir a «{$estatDesti}» (historial immutable)"];
        }

        switch ($estatDesti) {
            case 'instruccio':
                return [true, 'OK'];
            case 'comunicat':
                if (! $cas->te_evidencia_licita) {
                    return [false, 'Falta evidència amb licitud verificada'];
                }
                $rest = $this->diesRestants($cas->data_prescripcio?->toDateString());
                if ($rest !== null && $rest < 0) {
                    return [false, 'Falta PRESCRITA (ET 60.2): no es pot comunicar'];
                }
                // La comunicació es fa DINS de l'app: cal destinatari (usuari) i un escrit SIGNAT per notificar.
                if (! $cas->user_id) {
                    return [false, 'Cal vincular el treballador (usuari de l\'app) per notificar-li l\'escrit'];
                }
                if (! $cas->documents()->where('estat', 'signat')->exists()) {
                    return [false, 'Cal un escrit SIGNAT per poder comunicar (la notificació lliura l\'escrit al portal del treballador)'];
                }
                return [true, 'OK'];
            case 'alegacions':
                if (! $cas->comunicat_ts) {
                    return [false, 'No es poden obrir al·legacions sense comunicació prèvia'];
                }
                return [true, 'OK'];
            case 'resolt':
                // TRÀMIT D'AUDIÈNCIA (ET 55.1, conveni 55.K): no es resol el que no s'ha comunicat.
                // Només s'hi arriba des de 'comunicat' o 'alegacions', estats que ja exigeixen escrit
                // signat, destinatari i comunicació prèvia: sancionar sense audiència és indefensió.
                if (! in_array($cas->estat, ['comunicat', 'alegacions'], true)) {
                    return [false, 'Cap resolució sense COMUNICACIÓ prèvia dels fets ni tràmit d\'audiència (ET 55.1): l\'expedient ha de passar per «comunicat»'];
                }
                // Comunicar no és escoltar: el termini que el propi plec concedeix ha d'HAVER
                // SERVIT. Es resol si (a) el treballador ha al·legat, (b) hi renuncia ell mateix
                // amb acte propi registrat, o (c) el termini ha vençut sense resposta. Resoldre
                // amb el termini corrent és indefensió i fa nul·la la sanció.
                [$audienciaOk, $motiuAudiencia] = $this->audienciaExhaurida($cas);
                if (! $audienciaOk) {
                    return [false, $motiuAudiencia];
                }
                if (! $cas->resolt_per) {
                    return [false, 'Cap resolució sense PERSONA que la signi (RGPD 22)'];
                }
                if ($cas->gravetat === 'molt_greu' && ! $cas->audiencia_rlt_ts) {
                    return [false, 'Molt greu sense audiència a la RLT (conveni 55.I/56.3)'];
                }
                if ($cas->es_representant && ! $cas->expedient_contradictori) {
                    return [false, 'Representant sense expedient contradictori'];
                }
                return [true, 'OK'];
            case 'executat':
                if ($cas->estat !== 'resolt' || ! $cas->resolucio_tipus) {
                    return [false, 'Només s\'executa una resolució signada'];
                }
                return [true, 'OK'];
            case 'arxivat':
                return [true, 'OK'];
            default:
                return [false, 'Transició desconeguda'];
        }
    }

    /**
     * TRÀMIT D'AUDIÈNCIA: [ok(bool), motiu(string)]. Mateix patró que l'audiència prèvia del rebuig
     * d'un tram de fitxatge (WorkLogSegmentController): o s'ha escoltat el treballador, o el termini
     * que se li va concedir ha vençut. La renúncia només val si la va fer ELL (renuncia_termini_per).
     */
    public function audienciaExhaurida(DisciplinaryCase $cas): array
    {
        if ($cas->alegacions_ts) {
            return [true, 'OK'];
        }
        if ($cas->renuncia_termini_ts && $cas->renuncia_termini_per) {
            return [true, 'OK'];
        }
        if (! $cas->termini_alegacions) {
            return [false, 'Sense termini d\'al·legacions fixat: no consta que s\'hagi obert el tràmit d\'audiència (ET 55.1)'];
        }
        if (Carbon::today()->lte($cas->termini_alegacions)) {
            return [false, 'En TERMINI d\'al·legacions fins al ' . $cas->termini_alegacions->format('d/m/Y')
                . ': cal esperar les al·legacions del treballador, la seva renúncia expressa o el venciment del termini (ET 55.1, conveni 55.K.6)'];
        }

        return [true, 'OK'];
    }

    /** Checklist de garanties per a la vista (estat de cada porta). */
    public function garanties(DisciplinaryCase $cas): array
    {
        $rest = $this->diesRestants($cas->data_prescripcio?->toDateString());
        [$audOk, $audMotiu] = $this->audienciaExhaurida($cas);
        return [
            ['clau' => 'audiencia_alegacions', 'ok' => $audOk, 'detall' => $audOk
                ? ($cas->alegacions_ts ? 'Al·legacions presentades pel treballador el ' . $cas->alegacions_ts->format('d/m/Y')
                    : ($cas->renuncia_termini_ts ? 'Renúncia expressa del treballador al termini' : 'Termini d\'al·legacions vençut sense resposta'))
                : $audMotiu],
            ['clau' => 'no_automatica', 'ok' => true, 'detall' => 'Cap sanció automàtica: la signa una persona (RGPD 22)'],
            ['clau' => 'imputabilitat', 'ok' => true, 'detall' => 'Només elements imputables compten'],
            ['clau' => 'prescripcio', 'ok' => $rest === null || $rest >= 0, 'detall' => $rest === null ? 'Sense data de prescripció' : ($rest >= 0 ? "Prescriu en {$rest} dies" : 'PRESCRITA')],
            ['clau' => 'evidencia_licita', 'ok' => (bool) $cas->te_evidencia_licita, 'detall' => 'Evidència amb licitud verificada'],
            ['clau' => 'vincle', 'ok' => $cas->vincle !== 'autonom' && ! $cas->user?->esAutonom(), 'detall' => ($cas->vincle === 'autonom' || $cas->user?->esAutonom()) ? 'Autònom: via laboral vetada' : 'Vincle laboral'],
            ['clau' => 'audiencia_rlt', 'ok' => $cas->gravetat !== 'molt_greu' || (bool) $cas->audiencia_rlt_ts, 'detall' => 'Audiència a la RLT si molt greu'],
            ['clau' => 'contradictori', 'ok' => ! $cas->es_representant || (bool) $cas->expedient_contradictori, 'detall' => 'Expedient contradictori si és representant'],
        ];
    }

    /* ── Suggeriments d'obertura (revisió humana) ─────────────────────────────
       La recollida SUGGEREIX; la persona OBRE. Quan els elements imputables lliures (no inclosos
       en cap cas) assoleixen el llindar de reincidència del catàleg, o quan a un element li queda
       poc per prescriure, es proposa l'obertura amb els elements que la fonamenten. */
    public function suggeriments(): array
    {
        $out = [];
        $llindars = DisciplinaryFaultType::where('llindar_reincidencia', '>', 0)->get()->keyBy('clau');
        $graus = DisciplinaryFaultType::pluck('grau_base', 'clau');

        // El motor no proposa obrir un expedient sobre una prova que ell mateix no deixaria escriure
        // al plec: seria convidar a una decisió que després no es pot fonamentar.
        $lliures = DisciplinaryElement::whereIn('estat', ['nou', 'valorat'])
            ->apte()
            ->where('imputable', 'si')->orderBy('data_fet')->get();

        // (a) Llindar de reincidència assolit per professional + tipus de falta.
        foreach ($lliures->groupBy(fn ($e) => $e->professional . '|' . $e->tipus_falta) as $grup) {
            $primer = $grup->first();
            $tf = $llindars->get($primer->tipus_falta);
            if (! $tf || $grup->count() < $tf->llindar_reincidencia) {
                continue;
            }
            $out[] = [
                'tipus'        => 'reincidencia',
                'professional' => $primer->professional,
                'user_id'      => $grup->pluck('user_id')->filter()->first(),
                'tipus_falta'  => $primer->tipus_falta,
                'n'            => $grup->count(),
                'llindar'      => $tf->llindar_reincidencia,
                'element_ids'  => $grup->pluck('id')->values()->all(),
                'motiu'        => "{$grup->count()} fets imputables de «{$tf->descripcio}» (llindar {$tf->llindar_reincidencia}): dades suficients per valorar l'obertura",
                'base_legal'   => trim(($tf->base_conveni ?? '') . ' ' . ($tf->base_et ?? '')),
            ];
        }

        // (b) Elements a punt de prescriure (≤7 dies) sense cas: actuar o deixar prescriure (decisió humana).
        foreach ($lliures as $el) {
            $grau = $graus[$el->tipus_falta] ?? null;
            $presc = $grau ? $this->prescripcio($grau, $el->data_coneixement->toDateString(), $el->data_fet->toDateString()) : null;
            $dies = $this->diesRestants($presc);
            if ($dies === null || $dies < 0 || $dies > 7) {
                continue;
            }
            $out[] = [
                'tipus'        => 'prescripcio',
                'professional' => $el->professional,
                'user_id'      => $el->user_id,
                'tipus_falta'  => $el->tipus_falta,
                'element_ids'  => [$el->id],
                'dies'         => $dies,
                'motiu'        => "L'element #{$el->id} ({$el->tipus_falta}, {$el->data_fet->toDateString()}) prescriu en {$dies} dies (ET 60.2): actuar o descartar",
                'base_legal'   => 'ET art. 60.2',
            ];
        }

        return $out;
    }

    /* ── Generació d'escrits ──────────────────────────────────────────────────
       Esborranys FORMALS per tipus, alimentats amb les dades reals del cas (fets amb font i data,
       tipificació amb article del conveni/ET, terminis, reincidència). Mai s'envien sols: els revisa,
       completa i SIGNA una persona. Els [claudàtors] marquen el que ha d'emplenar el signant. */

    /** Genera l'ESBORRANY d'un escrit (mai s'envia sol; el signa una persona). */
    public function generaEscrit(DisciplinaryCase $cas, string $tipus): string
    {
        $tipus = in_array($tipus, ['apercebiment', 'amonestacio', 'plec_carrecs', 'resolucio', 'carta_acomiadament'], true) ? $tipus : 'amonestacio';

        $ft = DisciplinaryFaultType::where('clau', $cas->tipus_falta)->first();
        $ctx = [
            'avui'        => Carbon::today()->locale('ca')->isoFormat('D [de] MMMM [de] YYYY'),
            'falta'       => $ft?->descripcio ?? ($cas->tipus_falta ?? '[tipus de falta]'),
            'tipificacio' => $this->tipificacio($ft, $cas),
            'gravetat'    => $this->grauLegible($cas->gravetat),
            'fets'        => $this->blocFets($cas),
            'reincidencia'=> $this->blocReincidencia($cas),
            'termini'     => $cas->termini_alegacions?->locale('ca')->isoFormat('D [de] MMMM [de] YYYY') ?? '[data: mínim 5 dies hàbils]',
        ];

        $cos = match ($tipus) {
            'apercebiment'       => $this->escritApercebiment($cas, $ctx),
            'amonestacio'        => $this->escritAmonestacio($cas, $ctx),
            'plec_carrecs'       => $this->escritPlecCarrecs($cas, $ctx),
            'resolucio'          => $this->escritResolucio($cas, $ctx),
            'carta_acomiadament' => $this->escritAcomiadament($cas, $ctx),
        };

        return $this->capçalera($cas, $tipus, $ctx) . $cos . $this->peu($tipus);
    }

    private function grauLegible(?string $g): string
    {
        return ['lleu' => 'LLEU', 'menys_greu' => 'MENYS GREU', 'greu' => 'GREU', 'molt_greu' => 'MOLT GREU'][$g] ?? '[gravetat]';
    }

    private function tipificacio(?DisciplinaryFaultType $ft, DisciplinaryCase $cas): string
    {
        if (! $ft) {
            return '[article del XII Conveni col·lectiu de la sanitat concertada de Catalunya]';
        }
        $parts = [];
        if ($ft->base_conveni) {
            $parts[] = "l'{$ft->base_conveni} del XII Conveni col·lectiu de la sanitat concertada de Catalunya";
        }
        if ($ft->base_et) {
            $parts[] = "l'{$ft->base_et} de l'Estatut dels Treballadors";
        }

        return $parts ? implode(' i ', $parts) : '[tipificació]';
    }

    /**
     * Fets del cas: cada element imputable amb data, descripció i font (la força probatòria de
     * l'escrit). Dos filtres que no es poden saltar:
     *   · el fet ha de ser DEL TITULAR de l'expedient (una prova d'un altre treballador no imputa);
     *   · el fet no pot estar PRESCRIT (ET 60.2): un càrrec sobre un fet mort és impugnable sencer.
     * I l'origen s'escriu tal com consta: el que va teclejar RRHH es diu, no es disfressa de registre
     * automàtic de l'app domiciliària.
     */
    private function blocFets(DisciplinaryCase $cas): string
    {
        $imputables = $cas->elements()->where('imputable', 'si')->orderBy('data_fet')->get()
            ->filter(fn ($el) => $this->elementDelTitular($cas, $el) && ! $this->elementPrescrit($el));

        // Les proves bloquejades no es descriuen com a fets, però la seva absència SÍ que es diu.
        // Excloure-les en silenci deixaria qui redacta convençut que el cas s'ha desinflat sol, quan
        // el que ha passat és que l'origen d'aquells fets no consta i cal decidir-ho expressament.
        $bloquejats = $imputables->filter(fn ($el) => ! $el->apte_plec);
        $elements = $imputables->filter(fn ($el) => (bool) $el->apte_plec);
        $avis = $bloquejats->isEmpty() ? '' : "\n\n[ATENCIÓ: {$bloquejats->count()} fet(s) d'aquest expedient "
            . "(#" . $bloquejats->pluck('id')->implode(', #') . ') NO s\'han inclòs perquè el seu origen no '
            . 'està acreditat. No es poden imputar mentre no es rehabilitin expressament des de la pantalla '
            . 'd\'Evidències, amb la motivació de qui ho decideix.]';

        if ($elements->isEmpty()) {
            return "1. [Descriviu els fets amb dia, hora, lloc i font de la prova. Sense fets concrets amb data,\n   l'escrit és impugnable per indefensió: el detall NO és opcional.]" . $avis;
        }
        $out = [];
        foreach ($elements->values() as $i => $el) {
            $linia = ($i + 1) . '. En data ' . $el->data_fet->locale('ca')->isoFormat('D [de] MMMM [de] YYYY') . ', ' . rtrim((string) ($el->descripcio ?: '[fet]'), '.') . '.';
            $detall = array_filter([
                $el->valor ? "magnitud: {$el->valor}" : null,
                $el->font ? 'font de la prova: ' . $el->font . ' (' . $this->origenLegible($el->origen) . ')' : null,
            ]);
            if ($detall) {
                $linia .= ' (' . implode(' · ', $detall) . ')';
            }
            $out[] = $linia;
        }

        return implode("\n", $out) . $avis;
    }

    /** El fet imputa el titular de l'expedient? Es creua per usuari i, si no n'hi ha, per identificador. */
    public function elementDelTitular(DisciplinaryCase $cas, DisciplinaryElement $el): bool
    {
        if ($cas->user_id && $el->user_id) {
            return (int) $cas->user_id === (int) $el->user_id;
        }

        return $el->professional === $cas->professional;
    }

    /**
     * La sanció, escrita sencera: tipus, dies i article que l'empara. La fan servir tant l'escrit de
     * resolució com el llibre de custòdia — el llibre ha de dir QUÈ es va imposar, no només que es va
     * resoldre, perquè aquesta és l'única constància interna del contingut de la sanció.
     */
    public function sancioLegible(DisciplinaryCase $cas): string
    {
        if (! $cas->resolucio_tipus) {
            return 'sense resolució';
        }
        $txt = \App\Support\ConveniSancions::etiqueta($cas->resolucio_tipus);
        if ($cas->resolucio_dies) {
            $txt .= ' de ' . $cas->resolucio_dies . ' dies';
        }
        if ($cas->resolucio_tipus === 'arxiu') {
            return $txt . ' (sense sanció)';
        }

        return $txt . ' — falta ' . $this->grauLegible($cas->gravetat)
            . ', art. 68 del XII Conveni col·lectiu de la sanitat concertada de Catalunya';
    }

    /** Com es diu l'origen d'una prova dins d'un escrit formal. */
    private function origenLegible(?string $origen): string
    {
        return match ($origen) {
            'pont'   => 'registre automàtic de l\'aplicació assistencial',
            'manual' => 'incorporada manualment per Recursos Humans',
            default  => 'origen no acreditat',
        };
    }

    private function blocReincidencia(DisciplinaryCase $cas): string
    {
        $r = $this->reincidencia($cas->professional, $cas->tipus_falta);
        if (! ($r['llindar'] ?? 0)) {
            return '';
        }
        if ($r['acreditada']) {
            return "\nConsta a l'expedient la REINCIDÈNCIA en conductes del mateix tipus ({$r['n']} fets imputables acreditats, "
                 . "llindar de {$r['llindar']}), circumstància que es té en compte en la qualificació conforme al conveni.\n";
        }

        return '';
    }

    private function capçalera(DisciplinaryCase $cas, string $tipus, array $ctx): string
    {
        $titols = [
            'apercebiment'       => 'APERCEBIMENT PER ESCRIT',
            'amonestacio'        => 'AMONESTACIÓ PER ESCRIT',
            'plec_carrecs'       => 'PLEC DE CÀRRECS',
            'resolucio'          => 'RESOLUCIÓ DE L\'EXPEDIENT DISCIPLINARI',
            'carta_acomiadament' => 'CARTA D\'ACOMIADAMENT DISCIPLINARI',
        ];

        // Capçalera de l'entitat: identificació REAL, no claudàtors. Una carta disciplinària que
        // arriba amb «[NIF]» al peu del nom de l'empresa no identifica qui sanciona, i qui la signa
        // acaba omplint a mà un dada que ja consta a l'EIPD. La localitat sí que queda oberta: és
        // el lloc d'emissió de l'escrit, que canvia de carta a carta.
        return '**CRT** · NIF ' . self::NIF_EMPRESA . ' · ' . self::DOMICILI_EMPRESA . "\n\n"
            . "[Localitat], {$ctx['avui']}\n\n"
            . "**A l'atenció de:** {$this->destinatari($cas)}\n"
            . "**Lliurament:** en mà amb justificant de recepció / burofax amb certificació de text i acusament\n\n"
            . "## {$titols[$tipus]}\n"
            . "**Ref.:** expedient {$cas->id}/" . Carbon::today()->year . "\n\n";
    }

    /**
     * Destinatari de la carta: surt de la FITXA del treballador a RRHH, no d'un camp lliure. Una carta
     * disciplinària dirigida a un nom escrit a mà pot anar a una persona que no existeix —o, pitjor,
     * a una altra—. Sense titular vinculat no s'inventa cap nom: es deixa el claudàtor a la vista.
     */
    private function destinatari(DisciplinaryCase $cas): string
    {
        $u = $cas->user;
        if (! $u) {
            return "[vincular el treballador a la seva fitxa de RRHH: sense fitxa no hi ha destinatari acreditat] · (identificador operatiu: {$cas->professional})";
        }

        return $u->name . ' · DNI ' . ($u->dni ?: '[DNI no consta a la fitxa]');
    }

    private function escritApercebiment(DisciplinaryCase $cas, array $ctx): string
    {
        return "Senyor/a,\n\n"
            . "Per mitjà del present escrit, la Direcció de CRT us **aperceb formalment**, amb caràcter previ i "
            . "sense naturalesa de sanció, en relació amb els fets següents:\n\n{$ctx['fets']}\n\n"
            . "Aquests fets, de mantenir-se, podrien ser constitutius de la falta de «{$ctx['falta']}», tipificada a {$ctx['tipificacio']}.\n"
            . $ctx['reincidencia']
            . "\nUs requerim que **corregiu immediatament** aquesta situació. Aquest apercebiment quedarà incorporat al "
            . "vostre expedient i, en cas de persistència, l'empresa es reserva l'exercici de les accions disciplinàries "
            . "que corresponguin, inclosa la via de l'art. 54.2.e de l'Estatut dels Treballadors (disminució continuada "
            . "i voluntària del rendiment), per a la qual aquest apercebiment previ constitueix requisit.\n\n"
            . "Restem a la vostra disposició per escoltar qualsevol circumstància que considereu rellevant.\n";
    }

    private function escritAmonestacio(DisciplinaryCase $cas, array $ctx): string
    {
        return "Senyor/a,\n\n"
            . "La Direcció de CRT, en exercici de les facultats disciplinàries que li reconeixen l'art. 58 de "
            . "l'Estatut dels Treballadors i el capítol 9 del XII Conveni col·lectiu de la sanitat concertada de "
            . "Catalunya, us comunica la imposició d'una **AMONESTACIÓ PER ESCRIT** pels fets següents:\n\n"
            . "**FETS**\n\n{$ctx['fets']}\n\n"
            . "**QUALIFICACIÓ**\n\nEls fets descrits constitueixen una falta **{$ctx['gravetat']}** de «{$ctx['falta']}», "
            . "tipificada a {$ctx['tipificacio']}.\n"
            . $ctx['reincidencia']
            . "\n**SANCIÓ**\n\nAmonestació per escrit, amb constància a l'expedient personal.\n\n"
            . "Us recordem que la reiteració d'aquests fets podrà ser qualificada amb graus superiors de gravetat. "
            . "Contra aquesta sanció podeu presentar les al·legacions que estimeu oportunes davant el Departament de "
            . "Recursos Humans i, si escau, exercitar les accions que us reconeix la legislació vigent davant la "
            . "jurisdicció social (termini de 20 dies hàbils).\n";
    }

    private function escritPlecCarrecs(DisciplinaryCase $cas, array $ctx): string
    {
        return "Senyor/a,\n\n"
            . "S'ha acordat la incoació d'expedient disciplinari i, en garantia del vostre dret de defensa, us "
            . "traslladem el present **PLEC DE CÀRRECS**:\n\n"
            . "**PRIMER — FETS IMPUTATS**\n\n{$ctx['fets']}\n\n"
            . "**SEGON — QUALIFICACIÓ PROVISIONAL**\n\nEls fets podrien ser constitutius d'una falta **{$ctx['gravetat']}** "
            . "de «{$ctx['falta']}», tipificada a {$ctx['tipificacio']}.\n"
            . $ctx['reincidencia']
            . "\n**TERCER — TRÀMIT D'AUDIÈNCIA**\n\nDisposeu fins al **{$ctx['termini']}** per presentar per escrit les "
            . "al·legacions i proposar els mitjans de prova que considereu convenients, davant el Departament de "
            . "Recursos Humans. Transcorregut el termini, l'expedient seguirà el seu curs amb la resolució que escaigui.\n\n"
            . "[Si la persona és representant dels treballadors: es tramita expedient contradictori amb audiència a "
            . "la resta de la representació. Si està afiliada a un sindicat i consta a l'empresa: audiència prèvia "
            . "als delegats sindicals (art. 56.3 del conveni).]\n";
    }

    private function escritResolucio(DisciplinaryCase $cas, array $ctx): string
    {
        $sancio = $cas->resolucio_tipus ? mb_strtoupper($this->sancioLegible($cas)) : '[SANCIÓ: amonestació / suspensió de sou i feina de … dies / trasllat / inhabilitació / acomiadament / arxiu]';
        $motivacio = trim((string) $cas->resolucio_motivacio) ?: '[Motivació de la resolució: valoració de les al·legacions presentades, proves practicades i proporcionalitat de la sanció. La redacta la persona que signa.]';

        // El tràmit d'audiència s'explica pel que consta a l'expedient, no per un claudàtor: aquesta
        // frase és la que acredita davant d'un jutge que es va escoltar el treballador.
        $audiencia = match (true) {
            (bool) $cas->alegacions_ts => 'les al·legacions presentades per la persona interessada en data '
                . $cas->alegacions_ts->locale('ca')->isoFormat('D [de] MMMM [de] YYYY'),
            (bool) $cas->renuncia_termini_ts => 'la renúncia expressa de la persona interessada al termini d\'al·legacions, formulada per ella mateixa en data '
                . $cas->renuncia_termini_ts->locale('ca')->isoFormat('D [de] MMMM [de] YYYY'),
            (bool) $cas->termini_alegacions => 'el transcurs del termini d\'al·legacions, que va vèncer el '
                . $cas->termini_alegacions->locale('ca')->isoFormat('D [de] MMMM [de] YYYY') . ' sense que se n\'hagin presentat',
            default => '[tràmit d\'audiència: fer-hi constar què va passar amb el termini d\'al·legacions]',
        };

        return "Vistos l'expedient disciplinari de referència, el plec de càrrecs notificat i "
            . $audiencia
            . ", la Direcció de CRT adopta la següent **RESOLUCIÓ**:\n\n"
            . "**FETS PROVATS**\n\n{$ctx['fets']}\n\n"
            . "**QUALIFICACIÓ**\n\nFalta **{$ctx['gravetat']}** de «{$ctx['falta']}», tipificada a {$ctx['tipificacio']}.\n"
            . $ctx['reincidencia']
            . "\n**VALORACIÓ I MOTIVACIÓ**\n\n{$motivacio}\n\n"
            . "**PART DISPOSITIVA**\n\nS'imposa la sanció de **{$sancio}**, amb efectes des de [data d'efectes].\n\n"
            . "Contra aquesta resolució podeu exercitar les accions que us reconeix la legislació vigent davant la "
            . "jurisdicció social en el termini de **20 dies hàbils** des de la seva notificació.\n";
    }

    private function escritAcomiadament(DisciplinaryCase $cas, array $ctx): string
    {
        return "Senyor/a,\n\n"
            . "La Direcció de CRT us comunica la decisió d'extingir el vostre contracte de treball per "
            . "**ACOMIADAMENT DISCIPLINARI**, a l'empara de l'art. 54 de l'Estatut dels Treballadors i del capítol 9 "
            . "del XII Conveni col·lectiu de la sanitat concertada de Catalunya, amb efectes des del **[data d'efectes]**, "
            . "sobre la base dels fets següents:\n\n"
            . "**FETS**\n\n{$ctx['fets']}\n\n"
            . "**QUALIFICACIÓ JURÍDICA**\n\nEls fets descrits constitueixen una falta **{$ctx['gravetat']}** de "
            . "«{$ctx['falta']}», tipificada a {$ctx['tipificacio']}, i justifiquen l'acomiadament conforme a l'art. 54 ET.\n"
            . $ctx['reincidencia']
            . "\n[GARANTIES PRÈVIES — verificar abans de signar: expedient contradictori si és representant dels "
            . "treballadors (art. 55.1 ET); audiència prèvia als delegats sindicals si consta afiliació (art. 55.1 ET "
            . "i 56.3 del conveni); audiència a la RLT en faltes molt greus (art. 55.I del conveni).]\n\n"
            . "A partir de la data d'efectes tindreu a la vostra disposició la liquidació, saldo i quitança "
            . "corresponents. Aquesta decisió es pot impugnar davant la jurisdicció social en el termini de "
            . "**20 dies hàbils** següents a la seva notificació (art. 59.3 ET).\n";
    }

    private function peu(string $tipus): string
    {
        return "\n---\n\n"
            . "Atentament,\n\n\n"
            . "________________________________\n"
            . "[Nom i cognoms del signant]\n"
            . "[Càrrec] — CRT\n\n"
            . "---\n\n"
            . "**JUSTIFICANT DE RECEPCIÓ** (a emplenar per la persona treballadora)\n\n"
            . "Rebo l'original d'aquest escrit en data ____ / ____ / ________.\n"
            . "La signatura acredita únicament la RECEPCIÓ, no la conformitat amb el seu contingut.\n\n\n"
            . "________________________________\n"
            . "Signatura de la persona treballadora\n\n"
            . "*(En cas de negativa a signar, es farà constar davant de dos testimonis i es remetrà per burofax "
            . "amb certificació de text i acusament de recepció.)*\n";
    }
}
