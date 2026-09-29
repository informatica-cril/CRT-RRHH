<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'worker'])->default('worker');
            $table->string('work_type')->default('DOMICILIARIA'); // DOMICILIARIA or AMBULATORIA
            $table->string('job_profile')->default('Fisioterapeuta'); // Fisioterapeuta, Logopeda, Administracion, Gerencia
            $table->string('postal_code_assigned')->nullable();
            $table->unsignedBigInteger('work_schedule_id')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('privacy_consent')->default(false);
            $table->date('seniority_date')->nullable();
            $table->unsignedBigInteger('onboarding_profile_id')->nullable();
            $table->boolean('onboarding_completed')->default(false);
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('work_schedule_id')
                  ->references('id')->on('work_schedules')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
