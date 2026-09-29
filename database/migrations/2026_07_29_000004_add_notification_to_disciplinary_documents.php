<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comunicació i acusament DINS de l'app: el document signat es notifica al treballador al seu
 * portal (notificat_ts) i aquest signa l'acusament de recepció in-app (acus_ts + IP). L'acusament
 * acredita NOMÉS la recepció, no la conformitat — com el justificant en paper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disciplinary_documents', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('professional')->constrained('users')->nullOnDelete();
            $table->timestamp('notificat_ts')->nullable()->after('signat_ts');
            $table->timestamp('acus_ts')->nullable()->after('notificat_ts');
            $table->string('acus_ip', 64)->nullable()->after('acus_ts');
        });
    }

    public function down(): void
    {
        Schema::table('disciplinary_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['notificat_ts', 'acus_ts', 'acus_ip']);
        });
    }
};
