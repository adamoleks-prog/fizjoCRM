<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Settings an administrator changes from the panel (mail server, SMS
        // gateway) — secrets are stored encrypted by AppSettings.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // Shown to patients: in reminders and on the printed visit card.
            $table->string('practice_name')->nullable()->after('competency_profile');
            $table->string('practice_address')->nullable()->after('practice_name');
            $table->string('practice_phone', 32)->nullable()->after('practice_address');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('reminders_enabled')->default(true)->after('email');
        });

        // One row per attempt — enough to answer "did the patient get a reminder?"
        // without storing the message itself.
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('channel', ['sms', 'email']);
            $table->enum('status', ['sent', 'failed']);
            $table->string('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('appointment_id');
        });

        Schema::table('patient_access_logs', function (Blueprint $table) {
            $table->enum('action', [
                'viewed', 'updated', 'document_downloaded', 'sent_to_ai',
                'visit_card_downloaded', 'visit_card_sent',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('patient_access_logs', function (Blueprint $table) {
            $table->enum('action', ['viewed', 'updated', 'document_downloaded', 'sent_to_ai'])->change();
        });

        Schema::dropIfExists('appointment_reminders');

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('reminders_enabled');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['practice_name', 'practice_address', 'practice_phone']);
        });

        Schema::dropIfExists('settings');
    }
};
