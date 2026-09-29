<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('break_override')->nullable()->default(null)
                ->comment('null = usa config global; true = força pausa activada; false = desactiva pausa')
                ->after('work_schedule_id');
            $table->time('break_override_time')->nullable()->default(null)
                ->comment('Hora personalitzada de pausa per aquest treballador (null = usa config global)')
                ->after('break_override');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['break_override', 'break_override_time']);
        });
    }
};
