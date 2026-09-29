<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa el portal de l'empleat antic de CRT (connexió `portal_origen`) a aquesta app:
 *   users                → users      (rol worker, sense servei; conserven la contrasenya)
 *   nominas              → payrolls   (PDF, mes i any; enllaç per DNI i, si no, per id)
 *   registro_de_entradas → work_logs  (per correu; hores locals → UTC com fa l'app)
 *
 * Per defecte és un ASSAIG (no escriu res). Amb --apply escriu dins d'una transacció.
 * Idempotent: no duplica usuaris (correu o DNI), nòmines (usuari+any+mes+fitxer) ni
 * jornades (usuari+hora d'inici), de manera que es pot repetir amb un dump més nou.
 * Els usuaris que ja existeixen NO es modifiquen.
 */
class ImportaPortal extends Command
{
    protected $signature = 'crt:importa-portal {--apply : Escriu els canvis (per defecte, assaig)}';

    protected $description = "Importa treballadors, nòmines i fitxatges del portal de l'empleat antic de CRT";

    private const TZ = 'Europe/Madrid';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $src = DB::connection('portal_origen');

        try {
            $src->getPdo();
        } catch (\Throwable $e) {
            $this->error("No es pot connectar a la BD d'origen (portal_origen): " . $e->getMessage());
            return self::FAILURE;
        }

        $this->info($apply ? 'Mode APPLY: s\'escriuran els canvis.' : 'Mode ASSAIG: no s\'escriu res (afegeix --apply).');

        DB::beginTransaction();
        try {
            $mapaUsuaris = $this->usuaris($src);
            $this->nomines($src, $mapaUsuaris);
            $this->fitxatges($src, $mapaUsuaris);

            $apply ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error; no s\'ha escrit res: ' . $this->missatgeSenseDades($e));
            return self::FAILURE;
        }

        $this->info($apply ? 'Importació completada.' : 'Assaig completat. Repeteix amb --apply per escriure.');

        return self::SUCCESS;
    }

    /**
     * Les QueryException inclouen l'SQL amb els valors (noms, DNI, hash...): no s'han de
     * mostrar per consola ni quedar als logs de Jenkins. Només el codi i el motiu del motor.
     */
    private function missatgeSenseDades(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Database\QueryException) {
            $motiu = $e->getPrevious()?->getMessage() ?? 'error SQL';
            $motiu = preg_replace("/(entry|value) '[^']*'/i", "$1 '…'", $motiu);

            return "SQLSTATE {$e->getCode()}: {$motiu}";
        }

        return get_class($e) . ': ' . $e->getMessage();
    }

    /** @return array{per_id: array<int,int>, per_dni: array<string,int>, per_email: array<string,int>} */
    private function usuaris($src): array
    {
        $mapa = ['per_id' => [], 'per_dni' => [], 'per_email' => []];
        $creats = $existents = 0;

        foreach ($src->table('users')->orderBy('id')->get() as $o) {
            $email = strtolower(trim((string) $o->email));
            $dni = strtoupper(trim((string) $o->dni)) ?: null;

            $id = DB::table('users')->where('email', $email)->value('id')
                ?? ($dni ? DB::table('users')->where('dni', $dni)->value('id') : null);

            if ($id) {
                $existents++;
            } else {
                // Insert directe: el hash bcrypt es copia tal qual (la persona manté la
                // contrasenya) sense passar pel cast `hashed` del model.
                $id = DB::table('users')->insertGetId([
                    'name' => trim((string) $o->nombre),
                    'email' => $email,
                    'dni' => $dni,
                    'device_phone' => mb_substr(trim((string) $o->telefono), 0, 20) ?: null,
                    'password' => $o->password,
                    'role' => 'worker',
                    // Sense servei (el portal antic en tenia un codi numèric): s'assigna a
                    // Empleats. '' i no el valor per defecte (DOMICILIARIA), que exigiria GPS.
                    'work_type' => '',
                    'active' => true,
                    'must_change_password' => false,
                    'created_at' => $o->created_at ?? now(),
                    'updated_at' => now(),
                ]);
                $creats++;
            }

            $mapa['per_id'][(int) $o->id] = $id;
            $mapa['per_email'][$email] = $id;
            if ($dni) {
                $mapa['per_dni'][$dni] = $id;
            }
        }

        $this->line("Treballadors: {$creats} creats · {$existents} ja existien (no es toquen).");

        return $mapa;
    }

    private function nomines($src, array $mapa): void
    {
        $creades = $existents = 0;
        $sensePersona = [];

        foreach ($src->table('nominas')->orderBy('id')->cursor() as $n) {
            $dni = strtoupper(trim((string) $n->dni));
            $userId = $mapa['per_dni'][$dni] ?? $mapa['per_id'][(int) $n->id_usuario] ?? null;
            if (! $userId) {
                $sensePersona[$dni ?: "id {$n->id_usuario}"] = true;
                continue;
            }

            $data = Carbon::parse($n->fecha_nomina);
            $fitxer = trim((string) $n->documento_name) . '.pdf';

            $ja = DB::table('payrolls')->where('user_id', $userId)
                ->where('year', $data->year)->where('month', $data->month)
                ->where('file_name', $fitxer)->exists();
            if ($ja) {
                $existents++;
                continue;
            }

            // L'app desa els PDF com a data URL (FileReader.readAsDataURL); l'origen, base64 pur.
            $pdf = (string) $n->documento_pdf;
            if (! str_starts_with($pdf, 'data:')) {
                $pdf = 'data:application/pdf;base64,' . $pdf;
            }

            DB::table('payrolls')->insert([
                'user_id' => $userId,
                'title' => $fitxer,
                'month' => $data->month,
                'year' => $data->year,
                'payroll_base64' => $pdf,
                'file_name' => $fitxer,
                'created_at' => $n->created_at ?? now(),
                'updated_at' => now(),
            ]);
            $creades++;
        }

        $this->line("Nòmines: {$creades} creades · {$existents} ja existien.");
        if ($sensePersona) {
            $this->warn(count($sensePersona) . " persona(es) amb nòmines però sense usuari a l'origen: les seves nòmines NO s'importen.");
        }
    }

    private function fitxatges($src, array $mapa): void
    {
        $creats = $existents = $oberts = $invalids = 0;

        foreach ($src->table('registro_de_entradas')->orderBy('id')->cursor() as $r) {
            $userId = $mapa['per_email'][strtolower(trim((string) $r->UserName))] ?? null;
            if (! $userId || ! $r->Fecha_Entrada || ! preg_match('/^\d{1,2}:\d{2}$/', (string) $r->Hora_Entrada)) {
                $invalids++;
                continue;
            }

            // Hora local de l'origen → UTC, que és com l'app desa start_time/end_time.
            $inici = Carbon::parse("{$r->Fecha_Entrada} {$r->Hora_Entrada}", self::TZ);
            $iniciUtc = $inici->copy()->utc()->toDateTimeString();

            if (DB::table('work_logs')->where('user_id', $userId)->where('start_time', $iniciUtc)->exists()) {
                $existents++;
                continue;
            }

            $fi = null;
            if ($r->Fecha_Salida && preg_match('/^\d{1,2}:\d{2}$/', (string) $r->Hora_Salida)) {
                $fi = Carbon::parse("{$r->Fecha_Salida} {$r->Hora_Salida}", self::TZ);
                if ($fi->lte($inici)) {
                    $fi = null; // sortida incoherent: queda oberta per revisar
                }
            }

            $hores = $fi ? round($inici->diffInMinutes($fi) / 60, 2) : null;

            DB::table('work_logs')->insert([
                'user_id' => $userId,
                'date' => $inici->toDateString(),
                'start_time' => $iniciUtc,
                'end_time' => $fi?->copy()->utc()->toDateTimeString(),
                'hours_worked' => $hores,
                'effective_hours' => $hores,
                'total_hours_worked' => $hores ?? 0, // NOT NULL: 0 mentre la jornada és oberta
                // Registre històric del portal antic: vàlid si té sortida; si no, a revisar.
                'status' => $fi ? 'approved' : 'pending',
                'created_at' => $r->created_at ?? now(),
                'updated_at' => now(),
            ]);
            $creats++;
            if (! $fi) {
                $oberts++;
            }
        }

        $this->line("Fitxatges: {$creats} creats ({$oberts} sense sortida, pendents de revisar) · {$existents} ja existien · {$invalids} descartats.");
    }
}
