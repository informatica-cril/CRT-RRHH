<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La pantalla de gestió ja permetia triar, document a document, si l'alta el demana
 * SIGNAT o només LLEGIT, però el camp no era columna i s'esborrava en desar el perfil.
 * Sense ell tot document exigia firma electrònica avançada, també els informatius.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('onboarding_profiles', 'doc_modes')) {
                $table->json('doc_modes')->nullable()->after('document_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('onboarding_profiles', 'doc_modes')) {
                $table->dropColumn('doc_modes');
            }
        });
    }
};
