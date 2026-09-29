<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('profile_id');
            $table->integer('current_step')->default(0);
            $table->boolean('completed')->default(false);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->json('steps'); // Array of {document_id, viewed_at, signed_at}
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('profile_id')->references('id')->on('onboarding_profiles')->onDelete('restrict');

            $table->unique('user_id'); // One onboarding status per user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_statuses');
    }
};
