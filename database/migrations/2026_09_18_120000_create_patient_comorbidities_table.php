<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comorbidities belong to the patient rather than a single visit — they are
     * background that holds across the whole treatment, not an observation made
     * on one day.
     */
    public function up(): void
    {
        Schema::create('patient_comorbidities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 200);
            $table->enum('kind', ['active', 'chronic', 'past']);
            $table->timestamps();

            $table->index(['operator_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_comorbidities');
    }
};
