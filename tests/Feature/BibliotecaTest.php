<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Documentació: tothom la consulta; RRHH la gestiona i hi importa un ZIP amb carpetes. */
class BibliotecaTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $rol): User
    {
        return User::create(['name' => 'P ' . $rol, 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => $rol, 'active' => true]);
    }

    private function zip(array $entrades): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bib') . '.zip';
        $z = new \ZipArchive();
        $z->open($path, \ZipArchive::CREATE);
        foreach ($entrades as $nom => $contingut) $z->addFromString($nom, $contingut);
        $z->close();

        return new UploadedFile($path, 'documents.zip', 'application/zip', null, true);
    }

    public function test_importa_un_zip_per_carpetes_i_tothom_ho_pot_descarregar(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->usuari('hr'));
        $r = $this->post('/api/v1/biblioteca/importa-zip', ['zip' => $this->zip([
            'portal/public/documents/PLA_IGUALTAT/Política de igualdad.pdf' => '%PDF-1.4 a',
            'portal/public/documents/FORMACIO PPRRLL_COVID/Dossier COVID.pdf' => '%PDF-1.4 b',
            'portal/public/documents/FORMACIO PPRRLL_COVID/script.exe' => 'no',
            'arrel.png' => 'png',
        ])], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(3, $r->json('fitxers'));
        $this->assertCount(1, $r->json('omesos'));   // l'.exe no s'accepta
        $carpetes = collect($r->json('biblioteca'))->pluck('nom')->sort()->values()->all();
        $this->assertSame(['FORMACIO PPRRLL_COVID', 'General', 'PLA_IGUALTAT'], $carpetes);

        // Tornar a importar el mateix ZIP no duplica res.
        $this->post('/api/v1/biblioteca/importa-zip', ['zip' => $this->zip([
            'portal/public/documents/PLA_IGUALTAT/Política de igualdad.pdf' => '%PDF-1.4 a',
        ])], ['Accept' => 'application/json'])->assertOk()->assertJson(['fitxers' => 0]);

        // Un treballador la veu i descarrega el fitxer amb el nom original.
        Sanctum::actingAs($this->usuari('worker'));
        $llista = $this->getJson('/api/v1/biblioteca')->assertOk()->json();
        $fitxer = collect($llista)->firstWhere('nom', 'PLA_IGUALTAT')['fitxers'][0];
        $this->assertSame('Política de igualdad.pdf', $fitxer['nom']);
        $this->assertArrayNotHasKey('path', $fitxer);
        $this->get("/api/v1/biblioteca/fitxers/{$fitxer['id']}")->assertOk();

        // I no la pot gestionar.
        $this->postJson('/api/v1/biblioteca/carpetes', ['nom' => 'Meva'])->assertForbidden();
        $this->deleteJson("/api/v1/biblioteca/fitxers/{$fitxer['id']}")->assertForbidden();
    }

    public function test_rrhh_crea_carpeta_hi_puja_fitxers_i_els_esborra(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->usuari('admin'));
        $c = $this->postJson('/api/v1/biblioteca/carpetes', ['nom' => 'Protocols'])->assertCreated()->json('id');
        $r = $this->post("/api/v1/biblioteca/carpetes/{$c}/fitxers", ['fitxers' => [
            UploadedFile::fake()->create('protocol.pdf', 50, 'application/pdf'),
            UploadedFile::fake()->create('taula.xlsx', 20),
        ]], ['Accept' => 'application/json'])->assertOk();
        $this->assertCount(2, $r->json('fitxers'));

        $this->post("/api/v1/biblioteca/carpetes/{$c}/fitxers", ['fitxers' => [UploadedFile::fake()->create('virus.exe', 5)]],
            ['Accept' => 'application/json'])->assertStatus(422);

        $this->deleteJson("/api/v1/biblioteca/carpetes/{$c}")->assertOk();
        $this->assertSame([], $this->getJson('/api/v1/biblioteca')->json());
        $this->assertSame([], Storage::disk('local')->allFiles('biblioteca'));
    }
}
