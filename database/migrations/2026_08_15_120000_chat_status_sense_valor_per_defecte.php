<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('chat_status')->nullable()->default(null)->change();
        });

        DB::table('users')->where('chat_status', 'offline')->update(['chat_status' => null]);
    }

    public function down(): void
    {
        DB::table('users')->whereNull('chat_status')->update(['chat_status' => 'offline']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('chat_status')->nullable(false)->default('offline')->change();
        });
    }
};
