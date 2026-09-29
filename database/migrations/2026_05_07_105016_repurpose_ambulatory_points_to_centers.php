<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Limpiamos los datos residuales ya que la tabla tenía datos por usuario
        DB::table('ambulatory_points')->truncate();

        // 1. Modificar la tabla antigua
        // sqlite (tests): no sap fer dropForeign ni dropColumn múltiple — la FK cau
        // sola en refer la taula, i les columnes es treuen d'una en una. A MySQL,
        // exactament el que feia la versió original.
        if (DB::getDriverName() === 'sqlite') {
            foreach (['user_id', 'lat', 'lng', 'radius'] as $col) {
                Schema::table('ambulatory_points', fn (Blueprint $t) => $t->dropColumn($col));
            }
        } else {
            Schema::table('ambulatory_points', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn(['user_id', 'lat', 'lng', 'radius']);
            });
        }

        // 2. Renombrar a ambulatory_centers
        Schema::rename('ambulatory_points', 'ambulatory_centers');

        // 3. Añadir FK a work_locations
        Schema::table('work_locations', function (Blueprint $table) {
            $table->foreignId('ambulatory_center_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('ambulatory_centers')
                  ->nullOnDelete();
        });

        // 4. Crear tabla pivot para asignar usuarios enteros a un centro
        Schema::create('ambulatory_center_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ambulatory_center_id')->constrained('ambulatory_centers')->cascadeOnDelete();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ambulatory_center_user');

        Schema::table('work_locations', function (Blueprint $table) {
            $table->dropForeign(['ambulatory_center_id']);
            $table->dropColumn('ambulatory_center_id');
        });

        Schema::rename('ambulatory_centers', 'ambulatory_points');

        Schema::table('ambulatory_points', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->decimal('lat', 10, 7)->default(0);
            $table->decimal('lng', 10, 7)->default(0);
            $table->integer('radius')->default(100);
        });
    }
};
