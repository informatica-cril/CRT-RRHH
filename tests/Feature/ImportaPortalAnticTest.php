<?php

namespace Tests\Feature;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Importació de les nòmines del portal antic: només les que falten, sense signar. */
class ImportaPortalAnticTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?string $dni = null): User
    {
        return User::create([
            'name' => 'Persona ' . uniqid(), 'email' => uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => $role, 'active' => true, 'dni' => $dni,
        ]);
    }

    private function pdf(string $contingut): string
    {
        return base64_encode("%PDF-1.4\n" . $contingut);
    }

    private function envia(array $nomines)
    {
        return $this->postJson('/api/v1/payroll-files/importa-portal-antic', ['nomines' => $nomines]);
    }

    public function test_importa_les_que_falten_i_salta_les_que_ja_hi_son(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $w = $this->user('worker', '01234567A');
        // Ja pujada a l'app, amb el prefix data: (format de la primera migració).
        Payroll::create(['user_id' => $w->id, 'title' => 'Nòmina Juny 2025', 'month' => '06', 'year' => 2025,
            'payroll_base64' => 'data:application/pdf;base64,' . $this->pdf('juny'), 'signed_at' => now()]);

        $this->envia([
            ['dni' => '01234567A', 'data' => '2025-06-30 00:00:00', 'pdf' => $this->pdf('juny')],
            ['dni' => '1234567a', 'data' => '2025-06-30 00:00:00', 'pdf' => $this->pdf('paga extra'), 'nom_fitxer' => 'x.pdf'],
            ['dni' => '01234567A', 'data' => '2025-06-30 00:00:00', 'pdf' => $this->pdf('paga extra')], // repetida al fitxer
            ['dni' => '99999999Z', 'data' => '2025-07-31 00:00:00', 'pdf' => $this->pdf('altra')],
            ['dni' => '01234567A', 'data' => '2025-07-31 00:00:00', 'pdf' => base64_encode('no és un pdf')],
        ])->assertOk()
            ->assertJsonPath('compte', ['importada' => 1, 'ja_hi_era' => 2, 'sense_persona' => 1, 'pdf_no_valid' => 1])
            ->assertJsonPath('resultats', ['ja_hi_era', 'importada', 'ja_hi_era', 'sense_persona', 'pdf_no_valid']);

        $nova = Payroll::where('user_id', $w->id)->whereNull('signed_at')->sole();
        $this->assertSame('06', $nova->month);
        $this->assertSame(2025, (int) $nova->year);
        $this->assertSame('Nòmina Juny 2025 (2n document)', $nova->title);
        $this->assertNull($nova->viewed_at);
        $this->assertStringStartsWith('JVBER', $nova->payroll_base64);
    }

    public function test_nomes_admin(): void
    {
        $w = $this->user('worker', '11111111H');
        Sanctum::actingAs($w);

        $this->envia([['dni' => '11111111H', 'data' => '2025-01-31', 'pdf' => $this->pdf('a')]])->assertForbidden();
        $this->assertSame(0, Payroll::count());
    }
}
