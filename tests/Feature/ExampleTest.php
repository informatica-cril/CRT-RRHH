<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // Comportament real de l'app: l'arrel redirigeix al dashboard (i el
        // middleware auth, si no hi ha sessio, d'alla cap al login).
        $response->assertRedirect(route('dashboard'));
    }
}
