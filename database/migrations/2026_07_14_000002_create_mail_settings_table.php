<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->string('mailer')->default('smtp'); // smtp | log
            $table->string('host')->nullable();
            $table->integer('port')->default(587);
            $table->string('encryption')->nullable()->default('tls'); // tls | ssl | null
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // xifrada (cast 'encrypted')
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->timestamp('last_test_at')->nullable();
            $table->string('last_test_status')->nullable(); // ok | error
            $table->timestamps();
        });

        // Registre inicial a partir de la configuració actual (.env)
        DB::table('mail_settings')->insert([
            'mailer' => env('MAIL_MAILER', 'smtp'),
            'host' => env('MAIL_HOST'),
            'port' => (int) env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => null,
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME', 'CRT RRHH'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
