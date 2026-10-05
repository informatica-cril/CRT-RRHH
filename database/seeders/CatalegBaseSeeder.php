<?php

namespace Database\Seeders;

use App\Models\AbsenceType;
use App\Models\BreakSetting;
use App\Models\ExcedenciaType;
use App\Models\Holiday;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

/**
 * Dades de base d'una instal·lació nova, SENSE persones ni dades de prova: horaris tipus, tipus de
 * permís (XII Conveni sanitari de Catalunya), tipus d'excedència, festius i configuració de la pausa.
 *
 * El DatabaseSeeder no es pot fer servir a producció (crea un admin amb contrasenya 123456, persones
 * de prova i ubicacions inventades). Aquest sí: és idempotent —el que ja existeix no es duplica ni es
 * toca—, així que es pot tornar a executar sense por.
 *
 *   php artisan db:seed --class=CatalegBaseSeeder --force
 */
class CatalegBaseSeeder extends Seeder
{
    public function run(): void
    {
        $dies = fn (string $ini, string $fi) => array_merge(
            array_map(fn ($d, $n) => ['day' => $d, 'name' => $n, 'active' => true, 'start' => $ini, 'end' => $fi],
                [1, 2, 3, 4, 5], ['Dilluns', 'Dimarts', 'Dimecres', 'Dijous', 'Divendres']),
            [['day' => 6, 'name' => 'Dissabte', 'active' => false, 'start' => '', 'end' => ''],
             ['day' => 0, 'name' => 'Diumenge', 'active' => false, 'start' => '', 'end' => '']]
        );
        $horaris = [
            ['name' => 'Jornada completa', 'total_hours_weekly' => 40, 'days' => $dies('08:00', '16:00')],
            ['name' => 'Mitja jornada matí', 'total_hours_weekly' => 20, 'days' => $dies('08:00', '12:00')],
            ['name' => 'Jornada intensiva', 'total_hours_weekly' => 40, 'days' => $dies('07:00', '15:00')],
        ];
        foreach ($horaris as $h) {
            WorkSchedule::firstOrCreate(['name' => $h['name']], $h);
        }

        $permisos = [
            ['name' => 'Vacances', 'recoverable' => false, 'remunerated' => true, 'max_days' => 30, 'max_per_year' => 1, 'requires_justification' => false, 'advance_notice_hours' => 336, 'category' => 'vacation'],
            ['name' => 'Matrimoni / Parella de fet', 'recoverable' => false, 'remunerated' => true, 'max_days' => 15, 'max_lifetime' => 1, 'requires_justification' => true, 'advance_notice_hours' => 336, 'category' => 'personal'],
            ['name' => 'Defunció familiar (1r-2n grau)', 'recoverable' => false, 'remunerated' => true, 'max_days' => 3, 'requires_justification' => true, 'advance_notice_hours' => 0, 'extends_with_travel' => true, 'extra_days_travel' => 2, 'category' => 'family'],
            ['name' => 'Malaltia greu / Hospitalització', 'recoverable' => false, 'remunerated' => true, 'max_days' => 5, 'requires_justification' => true, 'advance_notice_hours' => 0, 'extends_with_travel' => true, 'extra_days_travel' => 2, 'category' => 'family'],
            ['name' => "Trasllat d'habitatge", 'recoverable' => false, 'remunerated' => true, 'max_days' => 2, 'max_per_year' => 1, 'requires_justification' => true, 'advance_notice_hours' => 72, 'extends_with_travel' => true, 'extra_days_travel' => 1, 'category' => 'personal'],
            ['name' => 'Assumptes personals', 'recoverable' => false, 'remunerated' => true, 'max_days' => 1, 'max_per_year' => 3, 'requires_justification' => false, 'advance_notice_hours' => 48, 'category' => 'personal'],
            ['name' => 'Exàmens oficials', 'recoverable' => false, 'remunerated' => true, 'requires_justification' => true, 'advance_notice_hours' => 48, 'category' => 'training'],
            ['name' => 'Exàmens prenatals', 'recoverable' => false, 'remunerated' => true, 'requires_justification' => true, 'advance_notice_hours' => 24, 'category' => 'health'],
            ['name' => 'Baixa mèdica', 'recoverable' => false, 'remunerated' => true, 'requires_justification' => true, 'advance_notice_hours' => 0, 'category' => 'health'],
            ['name' => 'Permís per maternitat/paternitat', 'recoverable' => false, 'remunerated' => true, 'max_days' => 112, 'requires_justification' => true, 'advance_notice_hours' => 168, 'category' => 'family'],
            ['name' => 'Formació', 'recoverable' => true, 'remunerated' => true, 'requires_justification' => true, 'advance_notice_hours' => 72, 'category' => 'training'],
            ['name' => 'Deure inexcusable públic', 'recoverable' => false, 'remunerated' => true, 'requires_justification' => true, 'advance_notice_hours' => 24, 'category' => 'public'],
        ];
        foreach ($permisos as $p) {
            AbsenceType::firstOrCreate(['name' => $p['name']], $p);
        }

        $excedencies = [
            ['name' => 'Excedència voluntària', 'min_months' => 4, 'max_months' => 60, 'requires_seniority_months' => 12, 'job_reserve' => false, 'seniority_counts' => false, 'description' => 'Dret preferent de reincorporació a la mateixa o similar categoria'],
            ['name' => 'Excedència per cura de fill/a', 'min_months' => 1, 'max_months' => 36, 'requires_seniority_months' => 0, 'job_reserve' => true, 'job_reserve_months' => 12, 'seniority_counts' => true, 'description' => '1r any: reserva de lloc. Posteriors: reserva de categoria'],
            ['name' => 'Excedència per cura de familiar', 'min_months' => 1, 'max_months' => 36, 'requires_seniority_months' => 0, 'job_reserve' => true, 'job_reserve_months' => 12, 'seniority_counts' => true, 'description' => 'Per cura de familiar fins a 2n grau que no es pugui valer per si mateix'],
            ['name' => 'Excedència forçosa', 'min_months' => null, 'max_months' => null, 'requires_seniority_months' => 0, 'job_reserve' => true, 'seniority_counts' => true, 'description' => 'Per elecció o designació a càrrec públic o sindical'],
        ];
        foreach ($excedencies as $e) {
            ExcedenciaType::firstOrCreate(['name' => $e['name']], $e);
        }

        // Festius: el seeder propi (2025 i 2026, amb Sant Esteve). Només si encara no n'hi ha cap,
        // perquè aquell seeder fa create() i duplicaria.
        if (! Holiday::exists()) {
            $this->call(HolidaySeeder::class);
        }

        if (! BreakSetting::exists()) {
            BreakSetting::create([
                'enabled' => true, 'threshold_hours' => 5, 'break_duration_minutes' => 20,
                'auto_start' => false, 'grace_period_minutes' => 0,
            ]);
        }

        $this->command?->info('Dades de base al dia: ' . WorkSchedule::count() . ' horaris, ' . AbsenceType::count()
            . ' tipus de permís, ' . ExcedenciaType::count() . ' d\'excedència, ' . Holiday::count() . ' festius.');
    }
}
