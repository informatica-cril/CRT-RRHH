<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        // Les pàgines Inertia refereixen el manifest de Vite (public/build), que no
        // existeix a l'entorn de tests: sense això, tota ruta que renderitzi una
        // pàgina retorna 500 i els tests menteixen sobre el que proven.
        $this->withoutVite();
    }
}
