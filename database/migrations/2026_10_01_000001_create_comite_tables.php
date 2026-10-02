<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REPRESENTACIÓ LEGAL DE LES PERSONES TREBALLADORES: qui en forma part i les hores que hi dedica.
 *
 * El crèdit horari (art. 68.e ET) va per HORES i per PERSONA; les absències de l'app van per dies,
 * així que no s'hi podia encabir. Dues taules:
 *
 *  - comite_membres: qui és representant i des de quan fins a quan. Amb històric (data_baixa), perquè
 *    les hores d'un mandat anterior s'han de poder justificar encara que la persona ja no hi sigui.
 *  - comite_hores: cada ús del crèdit. El registra la mateixa persona; RRHH només el valida o el rebutja.
 *    user_id hi és repetit (també surt de membre_id) a propòsit: qui consulta la BD no ha de fer un JOIN
 *    per saber de qui és cada hora.
 *
 * Les hores de reunions convocades per l'empresa (negociació) no consumeixen crèdit: es desa
 * consumeix_credit perquè el còmput no depengui de reinterpretar el tipus més endavant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comite_membres')) {
            Schema::create('comite_membres', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                // Comitè (art. 63 ET), delegat de personal (art. 62 ET) o delegat sindical (art. 10 LOLS).
                // Avui només hi ha comitè; la columna evita tocar l'esquema si algun dia n'hi ha d'altres.
                $table->enum('tipus_representacio', ['comite', 'delegat_personal', 'delegat_sindical'])->default('comite');
                $table->enum('carrec', ['president', 'secretari', 'vocal', 'delegat'])->default('vocal');
                $table->string('sindicat', 80)->nullable();
                $table->date('data_alta');
                $table->date('data_baixa')->nullable();
                // Hores al mes que té reconegudes. L'escala legal depèn de la plantilla; el conveni la pot millorar.
                $table->decimal('credit_hores_mensual', 5, 2);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('creat_per')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'data_alta'], 'comite_membres_user_idx');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('creat_per')->references('id')->on('users')->nullOnDelete();
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE comite_membres ADD CONSTRAINT chk_comite_membre_dates
                    CHECK (data_baixa IS NULL OR data_baixa >= data_alta)');
                DB::statement('ALTER TABLE comite_membres ADD CONSTRAINT chk_comite_membre_credit
                    CHECK (credit_hores_mensual >= 0)');
            }
        }

        if (! Schema::hasTable('comite_hores')) {
            Schema::create('comite_hores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('membre_id');
                $table->unsignedBigInteger('user_id');
                $table->date('data');
                $table->time('hora_inici');
                $table->time('hora_fi');
                $table->unsignedInteger('minuts');
                $table->enum('tipus', ['reunio_comite', 'assemblea', 'negociacio_empresa', 'formacio', 'gestio', 'altres']);
                $table->boolean('consumeix_credit')->default(true);
                $table->text('motiu');
                $table->string('justificant_path')->nullable();
                $table->string('justificant_name', 200)->nullable();
                $table->enum('estat', ['pendent', 'validada', 'rebutjada'])->default('pendent');
                $table->unsignedBigInteger('validat_per')->nullable();
                $table->timestamp('validat_ts')->nullable();
                $table->text('motiu_rebuig')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'data'], 'comite_hores_user_idx');
                $table->index(['estat', 'data'], 'comite_hores_estat_idx');
                $table->foreign('membre_id')->references('id')->on('comite_membres')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('validat_per')->references('id')->on('users')->nullOnDelete();
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE comite_hores ADD CONSTRAINT chk_comite_hores_franja
                    CHECK (hora_fi > hora_inici)');
                // Sense CHECK sobre validat_per: MySQL 8 no admet un CHECK en una columna amb FK
                // ON DELETE SET NULL (error 3823). Que una hora resolta tingui qui i quan ho garanteix
                // ComiteController::resol().
            }
        }

        $this->creaVistes();
    }

    /**
     * Vistes de consulta: la mateixa informació de les taules, amb noms en lloc d'ids i només les
     * columnes que interessen. Són de només lectura i l'app no en depèn; si cal canviar-ne una,
     * es fa amb CREATE OR REPLACE en una migració nova. Mai hi surten contrasenyes, secrets 2FA ni
     * coordenades.
     */
    private function creaVistes(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_treballadors AS
            SELECT u.id,
                   u.name AS nom,
                   u.dni,
                   u.email,
                   CASE u.role WHEN 'admin' THEN 'Administració' WHEN 'hr' THEN 'RRHH'
                               WHEN 'coordinator' THEN 'Coordinació' WHEN 'service' THEN 'Servei (integració)'
                               ELSE 'Treballador/a' END AS rol,
                   CASE u.relacio WHEN 'autonom' THEN 'Autònom' ELSE 'Laboral' END AS relacio,
                   u.job_profile AS perfil,
                   (SELECT GROUP_CONCAT(d.name ORDER BY d.sort SEPARATOR ', ')
                      FROM department_user du JOIN departments d ON d.id = du.department_id
                     WHERE du.user_id = u.id) AS departaments,
                   (SELECT GROUP_CONCAT(l.name ORDER BY l.sort SEPARATOR ', ')
                      FROM lot_user lu JOIN lots l ON l.id = lu.lot_id
                     WHERE lu.user_id = u.id) AS lots,
                   ws.name AS horari,
                   ws.total_hours_weekly AS hores_setmanals,
                   u.seniority_date AS antiguitat,
                   IF(u.active, 'Sí', 'No') AS actiu,
                   IF(EXISTS (SELECT 1 FROM comite_membres cm
                               WHERE cm.user_id = u.id AND cm.data_alta <= CURDATE()
                                 AND (cm.data_baixa IS NULL OR cm.data_baixa >= CURDATE())), 'Sí', 'No') AS representant,
                   u.created_at AS alta_app
              FROM users u
              LEFT JOIN work_schedules ws ON ws.id = u.work_schedule_id
             WHERE u.role <> 'service'
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_fitxatges AS
            SELECT wl.id,
                   wl.user_id,
                   u.name AS treballador,
                   u.dni,
                   wl.date AS data,
                   TIME(wl.start_time) AS entrada,
                   TIME(wl.end_time) AS sortida,
                   TIME(wl.break_start_time) AS inici_pausa,
                   TIME(wl.break_end_time) AS fi_pausa,
                   COALESCE(wl.effective_hours, wl.total_hours_worked, wl.hours_worked) AS hores,
                   wl.extra_hours_authorized AS hores_extra_autoritzades,
                   wl.extra_hours_unauthorized AS hores_extra_no_autoritzades,
                   wl.hours_out_of_area AS hores_fora_zona,
                   CASE wl.status WHEN 'pending' THEN 'Pendent' WHEN 'approved' THEN 'Aprovat'
                                  WHEN 'rejected' THEN 'Rebutjat' WHEN 'modified' THEN 'Modificat'
                                  ELSE wl.status END AS estat,
                   IF(wl.location_match, 'Sí', 'No') AS dins_zona,
                   IF(wl.auto_closed, 'Sí', 'No') AS tancat_automatic,
                   wl.rejection_reason AS motiu_rebuig
              FROM work_logs wl
              JOIN users u ON u.id = wl.user_id
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_absencies AS
            SELECT a.id,
                   a.user_id,
                   u.name AS treballador,
                   u.dni,
                   t.name AS tipus,
                   a.start_date AS desde,
                   a.end_date AS fins,
                   DATEDIFF(a.end_date, a.start_date) + 1 AS dies_naturals,
                   CASE WHEN a.approved IS NULL THEN 'Pendent' WHEN a.approved = 1 THEN 'Aprovada'
                        ELSE 'Denegada' END AS estat,
                   ap.name AS resolt_per,
                   a.approved_at AS resolt_el,
                   a.denial_reason AS motiu_denegacio,
                   a.reason AS motiu,
                   IF(a.justificant_path IS NULL, 'No', 'Sí') AS justificant,
                   a.created_at AS sollicitada_el
              FROM absences a
              JOIN users u ON u.id = a.user_id
              JOIN absence_types t ON t.id = a.absence_type_id
              LEFT JOIN users ap ON ap.id = a.approved_by
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_comite_membres AS
            SELECT cm.id,
                   cm.user_id,
                   u.name AS treballador,
                   u.dni,
                   CASE cm.tipus_representacio WHEN 'comite' THEN 'Comitè d''empresa'
                        WHEN 'delegat_personal' THEN 'Delegat/da de personal'
                        ELSE 'Delegat/da sindical' END AS representacio,
                   cm.carrec,
                   cm.sindicat,
                   cm.data_alta,
                   cm.data_baixa,
                   IF(cm.data_alta <= CURDATE() AND (cm.data_baixa IS NULL OR cm.data_baixa >= CURDATE()), 'Sí', 'No') AS vigent,
                   cm.credit_hores_mensual
              FROM comite_membres cm
              JOIN users u ON u.id = cm.user_id
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_comite_hores AS
            SELECT h.id,
                   h.user_id,
                   u.name AS treballador,
                   u.dni,
                   h.data,
                   h.hora_inici,
                   h.hora_fi,
                   ROUND(h.minuts / 60, 2) AS hores,
                   CASE h.tipus WHEN 'reunio_comite' THEN 'Reunió del comitè' WHEN 'assemblea' THEN 'Assemblea'
                        WHEN 'negociacio_empresa' THEN 'Reunió convocada per l''empresa'
                        WHEN 'formacio' THEN 'Formació sindical' WHEN 'gestio' THEN 'Gestions de representació'
                        ELSE 'Altres' END AS tipus,
                   IF(h.consumeix_credit, 'Sí', 'No') AS consumeix_credit,
                   h.motiu,
                   IF(h.justificant_path IS NULL, 'No', 'Sí') AS justificant,
                   CASE h.estat WHEN 'pendent' THEN 'Pendent' WHEN 'validada' THEN 'Validada' ELSE 'Rebutjada' END AS estat,
                   v.name AS validat_per,
                   h.validat_ts AS validat_el,
                   h.motiu_rebuig,
                   h.created_at AS registrada_el
              FROM comite_hores h
              JOIN users u ON u.id = h.user_id
              LEFT JOIN users v ON v.id = h.validat_per
        SQL);

        // Una fila per persona i mes amb hores: el resum que cal per justificar el crèdit.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_comite_credit_mensual AS
            SELECT h.user_id,
                   u.name AS treballador,
                   u.dni,
                   DATE_FORMAT(h.data, '%Y-%m') AS mes,
                   cm.credit_hores_mensual AS credit,
                   ROUND(SUM(CASE WHEN h.estat = 'validada' AND h.consumeix_credit THEN h.minuts ELSE 0 END) / 60, 2) AS hores_validades,
                   ROUND(SUM(CASE WHEN h.estat = 'pendent' AND h.consumeix_credit THEN h.minuts ELSE 0 END) / 60, 2) AS hores_pendents,
                   ROUND(SUM(CASE WHEN h.estat = 'validada' AND NOT h.consumeix_credit THEN h.minuts ELSE 0 END) / 60, 2) AS hores_convocades_empresa,
                   ROUND(cm.credit_hores_mensual
                         - SUM(CASE WHEN h.estat = 'validada' AND h.consumeix_credit THEN h.minuts ELSE 0 END) / 60, 2) AS hores_restants
              FROM comite_hores h
              JOIN comite_membres cm ON cm.id = h.membre_id
              JOIN users u ON u.id = h.user_id
             GROUP BY h.user_id, u.name, u.dni, DATE_FORMAT(h.data, '%Y-%m'), cm.id, cm.credit_hores_mensual
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['v_comite_credit_mensual', 'v_comite_hores', 'v_comite_membres', 'v_absencies', 'v_fitxatges', 'v_treballadors'] as $v) {
                DB::statement("DROP VIEW IF EXISTS {$v}");
            }
        }
        Schema::dropIfExists('comite_hores');
        Schema::dropIfExists('comite_membres');
    }
};
