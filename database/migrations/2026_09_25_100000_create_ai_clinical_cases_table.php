<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The anonymised clinical picture of one therapy cycle, reviewed by a person
        // before it may be sent for a suggestion. Same review columns as
        // document_anonymizations — both go through ReviewGate.
        Schema::create('ai_clinical_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('therapy_cycle_id')->unique()->constrained('therapy_cycles')->cascadeOnUpdate()->cascadeOnDelete();

            $table->longText('anonymized_text');
            $table->longText('generated_text');
            $table->string('source_hash', 64);
            $table->json('redaction_report');
            $table->unsignedSmallInteger('suspicion_count')->default(0);
            $table->enum('confidence', ['high', 'low'])->default('low');
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->unsignedSmallInteger('manual_edits')->default(0);
            $table->string('ruleset_version', 20);

            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index('operator_id');
        });

        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('therapy_cycle_id')->constrained('therapy_cycles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('ai_clinical_case_id')->constrained('ai_clinical_cases')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();

            $table->enum('status', ['queued', 'completed', 'failed'])->default('queued');

            // What was asked and how — enough to reconstruct the request later
            // without keeping a second copy of the medical text.
            $table->string('model');
            $table->string('provider')->nullable();
            $table->string('prompt_version', 20);
            $table->string('schema_version', 20);
            $table->string('case_text_hash', 64);

            $table->json('response')->nullable();
            $table->string('failure_reason')->nullable();

            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->decimal('cost', 10, 6)->nullable();

            $table->enum('decision', ['pending', 'useful', 'rejected'])->default('pending');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamps();

            $table->index(['operator_id', 'created_at']);
            $table->index('therapy_cycle_id');
        });

        Schema::table('users', function (Blueprint $table) {
            // Courses and methods the physiotherapist is qualified in; fed into the
            // prompt so suggestions stay within what this person may actually do.
            $table->text('competency_profile')->nullable()->after('email');
        });

        Schema::table('patient_access_logs', function (Blueprint $table) {
            $table->enum('action', ['viewed', 'updated', 'document_downloaded', 'sent_to_ai'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('patient_access_logs', function (Blueprint $table) {
            $table->enum('action', ['viewed', 'updated', 'document_downloaded'])->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('competency_profile');
        });

        Schema::dropIfExists('ai_recommendations');
        Schema::dropIfExists('ai_clinical_cases');
    }
};
