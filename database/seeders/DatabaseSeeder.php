<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\AbsenceType;
use App\Models\ExcedenciaType;
use App\Models\Document;
use App\Models\OnboardingProfile;
use App\Models\OnboardingStatus;
use App\Models\Holiday;
use App\Models\Payroll;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatSetting;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Mirrors the initDB() function from db.js
     */
    public function run(): void
    {
        // ── Work Schedules ──
        $scheduleCompleta = WorkSchedule::create([
            'name' => 'Jornada completa',
            'total_hours_weekly' => 40,
            'days' => [
                ['day' => 1, 'name' => 'Dilluns', 'active' => true, 'start' => '08:00', 'end' => '16:00'],
                ['day' => 2, 'name' => 'Dimarts', 'active' => true, 'start' => '08:00', 'end' => '16:00'],
                ['day' => 3, 'name' => 'Dimecres', 'active' => true, 'start' => '08:00', 'end' => '16:00'],
                ['day' => 4, 'name' => 'Dijous', 'active' => true, 'start' => '08:00', 'end' => '16:00'],
                ['day' => 5, 'name' => 'Divendres', 'active' => true, 'start' => '08:00', 'end' => '16:00'],
                ['day' => 6, 'name' => 'Dissabte', 'active' => false, 'start' => '', 'end' => ''],
                ['day' => 0, 'name' => 'Diumenge', 'active' => false, 'start' => '', 'end' => ''],
            ],
        ]);

        $scheduleMitja = WorkSchedule::create([
            'name' => 'Mitja jornada matí',
            'total_hours_weekly' => 20,
            'days' => [
                ['day' => 1, 'name' => 'Dilluns', 'active' => true, 'start' => '08:00', 'end' => '12:00'],
                ['day' => 2, 'name' => 'Dimarts', 'active' => true, 'start' => '08:00', 'end' => '12:00'],
                ['day' => 3, 'name' => 'Dimecres', 'active' => true, 'start' => '08:00', 'end' => '12:00'],
                ['day' => 4, 'name' => 'Dijous', 'active' => true, 'start' => '08:00', 'end' => '12:00'],
                ['day' => 5, 'name' => 'Divendres', 'active' => true, 'start' => '08:00', 'end' => '12:00'],
                ['day' => 6, 'name' => 'Dissabte', 'active' => false, 'start' => '', 'end' => ''],
                ['day' => 0, 'name' => 'Diumenge', 'active' => false, 'start' => '', 'end' => ''],
            ],
        ]);

        $scheduleIntensiva = WorkSchedule::create([
            'name' => 'Jornada intensiva',
            'total_hours_weekly' => 40,
            'days' => [
                ['day' => 1, 'name' => 'Dilluns', 'active' => true, 'start' => '07:00', 'end' => '15:00'],
                ['day' => 2, 'name' => 'Dimarts', 'active' => true, 'start' => '07:00', 'end' => '15:00'],
                ['day' => 3, 'name' => 'Dimecres', 'active' => true, 'start' => '07:00', 'end' => '15:00'],
                ['day' => 4, 'name' => 'Dijous', 'active' => true, 'start' => '07:00', 'end' => '15:00'],
                ['day' => 5, 'name' => 'Divendres', 'active' => true, 'start' => '07:00', 'end' => '15:00'],
                ['day' => 6, 'name' => 'Dissabte', 'active' => false, 'start' => '', 'end' => ''],
                ['day' => 0, 'name' => 'Diumenge', 'active' => false, 'start' => '', 'end' => ''],
            ],
        ]);

        // ── Users ──
        $admin = User::create([
            'name' => 'Admin CRT', 'email' => 'admin@crtbcn.cat',
            'password' => Hash::make('123456'), 'role' => 'admin',
            'work_type' => 'AMBULATORIA', 'job_profile' => 'Gerencia',
            'postal_code_assigned' => '08001', 'work_schedule_id' => $scheduleCompleta->id,
            'active' => true, 'privacy_consent' => true, 'seniority_date' => '2020-01-15',
        ]);

        $maria = User::create([
            'name' => 'Maria García', 'email' => 'maria@crtbcn.cat',
            'password' => Hash::make('123456'), 'role' => 'worker',
            'work_type' => 'DOMICILIARIA', 'job_profile' => 'Fisioterapeuta',
            'postal_code_assigned' => '08001', 'work_schedule_id' => $scheduleCompleta->id,
            'active' => true, 'privacy_consent' => true, 'seniority_date' => '2021-03-01',
            'onboarding_completed' => false,
        ]);

        $joan = User::create([
            'name' => 'Joan Puig', 'email' => 'joan@crtbcn.cat',
            'password' => Hash::make('123456'), 'role' => 'worker',
            'work_type' => 'DOMICILIARIA', 'job_profile' => 'Logopeda',
            'postal_code_assigned' => '08002', 'work_schedule_id' => $scheduleCompleta->id,
            'active' => true, 'privacy_consent' => true, 'seniority_date' => '2022-06-15',
            'onboarding_completed' => false,
        ]);

        $pere = User::create([
            'name' => 'Pere Mas', 'email' => 'pere@crtbcn.cat',
            'password' => Hash::make('123456'), 'role' => 'worker',
            'work_type' => 'AMBULATORIA', 'job_profile' => 'Fisioterapeuta',
            'postal_code_assigned' => '08003', 'work_schedule_id' => $scheduleMitja->id,
            'active' => true, 'privacy_consent' => true, 'seniority_date' => '2019-09-01',
            'onboarding_completed' => true,
        ]);

        $anna = User::create([
            'name' => 'Anna Roca', 'email' => 'anna@crtbcn.cat',
            'password' => Hash::make('123456'), 'role' => 'worker',
            'work_type' => 'AMBULATORIA', 'job_profile' => 'Administracion',
            'postal_code_assigned' => '08001', 'work_schedule_id' => $scheduleIntensiva->id,
            'active' => true, 'privacy_consent' => true, 'seniority_date' => '2023-01-10',
            'onboarding_completed' => false,
        ]);

        $pau = User::create([
            'name' => 'Pau Soler', 'email' => 'pau@crtbcn.cat',
            'password' => Hash::make('123456'), 'role' => 'worker',
            'work_type' => 'AMBULATORIA', 'job_profile' => 'Logopeda',
            'postal_code_assigned' => '08015', 'work_schedule_id' => $scheduleMitja->id,
            'active' => true, 'privacy_consent' => true, 'seniority_date' => '2024-02-15',
            'onboarding_completed' => false,
        ]);

        // ── Absence Types (XII Convenio Sanitarios Cataluña) ──
        $absenceTypes = [
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
        foreach ($absenceTypes as $type) {
            AbsenceType::create($type);
        }

        // ── Excedencia Types ──
        ExcedenciaType::create(['name' => 'Excedència voluntària', 'min_months' => 4, 'max_months' => 60, 'requires_seniority_months' => 12, 'job_reserve' => false, 'seniority_counts' => false, 'description' => 'Dret preferent de reincorporació a la mateixa o similar categoria']);
        ExcedenciaType::create(['name' => 'Excedència per cura de fill/a', 'min_months' => 1, 'max_months' => 36, 'requires_seniority_months' => 0, 'job_reserve' => true, 'job_reserve_months' => 12, 'seniority_counts' => true, 'description' => '1r any: reserva de lloc. Posteriors: reserva de categoria']);
        ExcedenciaType::create(['name' => 'Excedència per cura de familiar', 'min_months' => 1, 'max_months' => 36, 'requires_seniority_months' => 0, 'job_reserve' => true, 'job_reserve_months' => 12, 'seniority_counts' => true, 'description' => 'Per cura de familiar fins a 2n grau que no es pugui valer per si mateix']);
        ExcedenciaType::create(['name' => 'Excedència forçosa', 'min_months' => null, 'max_months' => null, 'requires_seniority_months' => 0, 'job_reserve' => true, 'seniority_counts' => true, 'description' => 'Per elecció o designació a càrrec públic o sindical']);

        // ── Postal Code Assignments ──
        // PostalCodeAssignment::create(['user_id' => $maria->id, 'postal_codes' => ['08001'], 'valid_from' => '2021-03-01', 'notes' => 'Assignació inicial', 'created_by' => $admin->id]);
        // PostalCodeAssignment::create(['user_id' => $joan->id, 'postal_codes' => ['08002', '08003'], 'valid_from' => '2022-06-15', 'notes' => 'Zona doble', 'created_by' => $admin->id]);
        // PostalCodeAssignment::create(['user_id' => $pere->id, 'postal_codes' => ['08003'], 'valid_from' => '2019-09-01', 'notes' => 'Assignació inicial', 'created_by' => $admin->id]);
        // PostalCodeAssignment::create(['user_id' => $anna->id, 'postal_codes' => ['08001'], 'valid_from' => '2023-01-10', 'notes' => 'Assignació inicial', 'created_by' => $admin->id]);
        // PostalCodeAssignment::create(['user_id' => $pau->id, 'postal_codes' => ['08015'], 'valid_from' => '2024-02-15', 'notes' => 'Assignació inicial', 'created_by' => $admin->id]);

        // ── Municipal Assignments (Vallès) ──
        // Note: Missing from this snapshot, ignored for now.

        // ── Work Locations ──
        $seu = \App\Models\WorkLocation::create(['name' => 'Seu CRT (ubicació de prova)', 'lat' => 41.3870, 'lng' => 2.1700, 'radius' => 150, 'active' => true]);
        $seu2 = \App\Models\WorkLocation::create(['name' => 'Centre CRT 2 (ubicació de prova)', 'lat' => 41.3900, 'lng' => 2.1650, 'radius' => 150, 'active' => true]);
        
        $admin->workLocations()->attach([
            $seu->id => ['valid_from' => '2020-01-15'],
        ]);

        // ── Documents ──
        Document::create([
            'title' => 'Protocol de Prevenció de Riscos Laborals (PRL)',
            'description' => 'Document obligatori de prevenció de riscos al lloc de treball per a fisioterapeutes a domicili.',
            'content' => "PROTOCOL DE PREVENCIÓ DE RISCOS LABORALS\n\nCRT — Centre de Rehabilitació Terapèutica\n\n1. OBJECTIU\nAquest protocol estableix les mesures de prevenció de riscos laborals per als fisioterapeutes que realitzen tractaments a domicili dins de la província de Barcelona.\n\n2. RISCOS IDENTIFICATS\n- Riscos ergonòmics: manipulació manual de pacients\n- Riscos biològics: contacte amb fluids corporals\n- Riscos psicosocials: treball en solitari\n- Riscos vials: desplaçaments entre domicilis\n\n3. MESURES PREVENTIVES\n3.1 Ergonomia\n- Utilitzar tècniques de mobilització de pacients adequades\n- Realitzar pauses cada 45 minuts de treball continu\n- Utilitzar l'equipament de protecció individual (EPI) proporcionat\n\n3.2 Higiene\n- Rentar-se les mans abans i després de cada tractament\n- Utilitzar guants quan sigui necessari\n- Desinfectar l'equipament portàtil entre pacients\n\n3.3 Desplaçaments\n- Respectar les normes de trànsit\n- No conduir en estat de fatiga\n- Comunicar qualsevol incidència vial\n\n4. OBLIGACIONS DEL TREBALLADOR\n- Conèixer i aplicar aquest protocol\n- Comunicar qualsevol situació de risc\n- Participar en les formacions de PRL\n- Utilitzar els EPI proporcionats\n\n5. VIGÈNCIA\nAquest protocol és vigent des de la seva publicació i fins a la seva substitució per un de nou.",
            'file_name' => 'PRL_CRT_2026.pdf',
            'category' => 'prl',
            'requires_signature' => true,
            'is_urgent' => true,
            'target_users' => '"all"',
            'created_by' => $admin->id,
        ]);

        Document::create([
            'title' => 'Política de Protecció de Dades (RGPD)',
            'description' => 'Política interna de protecció de dades conforme al RGPD i LOPDGDD.',
            'content' => "POLÍTICA DE PROTECCIÓ DE DADES\n\nCRT — Centre de Rehabilitació Terapèutica\n\n1. RESPONSABLE DEL TRACTAMENT\nCentre de Rehabilitació Terapèutica, amb NIF PENDENT, és el responsable del tractament de les dades personals dels seus treballadors.\n\n2. FINALITATS DEL TRACTAMENT\n- Gestió de la relació laboral\n- Control horari (RDL 8/2019)\n- Geolocalització per al registre de jornada\n- Prevenció de riscos laborals\n\n3. DRETS DELS TREBALLADORS\nEls treballadors tenen dret a:\n- Accés a les seves dades personals\n- Rectificació de dades incorrectes\n- Supressió (dret a l'oblit)\n- Limitació del tractament\n- Portabilitat de les dades\n- Oposició al tractament\n\n4. CONSERVACIÓ DE DADES\n- Registre horari: 4 anys (art. 34.9 ET)\n- Dades de geolocalització: eliminades després del registre\n- Dades laborals: durant la relació laboral + termini legal\n\n5. MESURES DE SEGURETAT\nConformement a l'Esquema Nacional de Seguretat (ENS) RD 311/2022:\n- Xifratge de dades sensibles\n- Control d'accés per rols\n- Registre d'auditoria de totes les accions\n- Còpies de seguretat periòdiques\n\nSignant aquest document, declaro haver llegit i entès la política de protecció de dades de CRT.",
            'file_name' => 'RGPD_CRT_2026.pdf',
            'category' => 'normativa',
            'requires_signature' => true,
            'is_urgent' => false,
            'target_users' => '"all"',
            'created_by' => $admin->id,
        ]);

        Document::create([
            'title' => "Circular: Nou horari d'estiu 2026",
            'description' => "Informació sobre el canvi d'horari durant el període d'estiu.",
            'content' => "CIRCULAR INFORMATIVA\n\nAssumpte: Horari d'estiu 2026\n\nBenvolguts treballadors,\n\nUs informem que, d'acord amb el XII Conveni Col·lectiu del sector sanitari de Catalunya, durant el període comprès entre l'1 de juny i el 30 de setembre de 2026, s'aplicarà la jornada intensiva d'estiu.\n\nHorari d'estiu:\n- Dilluns a divendres: 7:00 - 15:00\n- Dissabtes i diumenges: descans\n\nAquest canvi s'aplicarà automàticament al sistema de control horari.\n\nPer a qualsevol dubte, contacteu amb Recursos Humans.\n\nAtentament,\nDirecció CRT",
            'file_name' => 'Circular_Estiu_2026.pdf',
            'category' => 'laboral',
            'requires_signature' => false,
            'is_urgent' => false,
            'target_users' => '"all"',
            'created_by' => $admin->id,
        ]);

        Document::create([
            'title' => 'Avís Legal de Geolocalització',
            'description' => 'Aquest document detalla la base legal i els vostres drets respecte a la geolocalització.',
            'content' => "AVÍS LEGAL DE GEOLOCALITZACIÓ\n\nCRT — Centre de Rehabilitació Terapèutica\n\nPer què es recopilen les vostres dades de geolocalització?\nConforme a l'article 34.9 de l'Estatut dels Treballadors (modificat pel RDL 8/2019), l'empresa té l'obligació legal de garantir el registre diari de jornada de tots els treballadors. Per als treballadors ambulatoris i domiciliaris, l'ús de la geolocalització és l'únic mecanisme viable per verificar la presència al lloc de treball assignat.\n\nBase Legal del Tractament\n- Art. 6.1.b RGPD — Execució del contracte de treball\n- Art. 6.1.c RGPD — Compliment d'una obligació legal (RDL 8/2019)\n- Art. 87 LOPDGDD — Dret a la intimitat en l'ús de dispositius digitals\n- Art. 90 LOPDGDD — Dret a la intimitat davant l'ús de sistemes de geolocalització\n- XII Conveni Col·lectiu del Sector Sanitari de Catalunya\n\nQuè es registra exactament?\n- Coordenades GPS en el moment del fitxatge (entrada/sortida)\n- Hora exacta del registre\n- Tipus de dispositiu i precisió del GPS\n- El registre de jornada es conserva 4 anys (art. 34.9 ET), SENSE coordenades\n- Les coordenades es purguen automàticament (purga automàtica als 30 dies naturals, o en resoldre's la incidència associada)\n- NO hi ha seguiment continu: la ubicació només es captura en els hitos\n\nEls vostres drets (RGPD Arts. 15-22)\n- Teniu dret a accedir, rectificar i suprimir les vostres dades, així com altres drets detallats a la nostra política de privacitat.\n\nUs del dispositiu personal i alternativa: L'ús del mòbil personal és VOLUNTARI. Si retires els permisos d'ubicació, l'app no podrà fer el registre automàtic; en aquest cas has d'utilitzar el mètode de registre ALTERNATIU (part de jornada), amb la mateixa validesa. Havent prestat efectivament el servei, això NO es considerarà absència, incompliment ni motiu de sanció.\n\nDeclaro que he estat informat/da sobre l'ús de la geolocalització per al registre de jornada, comprenc la base legal del tractament i conec els meus drets conforme al RGPD i la LOPDGDD.",
            'file_name' => 'Geolocalitzacio_CRT_2026.pdf',
            'category' => 'normativa',
            'requires_signature' => true,
            'is_urgent' => true,
            'target_users' => '"all"',
            'created_by' => $admin->id,
        ]);


        // ── Onboarding Profile ──
        $profile = OnboardingProfile::create([
            'name' => 'Perfil estàndard — Fisioterapeuta',
            'description' => 'Onboarding per a nous fisioterapeutes a domicili. Inclou PRL i RGPD.',
            'document_ids' => [1, 2],
            'is_default' => true,
        ]);

        // Set onboarding_profile_id for workers
        // User::whereIn('id', [$maria->id, $joan->id, $pere->id, $anna->id, $pau->id])
        //     ->update(['onboarding_profile_id' => $profile->id]);

        // Pere already completed onboarding
        // OnboardingStatus::create([
        //     'user_id' => $pere->id,
        //     'profile_id' => $profile->id,
        //     'current_step' => 2,
        //     'completed' => true,
        //     'started_at' => '2026-03-02 08:00:00',
        //     'completed_at' => '2026-03-02 08:45:00',
        //     'steps' => [
        //         ['document_id' => 1, 'viewed_at' => '2026-03-02 08:05:00', 'signed_at' => '2026-03-02 08:20:00'],
        //         ['document_id' => 2, 'viewed_at' => '2026-03-02 08:25:00', 'signed_at' => '2026-03-02 08:45:00'],
        //     ],
        // ]);

        // ── Holidays ──
        $holidays = [
            ['date' => '2026-01-01', 'name' => 'Cap d\'Any', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-01-06', 'name' => 'Reis', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-04-03', 'name' => 'Divendres Sant', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-04-06', 'name' => 'Dilluns de Pasqua', 'type' => 'regional', 'year' => 2026],
            ['date' => '2026-05-01', 'name' => 'Dia del Treball', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-05-25', 'name' => 'Segona Pasqua', 'type' => 'local', 'year' => 2026],
            ['date' => '2026-06-24', 'name' => 'Sant Joan', 'type' => 'regional', 'year' => 2026],
            ['date' => '2026-08-15', 'name' => 'Assumpció', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-09-11', 'name' => 'Diada de Catalunya', 'type' => 'regional', 'year' => 2026],
            ['date' => '2026-09-24', 'name' => 'La Mercè', 'type' => 'local', 'year' => 2026],
            ['date' => '2026-10-12', 'name' => 'Festa Nacional', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-11-01', 'name' => 'Tots Sants', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-12-06', 'name' => 'Dia de la Constitució', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-12-08', 'name' => 'Immaculada Concepció', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-12-25', 'name' => 'Nadal', 'type' => 'national', 'year' => 2026]
        ];
        foreach ($holidays as $h) {
            Holiday::create($h);
        }

        // ── Break Settings (pausa obligatoria) ──
        \App\Models\BreakSetting::create([
            'enabled' => true,
            'threshold_hours' => 5,
            'break_duration_minutes' => 20,
            'auto_start' => false,
            'grace_period_minutes' => 0,
        ]);

        // ── Alternative Work Locations (Ignored for now) ──

        // ── Chat System ──
        /*
        ChatSetting::create([
            'forensic_keywords' => ['acoso', 'amenaza', 'denuncia', 'abuso', 'discriminación', 'soborno', 'fraude', 'robo', 'violencia', 'demanda'],
            'chat_policy_text' => 'POLÍTICA D\'ÚS DEL XAT CORPORATIU — CRT...',
            'require_policy_acceptance' => true
        ]);

        $general = ChatConversation::create(['type' => 'channel', 'name' => '📢 General', 'created_by' => $admin->id, 'pinned' => true]);
        $fisio = ChatConversation::create(['type' => 'channel', 'name' => '🏥 Fisioterapia', 'created_by' => $admin->id, 'pinned' => false]);
        $logo = ChatConversation::create(['type' => 'channel', 'name' => '🗣️ Logopèdia', 'created_by' => $admin->id, 'pinned' => false]);

        $general->users()->attach([$admin->id]);
        $fisio->users()->attach([$admin->id]);
        $logo->users()->attach([$admin->id]);

        ChatMessage::create([
            'conversation_id' => $general->id, 'sender_id' => $admin->id,
            'content' => 'Benvinguts al canal general de CRT! Utilitzeu aquest canal per a comunicacions d\'àmbit general.',
        ]);
        */
    }
}

