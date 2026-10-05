<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Entitat per a la qual treballa cada persona: CRIL, CRT o totes dues.
 *
 * Hi ha personal que treballa a CRIL i a CRT i ha de poder entrar a totes dues plataformes; fins ara
 * no constava enlloc i calia deduir-ho del domini del correu (@crtbcn.cat), que no és fiable: n'hi ha
 * amb correu de CRIL que també treballen a CRT. Per defecte CRT, que és qui és a aquesta app.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'entitat')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('entitat', ['CRIL', 'CRT', 'CRIL_CRT'])->default('CRT')->after('relacio');
            });
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // La vista de consulta passa a dir l'entitat.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_treballadors AS
            SELECT u.id,
                   u.name AS nom,
                   u.dni,
                   u.email,
                   CASE u.role WHEN 'admin' THEN 'Administració' WHEN 'hr' THEN 'RRHH'
                               WHEN 'coordinator' THEN 'Coordinació' WHEN 'service' THEN 'Servei (integració)'
                               ELSE 'Treballador/a' END AS rol,
                   CASE u.entitat WHEN 'CRT' THEN 'CRT' WHEN 'CRIL_CRT' THEN 'CRIL i CRT' WHEN 'CRIL' THEN 'CRIL' ELSE 'CRT' END AS entitat,
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
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'entitat')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('entitat');
            });
        }
    }
};
