<?php

return [

    /*
    | Fonts que entren al llibre d'integritat. Cada nit s'incorporen les files NOVES de cada taula
    | (una sola vegada per fila). Per defecte s'hashea la fila SENCERA (json canònic), així no cal
    | conèixer-ne les columnes: qualsevol alteració posterior es detecta. Si una taula no existeix o
    | no té la columna de temps, aquella font s'omet sense trencar la resta.
    |
    | 'estables' => [...] és per a les fonts que canvien LEGÍTIMAMENT després d'entrar al llibre (una
    | nòmina que després es visualitza o se signa). En comptes de renunciar a rellegir la fila —el que
    | feia l'antic 'mutable' => true, que deixava passar un canvi d'IMPORT sense dir res—, es declara
    | QUINES columnes se segellen: només aquestes entren al hash i només aquestes es tornen a llegir
    | a verify(). Les que canvien legítimament (viewed_at, signed_at, updated_at…) queden FORA i no
    | fan cantar el llibre; les que no han de canviar mai (import, període, titular, PDF) queden
    | DINS i qualsevol retoc posterior es denuncia.
    |
    | Les fonts sense 'estables' són append-only: es hashea i es rellegeix la fila sencera. Si canvia
    | o desapareix DESPRÉS de segellar-se, verify() ho denuncia.
    |
    | ⚠️ Canviar la llista 'estables' d'una font canvia la base del hash: els esdeveniments ja
    | segellats d'aquella font passarien a donar vermell. Si algun dia cal tocar-la, s'ha de fer amb
    | un tall de versió, no en calent.
    */
    'sources' => [
        ['table' => 'compliance_acknowledgements', 'ts' => 'acknowledged_at'],

        // Firma de document: la fila neix quan el treballador OBRE el document (viewed_at) i es
        // completa quan el signa. Se segella tot menys el rastre de visualització i updated_at.
        ['table' => 'document_signatures',         'ts' => 'created_at', 'estables' => [
            'id', 'document_id', 'user_id', 'signed_at', 'document_hash', 'signature_hash',
            'timestamp_source', 'timestamp_token', 'signing_payload', 'legal_basis',
            'ip_address', 'user_agent', 'created_at',
        ]],

        // Nòmina: l'import, el període, el titular i el PDF no canvien MAI un cop entregada.
        // (payroll_base64 hi és perquè és on viu de debò la nòmina: 'amount' és només el resum.)
        // Fora del segell: viewed_at, signed_at, signature_hash i updated_at — el rastre d'entrega.
        ['table' => 'payrolls',                    'ts' => 'updated_at', 'estables' => [
            'id', 'user_id', 'title', 'month', 'year', 'amount',
            'payroll_base64', 'file_name', 'expires_at', 'created_at',
        ]],

        ['table' => 'work_log_modifications',      'ts' => 'created_at'],
        ['table' => 'audit_logs',                  'ts' => 'created_at'],
        ['table' => 'chat_policy_acceptances',     'ts' => 'accepted_at'],
        // Procediment disciplinari: acusaments de recepció in-app + cadena de custòdia dels casos.
        // El document entra al llibre quan s'acusa recepció (acus_ts): a partir d'aquí ja no
        // s'edita (updateDocument exigeix estat 'esborrany'), així que va sencer.
        ['table' => 'disciplinary_documents',      'ts' => 'acus_ts'],
        ['table' => 'disciplinary_case_events',    'ts' => 'created_at'],

        // ELEMENTS DE PROVA: el segell cobria el paper (escrits i historial) però no la PROVA sobre
        // la qual el paper es fonamenta. Un fet retocat després d'entrar a un expedient —la data, la
        // magnitud, la font, la imputabilitat— no feia cantar res. Se'n segella el contingut
        // probatori; queden fora case_id i estat, que canvien legítimament quan el fet s'incorpora a
        // un cas o es descarta.
        ['table' => 'disciplinary_elements',       'ts' => 'created_at', 'estables' => [
            'id', 'professional', 'user_id', 'tipus_falta', 'data_fet', 'data_coneixement',
            'font', 'origen', 'valor', 'descripcio', 'imputable', 'created_by', 'created_at',
        ]],
    ],

    /*
    | FONTS REMOTES: firmes d'ALTRES apps (domi, logopedia…). Repos/BD separats → s'integren per API
    | (memòria: integrar només per API). Cada app exposa un endpoint read-only amb token que retorna
    | les firmes del dia com { id, occurred_at, hash }. El sellador de RRHH les encadena en el MATEIX
    | segell diari → un sol crèdit FNMT engloba les firmes de totes les apps.
    */
    'remote_sources' => array_values(array_filter([
        env('DOMI_FIRMES_URL') ? [
            'name'  => 'domi_firmes',
            'url'   => env('DOMI_FIRMES_URL'),      // p.ex. http://127.0.0.1:8080/api/firmes_dia.php
            'token' => env('DOMI_FIRMES_TOKEN', ''),
        ] : null,
        env('LOGO_FIRMES_URL') ? [
            'name'  => 'logopedia_firmes',
            'url'   => env('LOGO_FIRMES_URL'),
            'token' => env('LOGO_FIRMES_TOKEN', ''),
        ] : null,
    ])),

    /*
    | Acreditacions de FORMACIÓ obligatòria (domi). Read-only per token (el mateix DOMI_FIRMES_TOKEN).
    | Deriva de DOMI_FIRMES_URL substituint el nom del script, o s'especifica explícitament.
    */
    'formacio' => [
        'estat_url' => env('DOMI_FORMACIO_ESTAT_URL', str_replace('firmes_dia.php', 'formacio_estat.php', (string) env('DOMI_FIRMES_URL'))),
        'doc_url'   => env('DOMI_FORMACIO_DOC_URL', str_replace('firmes_dia.php', 'formacio_doc.php', (string) env('DOMI_FIRMES_URL'))),
        'token'     => env('DOMI_FIRMES_TOKEN', ''),
    ],

    /*
    | Segellat de temps (TSA RFC 3161). 1 segell/dia per a TOTA l'app: es timestampa NOMÉS l'arrel
    | del dia, no cada esdeveniment. Si 'url' és buit, el dia es tanca amb la cadena de hash
    | (integritat interna) i queda 'pendent' de segell extern fins que es configuri la FNMT.
    |
    | IMPORTANT: confirmar l'endpoint i credencials reals del proveïdor (FNMT/Mensatek). No
    | s'inventen aquí: s'agafen de l'entorn.
    */
    'tsa' => [
        'provider' => env('TSA_PROVIDER', 'fnmt'),
        'url'      => env('TSA_URL', ''),            // endpoint RFC 3161 del proveïdor
        'user'     => env('TSA_USER', ''),           // basic-auth opcional
        'password' => env('TSA_PASSWORD', ''),
        'hash_alg' => env('TSA_HASH_ALG', 'sha256'),
        'openssl'  => env('OPENSSL_BIN', 'openssl'), // binari openssl del sistema
        'timeout'  => (int) env('TSA_TIMEOUT', 20),
    ],

    // Hora del tancament diari (el Kernel el programa). Un sol segell diari = 1 crèdit/dia.
    // Final del dia: 23:45 (les firmes posteriors entren al segell de l'endemà, sense orfes).
    'seal_at' => env('INTEGRITY_SEAL_AT', '23:45'),
];
