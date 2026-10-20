<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catàleg de permisos IGUAL que la fulla CONCEPTOS de l'Excel de RRHH (RRHH, 16-10-2026:
 * «la app tal cual dice el excel»). I permisos per hores.
 *
 * absence_types (columnes noves):
 *   codi_rrhh  el motiu tal com s'escriu a l'Excel: l'importador hi casa cada fila
 *   quantitat + unitat (dies | hores | setmanes | temps_necessari) + periode (fet | any | mes)
 *   notes      el que diu la columna d'observacions de CONCEPTOS
 *
 * absences (columnes noves):
 *   hora_inici / hora_fi / minuts   permís d'unes hores (metge de 9 a 10): el saldo de jornada
 *                                   només en descompta aquests minuts, no el dia sencer
 *   origen / origen_ref             d'on ve (excel_rrhh) i la clau per no duplicar-lo si es torna a importar
 *
 * Els tipus que ja hi havia es busquen pel NOM antic (no per id: CRT en té uns altres) i
 * se'ls posen el nom i els valors de l'Excel; els que falten es creen. Cap s'esborra.
 */
return new class extends Migration
{
    /** [nom antic a l'app => [codi_rrhh, nom nou, camps]] */
    private function existents(): array
    {
        return [
            'Matrimoni / Parella de fet' => ['matrimoni', 'Matrimoni', ['max_days' => 15, 'quantitat' => 15, 'unitat' => 'dies', 'periode' => 'fet', 'recoverable' => false]],
            'Defunció familiar (1r-2n grau)' => ['defunció familiar', 'Defunció familiar', ['max_days' => 5, 'extends_with_travel' => false, 'extra_days_travel' => null, 'quantitat' => 5, 'unitat' => 'dies', 'periode' => 'fet', 'recoverable' => false]],
            'Malaltia greu / Hospitalització' => ['malaltia greu familiar', 'Malaltia greu familiar', ['max_days' => 2, 'extends_with_travel' => false, 'extra_days_travel' => null, 'quantitat' => 2, 'unitat' => 'dies', 'periode' => 'fet', 'recoverable' => false]],
            "Trasllat d'habitatge" => ['trasllat', 'Trasllat', ['max_days' => 1, 'extends_with_travel' => false, 'extra_days_travel' => null, 'quantitat' => 1, 'unitat' => 'dies', 'periode' => 'fet', 'recoverable' => false]],
            'Assumptes personals' => ['assumptes propis', 'Assumptes propis', ['max_days' => 1, 'max_per_year' => 3, 'quantitat' => 3, 'unitat' => 'dies', 'periode' => 'any', 'recoverable' => false]],
            'Exàmens oficials' => ['exàmens', 'Exàmens', ['max_days' => null, 'max_per_year' => null, 'quantitat' => 10, 'unitat' => 'dies', 'periode' => 'any', 'recoverable' => true]],
            'Permís per maternitat/paternitat' => ['naixement fill/filla', 'Naixement fill/filla', ['max_days' => 140, 'quantitat' => 20, 'unitat' => 'setmanes', 'periode' => 'fet', 'recoverable' => false]],
            'Formació' => ['formació (20h anuals/jornada 8 hores)', 'Formació (20 h anuals, jornada de 8 h)', ['quantitat' => 20, 'unitat' => 'hores', 'periode' => 'any', 'recoverable' => false]],
            'Deure inexcusable públic' => ['deure públic /personal', 'Deure públic / personal', ['quantitat' => null, 'unitat' => 'temps_necessari', 'periode' => 'fet', 'recoverable' => false]],
            'Vacances' => ['vacances', 'Vacances', []],
            'Baixa IT (malaltia comuna)' => ['incapacitat', 'Baixa IT (malaltia comuna)', []],
        ];
    }

    /** Els que l'Excel té i l'app no. */
    private function nous(): array
    {
        $t = fn ($codi, $nom, $quantitat, $unitat, $periode, $recupera, $cat, $notes = null, $remunerat = true, $maxDies = null) => [
            'codi_rrhh' => $codi, 'name' => $nom, 'quantitat' => $quantitat, 'unitat' => $unitat, 'periode' => $periode,
            'recoverable' => $recupera, 'remunerated' => $remunerat, 'category' => $cat, 'notes' => $notes, 'max_days' => $maxDies,
            'requires_justification' => false, 'advance_notice_hours' => 0, 'extends_with_travel' => false,
        ];

        return [
            $t('assumptes propis hores', 'Assumptes propis (hores)', 24, 'hores', 'any', true, 'personal'),
            $t('defunció familiar + 90 km', 'Defunció familiar (+90 km)', 5, 'dies', 'fet', false, 'family', null, true, 5),
            $t('defunció parella', 'Defunció parella', 5, 'dies', 'fet', false, 'family', null, true, 5),
            $t('defunció parella + 90 km', 'Defunció parella (+90 km)', 7, 'dies', 'fet', false, 'family', null, true, 7),
            $t('malatia greu familiar + 90 km', 'Malaltia greu familiar (+90 km)', 7, 'dies', 'fet', false, 'family', null, true, 7),
            $t('metge fill < 16 anys', 'Metge fill/a < 16 anys', 8, 'hores', 'any', false, 'health', '1 jornada / 8 h'),
            $t('metge propi', 'Metge propi', 8, 'hores', 'any', false, 'health', '1 jornada / 8 h'),
            $t('causa major familiar', 'Causa major familiar', 4, 'dies', 'fet', false, 'family', 'Es pot dividir en hores', true, 4),
            $t('permís parental', 'Permís parental', 16, 'setmanes', 'fet', false, 'family', 'Suspensió contractual', false, 112),
            $t('lactància acumulada', 'Lactància acumulada', 15, 'dies', 'fet', false, 'family', null, true, 15),
            $t('maternitat', 'Maternitat', 19, 'setmanes', 'fet', false, 'family', 'Suspensió contractual', false, 133),
            $t('paternitat', 'Paternitat', 19, 'setmanes', 'fet', false, 'family', 'Suspensió contractual', false, 133),
            $t('urgència mèdica menor 16a', 'Urgència mèdica menor de 16 anys', 4, 'dies', 'fet', false, 'family', null, true, 4),
            $t('força major', 'Força major', 4, 'dies', 'fet', false, 'public', null, true, 4),
            $t('adaptació horària', 'Adaptació horària', null, 'hores', 'fet', true, 'personal'),
            $t('accions sindicals', 'Accions sindicals', 15, 'hores', 'mes', false, 'public'),
            $t('dia conveni ss', 'Dia de conveni Setmana Santa', 1, 'dies', 'any', false, 'vacation_extra', 'Art. 35 del conveni', true, 1),
            $t('dia conveni nadal', 'Dia de conveni Nadal', 1, 'dies', 'any', false, 'vacation_extra', 'Art. 35 del conveni', true, 1),
            $t('permís 8 setmanes', 'Permís 8 setmanes', 8, 'setmanes', 'fet', false, 'family', null, false, 56),
        ];
    }

    public function up(): void
    {
        Schema::table('absence_types', function (Blueprint $table) {
            if (! Schema::hasColumn('absence_types', 'codi_rrhh')) $table->string('codi_rrhh', 100)->nullable()->index();
            if (! Schema::hasColumn('absence_types', 'quantitat')) $table->decimal('quantitat', 6, 2)->nullable();
            if (! Schema::hasColumn('absence_types', 'unitat')) $table->string('unitat', 20)->nullable();
            if (! Schema::hasColumn('absence_types', 'periode')) $table->string('periode', 10)->nullable();
            if (! Schema::hasColumn('absence_types', 'notes')) $table->string('notes', 255)->nullable();
        });
        Schema::table('absences', function (Blueprint $table) {
            if (! Schema::hasColumn('absences', 'hora_inici')) $table->time('hora_inici')->nullable();
            if (! Schema::hasColumn('absences', 'hora_fi')) $table->time('hora_fi')->nullable();
            if (! Schema::hasColumn('absences', 'minuts')) $table->unsignedInteger('minuts')->nullable();
            if (! Schema::hasColumn('absences', 'origen')) $table->string('origen', 40)->nullable();
            if (! Schema::hasColumn('absences', 'origen_ref')) $table->string('origen_ref', 64)->nullable()->index();
        });

        $ara = now();
        foreach ($this->existents() as $nomAntic => [$codi, $nom, $camps]) {
            $id = DB::table('absence_types')->where('name', $nomAntic)->value('id');
            if (! $id) continue;
            DB::table('absence_types')->where('id', $id)
                ->update(array_merge($camps, ['codi_rrhh' => $codi, 'name' => $nom, 'updated_at' => $ara]));
        }
        foreach ($this->nous() as $tipus) {
            if (DB::table('absence_types')->where('codi_rrhh', $tipus['codi_rrhh'])->exists()) continue;
            DB::table('absence_types')->insert(array_merge($tipus, ['created_at' => $ara, 'updated_at' => $ara]));
        }
    }

    public function down(): void
    {
        // Els tipus nous només es treuen si no tenen cap absència (no s'esborra mai una absència).
        foreach ($this->nous() as $tipus) {
            $id = DB::table('absence_types')->where('codi_rrhh', $tipus['codi_rrhh'])->value('id');
            if ($id && ! DB::table('absences')->where('absence_type_id', $id)->exists()) {
                DB::table('absence_types')->where('id', $id)->delete();
            }
        }
        foreach ($this->existents() as $nomAntic => [$codi, $nom]) {
            DB::table('absence_types')->where('codi_rrhh', $codi)->update(['name' => $nomAntic]);
        }
        Schema::table('absences', function (Blueprint $table) {
            foreach (['hora_inici', 'hora_fi', 'minuts', 'origen', 'origen_ref'] as $c) {
                if (Schema::hasColumn('absences', $c)) $table->dropColumn($c);
            }
        });
        Schema::table('absence_types', function (Blueprint $table) {
            foreach (['codi_rrhh', 'quantitat', 'unitat', 'periode', 'notes'] as $c) {
                if (Schema::hasColumn('absence_types', $c)) $table->dropColumn($c);
            }
        });
    }
};
