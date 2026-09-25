<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consent wordings each physiotherapist keeps for their practice.
        Schema::create('consent_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->longText('body');
            $table->timestamps();
            $table->softDeletes();

            $table->index('operator_id');
        });

        // Evidence of a signature: which wording (by hash — the full text is in
        // the signed PDF), when, on which device, in whose presence.
        Schema::create('signed_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('consent_template_id')->nullable()->constrained('consent_templates')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('witnessed_by_user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('body_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();

            $table->index('patient_id');
        });

        Schema::table('patient_access_logs', function (Blueprint $table) {
            $table->enum('action', [
                'viewed', 'updated', 'document_downloaded', 'sent_to_ai',
                'visit_card_downloaded', 'visit_card_sent', 'consent_signed',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('patient_access_logs', function (Blueprint $table) {
            $table->enum('action', [
                'viewed', 'updated', 'document_downloaded', 'sent_to_ai',
                'visit_card_downloaded', 'visit_card_sent',
            ])->change();
        });

        Schema::dropIfExists('signed_consents');
        Schema::dropIfExists('consent_templates');
    }
};
