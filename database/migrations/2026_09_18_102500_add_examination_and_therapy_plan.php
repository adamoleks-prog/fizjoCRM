<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->text('examination')->nullable()->after('interview');
        });

        // The plan spans the whole course of treatment, not a single visit.
        Schema::table('therapy_cycles', function (Blueprint $table) {
            $table->text('therapy_plan')->nullable()->after('name');
        });

        Schema::create('therapy_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('therapy_cycle_id')->constrained('therapy_cycles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('goal', 500);
            $table->enum('horizon', ['short', 'medium', 'long']);
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'therapy_cycle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapy_milestones');

        Schema::table('therapy_cycles', function (Blueprint $table) {
            $table->dropColumn('therapy_plan');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('examination');
        });
    }
};
