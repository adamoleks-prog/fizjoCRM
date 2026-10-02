<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null means the practice default from config/appointments.php.
            $table->unsignedSmallInteger('slot_minutes')->nullable()->after('practice_phone');
        });

        // The weekly pattern, repeated week after week. Several rows on one weekday
        // describe a split day (e.g. 8–12 and 14–18).
        Schema::create('work_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO-8601: 1 = Monday … 7 = Sunday
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index(['operator_id', 'weekday']);
        });

        // A single date that differs from the pattern. Any row for a date replaces
        // the pattern for that whole day; a row without times marks a day off.
        Schema::create('work_hour_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('note', 120)->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_hour_exceptions');
        Schema::dropIfExists('work_hours');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('slot_minutes');
        });
    }
};
