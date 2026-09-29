<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('municipal_assignments', function (Blueprint $table) {
            $table->renameColumn('municipality_name', 'municipalities');
        });

        Schema::table('municipal_assignments', function (Blueprint $table) {
            $table->text('municipalities')->nullable()->change();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
        });

        // Convert existing data to JSON format
        $assignments = \DB::table('municipal_assignments')->get();
        foreach ($assignments as $a) {
            if ($a->municipalities && !str_starts_with($a->municipalities, '[')) {
                \DB::table('municipal_assignments')
                    ->where('id', $a->id)
                    ->update(['municipalities' => json_encode([$a->municipalities])]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('municipal_assignments', function (Blueprint $table) {
            $table->renameColumn('municipalities', 'municipality_name');
            $table->string('municipality_name')->change();
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'notes']);
        });
    }
};
