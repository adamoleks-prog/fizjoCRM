<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pain_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('body_view', ['front', 'back']);
            // Percentages of the silhouette box, so the mark stays put whatever
            // size the drawing is rendered at.
            $table->decimal('position_x', 5, 2);
            $table->decimal('position_y', 5, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'appointment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pain_points');
    }
};
