<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Percentatge d'hores complementàries pactat (art. 12.5 ET; el conveni XII
            // el permet fins al 50%). Per defecte el 30% de l'ET. El canvi requereix
            // preavís de 7 dies: es desa el pendent i la data d'efecte.
            $table->decimal('complementary_ratio', 4, 2)->default(0.30)->after('pacte_complementaries');
            $table->decimal('complementary_ratio_pending', 4, 2)->nullable()->after('complementary_ratio');
            $table->date('complementary_ratio_pending_from')->nullable()->after('complementary_ratio_pending');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['complementary_ratio', 'complementary_ratio_pending', 'complementary_ratio_pending_from']);
        });
    }
};
