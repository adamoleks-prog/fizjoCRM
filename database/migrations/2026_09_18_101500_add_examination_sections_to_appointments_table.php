<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sections of the examination card, modelled after the structure the national
     * chamber's guidelines use. Kept as columns on the visit rather than a separate
     * record table — they are plain 1:1 attributes of a single visit.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->text('interview')->nullable()->after('icd10_code');
            $table->text('detailed_examination')->nullable()->after('interview');
            $table->text('conclusions')->nullable()->after('detailed_examination');
            // Stays in the record but is never printed on the patient's copy.
            $table->text('internal_notes')->nullable()->after('treatment_notes');
            $table->text('patient_recommendations')->nullable()->after('internal_notes');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'interview',
                'detailed_examination',
                'conclusions',
                'internal_notes',
                'patient_recommendations',
            ]);
        });
    }
};
