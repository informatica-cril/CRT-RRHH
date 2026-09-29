<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorization_codes', function (Blueprint $table) {
            $table->index('user_id', 'auth_codes_user_id_idx');
            $table->index('valid_to', 'auth_codes_valid_to_idx');
            $table->index('used', 'auth_codes_used_idx');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->index(['user_id', 'year'], 'payrolls_user_year_idx');
        });

        Schema::table('document_signatures', function (Blueprint $table) {
            // (document_id, user_id) unique already covers document_id-first queries.
            // Add a user_id-only index for getSignaturesByUser lookups.
            $table->index('user_id', 'doc_sigs_user_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('authorization_codes', function (Blueprint $table) {
            $table->dropIndex('auth_codes_user_id_idx');
            $table->dropIndex('auth_codes_valid_to_idx');
            $table->dropIndex('auth_codes_used_idx');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex('payrolls_user_year_idx');
        });

        Schema::table('document_signatures', function (Blueprint $table) {
            $table->dropIndex('doc_sigs_user_id_idx');
        });
    }
};
