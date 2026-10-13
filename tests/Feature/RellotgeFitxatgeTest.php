<?php

namespace Tests\Feature;

use App\Services\WorkLogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** L'hora del fitxatge no pregunta a internet a cada fitxatge (la sortida trigava). */
class RellotgeFitxatgeTest extends TestCase
{
    public function test_l_hora_externa_es_consulta_un_cop_i_es_reutilitza(): void
    {
        Cache::forget('rellotge_desfas_s');
        Http::fake(['worldtimeapi.org/*' => Http::response(['datetime' => now('Europe/Madrid')->addSeconds(30)->toIso8601String()])]);
        $s = app(WorkLogService::class);

        $h1 = $s->getAccurateTime();
        $s->getAccurateTime();
        $s->getAccurateTime();

        Http::assertSentCount(1);                                   // una sola consulta, no una per fitxatge
        $this->assertSame('Europe/Madrid', $h1->getTimezone()->getName());
        $this->assertEqualsWithDelta(now()->addSeconds(30)->getTimestamp(), $h1->getTimestamp(), 3);
    }

    public function test_si_l_api_no_respon_es_fa_servir_el_rellotge_del_servidor(): void
    {
        Cache::forget('rellotge_desfas_s');
        Http::fake(['worldtimeapi.org/*' => Http::response('', 500)]);

        $h = app(WorkLogService::class)->getAccurateTime();
        $this->assertEqualsWithDelta(now()->getTimestamp(), $h->getTimestamp(), 3);
    }
}
