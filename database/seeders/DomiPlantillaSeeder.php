<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DomiPlantillaSeeder extends Seeder
{
    private const PLANTILLA = [
        ['dni' => '90000907K', 'name' => 'Xavier Ubach Garcia',        'email' => 'x.ubach@exemple.invalid',    'work_type' => 'DOMICILIARIA', 'jornada' => 'Jornada completa'],
        ['dni' => '90000908E', 'name' => 'Clara Segura Miralles',      'email' => 'c.segura@exemple.invalid',   'work_type' => 'DOMICILIARIA', 'jornada' => 'Mitja jornada matí'],
        ['dni' => '90000909T', 'name' => 'Immaculada Lorente Rodríguez','email' => 'i.lorente@exemple.invalid', 'work_type' => 'DOMICILIARIA', 'jornada' => 'Jornada intensiva'],
        ['dni' => '90000108G', 'name' => 'Antoni Prat Pujol',          'email' => 'a.prat@exemple.invalid',     'work_type' => 'DOMICILIARIA', 'jornada' => 'Jornada completa'],
        ['dni' => '90000109M', 'name' => 'Anna Roca Sánchez',          'email' => 'a.roca@exemple.invalid',     'work_type' => 'DOMICILIARIA', 'jornada' => 'Mitja jornada matí'],
        ['dni' => '90000110Y', 'name' => 'Àngels Mas Puig',            'email' => 'a.mas@exemple.invalid',      'work_type' => 'DOMICILIARIA', 'jornada' => 'Jornada completa'],
        ['dni' => '90000906C', 'name' => 'Irene Tomàs Xifré',          'email' => 'i.tomas@exemple.invalid',    'work_type' => 'DOMICILIARIA', 'jornada' => 'Jornada intensiva'],
        ['dni' => '90000953K', 'name' => 'Perfil de proves tpo',       'email' => 'p.tpo@exemple.invalid',      'work_type' => 'DOMICILIARIA', 'jornada' => 'Mitja jornada matí'],
        ['dni' => '90000962F', 'name' => 'Perfil de proves Domiciliària','email' => 'p.domi@exemple.invalid',   'work_type' => 'DOMICILIARIA', 'jornada' => 'Jornada completa'],
    ];

    public function run(): void
    {
        $jornades = DB::table('work_schedules')->pluck('id', 'name');
        $creats = 0; $actualitzats = 0; $sense = [];

        foreach (self::PLANTILLA as $p) {
            $jid = $jornades[$p['jornada']] ?? null;
            if ($jid === null) { $sense[] = $p['jornada']; continue; }

            $existent = DB::table('users')->where('dni', $p['dni'])->first();
            $dades = [
                'name' => $p['name'], 'email' => $p['email'], 'dni' => $p['dni'],
                'work_type' => $p['work_type'], 'work_schedule_id' => $jid,
                'updated_at' => now(),
            ];

            if ($existent) {
                DB::table('users')->where('id', $existent->id)->update($dades);
                $actualitzats++;
                $this->command->line("  = {$p['name']} · {$p['dni']} · {$p['jornada']}");
            } else {
                DB::table('users')->insert($dades + [
                    'password' => Hash::make('CrtDev2026!'),
                    'created_at' => now(),
                ]);
                $creats++;
                $this->command->line("  + {$p['name']} · {$p['dni']} · {$p['jornada']}");
            }
        }

        $this->command->info("Plantilla domi: {$creats} creats, {$actualitzats} actualitzats.");
        foreach (array_unique($sense) as $j) {
            $this->command->error("Jornada inexistent a work_schedules: {$j}");
        }
    }
}
