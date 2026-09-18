<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The operator's own library of repeatable measurements.
        Schema::create('measurement_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->enum('type', ['boolean', 'scale', 'numeric', 'bilateral']);
            $table->string('unit', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['operator_id', 'name']);
        });

        // A single result recorded during a visit.
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnUpdate()->cascadeOnDelete();
            // Restricted rather than cascading: results must never disappear because
            // the definition was removed — templates are soft deleted instead.
            $table->foreignId('measurement_template_id')->constrained('measurement_templates')
                ->cascadeOnUpdate()->restrictOnDelete();
            // value_left also holds the single value of numeric and scale measurements.
            $table->decimal('value_left', 8, 2)->nullable();
            $table->decimal('value_right', 8, 2)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'appointment_id']);
            $table->index('measurement_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
        Schema::dropIfExists('measurement_templates');
    }
};
