<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // "pending" — booked online by a new patient, waiting for the
            // physiotherapist. It already holds the slot.
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show', 'pending'])->default('scheduled')->change();
            $table->enum('source', ['panel', 'online'])->default('panel')->after('status');
            // Lets the patient cancel from the link in the confirmation SMS.
            $table->string('cancel_token', 64)->nullable()->unique()->after('source');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->enum('source', ['panel', 'online'])->default('panel')->after('notes');
            $table->timestamp('online_consent_at')->nullable()->after('source');
        });

        // Per-physiotherapist booking rules; online booking is off until turned on.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('online_booking_enabled')->default(false)->after('slot_minutes');
            $table->unsignedSmallInteger('booking_visit_minutes')->nullable()->after('online_booking_enabled');
            $table->unsignedSmallInteger('booking_first_visit_minutes')->nullable()->after('booking_visit_minutes');
            $table->unsignedSmallInteger('booking_min_notice_hours')->default(12)->after('booking_first_visit_minutes');
            $table->unsignedSmallInteger('booking_days_ahead')->default(30)->after('booking_min_notice_hours');
            $table->unsignedSmallInteger('booking_cancel_hours')->default(24)->after('booking_days_ahead');
        });

        // One SMS code per booking attempt. Only a hash of the code is kept.
        Schema::create('booking_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('payload');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['phone', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_verifications');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'online_booking_enabled', 'booking_visit_minutes', 'booking_first_visit_minutes',
                'booking_min_notice_hours', 'booking_days_ahead', 'booking_cancel_hours',
            ]);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['source', 'online_consent_at']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique(['cancel_token']);
            $table->dropColumn(['source', 'cancel_token']);
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled')->change();
        });
    }
};
