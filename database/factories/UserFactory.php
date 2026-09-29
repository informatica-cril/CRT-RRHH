<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory d'usuaris per als TESTS (el directori database/factories no existia i tots
 * els tests de sèrie — Auth, Profile — morien amb "UserFactory not found").
 * Valors per defecte alineats amb l'app: role worker, actiu, relació sense classificar
 * (NULL = laboral, el mateix comportament que els usuaris reals pre-backfill).
 *
 * @extends Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => \Illuminate\Support\Facades\Hash::make('password'), // BCRYPT_ROUNDS=4 als tests: barat
            'remember_token' => Str::random(10),
            'role' => 'worker',
            'active' => true,
            'relacio' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    public function autonom(float $disponibilitat = 12.5): static
    {
        return $this->state(fn () => ['relacio' => 'autonom', 'disponibilitat_setmanal' => $disponibilitat]);
    }
}
