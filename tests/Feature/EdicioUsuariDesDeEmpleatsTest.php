<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * L'edició d'una fitxa des d'Empleats fa PUT /users/{id} amb { ...usuari de la llista, ...form }.
 * Aquest test envia exactament aquesta forma de payload.
 */
class EdicioUsuariDesDeEmpleatsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('x'), 'role' => 'admin', 'active' => true]);
    }

    private function eva(array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'Eva Prova', 'email' => 'eva@test.local', 'password' => bcrypt('x'),
            'role' => 'worker', 'dni' => '12345678Z', 'work_type' => 'DOMICILIARIA',
            'job_profile' => 'Fisioterapeuta', 'active' => true, 'seniority_date' => '2020-03-01',
        ], $extra));
    }

    /** El mateix que fa saveUser(): l'usuari de GET /users barrejat amb el formulari. */
    private function payloadFrontend(User $u, array $form = []): array
    {
        $llista = collect($this->getJson('/api/v1/users')->assertOk()->json())->firstWhere('id', $u->id);

        return array_merge($llista, [
            'role' => $llista['role'] ?: 'worker', 'entitat' => 'CRIL', 'name' => $llista['name'],
            'email' => $llista['email'], 'dni' => $llista['dni'] ?? '', 'device_phone' => $llista['device_phone'] ?? '',
            'pacte_complementaries' => false, 'work_type' => $llista['work_type'] ?: 'DOMICILIARIA',
            'job_profile' => $llista['job_profile'] ?: 'Fisioterapeuta',
            'postal_code_assigned' => $llista['postal_code_assigned'], 'work_schedule_id' => $llista['work_schedule_id'],
            // Com openEditModal(): la data ISO (UTC) es llegeix en hora de Madrid.
            'seniority_date' => $llista['seniority_date']
                ? \Carbon\Carbon::parse($llista['seniority_date'])->setTimezone('Europe/Madrid')->toDateString() : '',
            'specialties' => [], 'lots' => [], 'departments' => [], 'domi_username' => '',
            'domi_provisioned_at' => null, 'second_factor' => 'dispositiu', 'totp_confirmed' => false,
        ], $form);
    }

    public function test_desar_la_fitxa_tal_com_l_envia_el_frontend(): void
    {
        Sanctum::actingAs($this->admin());
        $eva = $this->eva();

        $r = $this->putJson('/api/v1/users/' . $eva->id, $this->payloadFrontend($eva, ['name' => 'Eva Editada']));
        $r->assertOk();
        $this->assertSame('Eva Editada', $eva->fresh()->name);
        $this->assertSame('2020-03-01', $eva->fresh()->seniority_date->toDateString(), "desar la fitxa no pot moure la data d'alta");
    }

    /**
     * Causa del 500 a producció: `dni` és varchar(20) i la validació no tenia límit. MySQL
     * en mode estricte rebutja el valor («Data too long») i l'API responia 500 sense dir per
     * què; SQLite no aplica longituds i els tests no ho veien. Ara és un 422 amb el motiu.
     */
    public function test_un_dni_massa_llarg_es_un_422_amb_el_motiu_i_no_un_500(): void
    {
        Sanctum::actingAs($this->admin());
        $eva = $this->eva();

        $this->putJson('/api/v1/users/' . $eva->id, $this->payloadFrontend($eva, ['dni' => str_repeat('X', 21)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dni'])
            ->assertJsonPath('errors.dni.0', 'El DNI / NIE no pot tenir més de 20 caràcters.');
        $this->assertSame('12345678Z', $eva->fresh()->dni);
    }

    public function test_l_alta_tambe_limita_el_dni(): void
    {
        Sanctum::actingAs($this->admin());
        $this->postJson('/api/v1/users', ['name' => 'Nova', 'email' => 'nova@test.local',
            'password' => 'secret12', 'dni' => str_repeat('X', 21)])
            ->assertStatus(422)->assertJsonValidationErrors(['dni']);
    }

    /** El modal d'horari només envia l'horari (abans, l'usuari sencer feia perdre un dia a la data d'alta). */
    public function test_el_canvi_d_horari_no_mou_la_data_d_alta(): void
    {
        Sanctum::actingAs($this->admin());
        $ws = \App\Models\WorkSchedule::create(['name' => 'P', 'total_hours_weekly' => 20, 'days' => []]);
        $eva = $this->eva();
        $this->putJson('/api/v1/users/' . $eva->id, ['id' => $eva->id, 'work_schedule_id' => $ws->id])->assertOk();
        $this->assertSame($ws->id, (int) $eva->fresh()->work_schedule_id);
        $this->assertSame('2020-03-01', $eva->fresh()->seniority_date->toDateString());
    }
}
