<?php

namespace Tests\Feature;

use App\Models\ComiteHora;
use App\Models\ComiteMembre;
use App\Models\User;
use App\Services\ImportaHoresComite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Hores sindicals de l'Excel de RRHH al Comitè: qui no hi és es dona d'alta, cada fila és un ús
 * del crèdit amb l'estat de l'Excel, i tornar a importar no duplica res.
 */
class ImportaHoresComiteTest extends TestCase
{
    use RefreshDatabase;

    private function fila(string $nom, string $dia, int $minuts, string $estat = 'concedit', string $obs = 'Accions administratives', int $fila = 2): array
    {
        return ['full' => 'hores sindicals', 'fila' => $fila, 'nom' => $nom, 'motiu' => 'accions sindicals', 'codi' => 'accions sindicals',
            'ini' => $dia, 'fi' => $dia, 'estat' => $estat, 'hora_inici' => null, 'hora_fi' => null, 'minuts' => $minuts,
            'obs' => 'mensual', 'detall' => $obs];
    }

    private function persona(string $nom): User
    {
        return User::create(['name' => $nom, 'email' => 'p' . uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => 'worker', 'active' => true]);
    }

    public function test_dona_d_alta_els_membres_i_importa_les_hores_sense_duplicar(): void
    {
        $laia = $this->persona('Laia Tarruella');
        $merce = $this->persona('Mercè Tous');
        ComiteMembre::create(['user_id' => $merce->id, 'tipus_representacio' => 'comite', 'carrec' => 'secretari',
            'data_alta' => '2026-01-01', 'credit_hores_mensual' => 20]);
        $hr = User::create(['name' => 'RRHH', 'email' => 'h' . uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => 'hr', 'active' => true]);

        $files = [
            $this->fila('Tarruella Laia', '2026-05-26', 120),
            $this->fila('Tous Mercè', '2026-06-05', 120, 'concedit', 'Accions administratives', 3),
            $this->fila('Tous Mercè', '2026-06-05', 120, 'concedit', 'Accions administratives', 4),   // dos usos iguals el mateix dia
            $this->fila('Tous Mercè', '2026-06-10', 90, 'petició', 'Reunió', 5),
        ];
        $persones = [['nom' => 'Tarruella Laia', 'user_id' => $laia->id], ['nom' => 'Tous Mercè', 'user_id' => $merce->id]];
        $servei = app(ImportaHoresComite::class);

        $prev = $servei->previsualitza($files, $persones);
        $this->assertCount(4, $prev['registres']);
        $this->assertFalse(collect($prev['persones'])->firstWhere('nom', 'Tarruella Laia')['es_membre']);

        $fet = $servei->aplica($prev['registres'], [], $hr);
        $this->assertSame(4, $fet['hores_creades']);
        $this->assertSame(1, $fet['membres_nous']);

        $nou = ComiteMembre::where('user_id', $laia->id)->first();
        $this->assertSame('2026-05-26', $nou->data_alta->toDateString());
        $this->assertEquals(15, $nou->credit_hores_mensual);
        $this->assertEquals(20, ComiteMembre::where('user_id', $merce->id)->value('credit_hores_mensual'));   // el que hi era no es toca

        $this->assertSame(2, ComiteHora::where('user_id', $merce->id)->where('data', '2026-06-05')->count());
        $this->assertSame('validada', ComiteHora::where('user_id', $laia->id)->value('estat'));
        $this->assertSame($hr->id, ComiteHora::where('user_id', $laia->id)->value('validat_per'));
        $this->assertSame('pendent', ComiteHora::where('user_id', $merce->id)->where('data', '2026-06-10')->value('estat'));
        $this->assertSame(90, (int) ComiteHora::where('user_id', $merce->id)->where('data', '2026-06-10')->value('minuts'));
        $this->assertSame('Reunió', ComiteHora::where('data', '2026-06-10')->value('motiu'));   // la K, no «mensual»

        // Tornar a importar el mateix Excel: res de nou; si l'estat ha canviat, s'actualitza.
        $files[3]['estat'] = 'concedit';
        $fet2 = $servei->aplica($servei->previsualitza($files, $persones)['registres'], [], $hr);
        $this->assertSame(0, $fet2['hores_creades']);
        $this->assertSame(1, $fet2['hores_actualitzades']);
        $this->assertSame(4, ComiteHora::count());
        $this->assertSame('validada', ComiteHora::where('data', '2026-06-10')->value('estat'));
    }

    public function test_l_endpoint_d_aplicar_hi_passa_les_hores(): void
    {
        $laia = $this->persona('Laia Tarruella');
        Sanctum::actingAs(User::create(['name' => 'RRHH', 'email' => 'h' . uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => 'hr', 'active' => true]));
        $prev = app(ImportaHoresComite::class)->previsualitza([$this->fila('Tarruella Laia', '2026-05-26', 120)], [['nom' => 'Tarruella Laia', 'user_id' => $laia->id]]);

        $this->postJson('/api/v1/absences/importa-excel/aplica', ['registres' => [], 'comite' => $prev['registres']])
            ->assertOk()->assertJsonPath('hores_creades', 1)->assertJsonPath('membres_nous', 1);
    }
}
