<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every patient gets an own data key; the patient's clinical text is encrypted
 * with it. Ciphertext is longer than the text and is not JSON, so the encrypted
 * columns become plain text columns.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> table => columns that will hold ciphertext */
    private const COLUMNS = [
        'patients' => ['address', 'notes'],
        'appointments' => ['icd10_code', 'booking_details'],
        'documents' => ['title', 'original_filename'],
        'document_anonymizations' => ['redaction_report'],
        'ai_clinical_cases' => ['redaction_report'],
        'ai_recommendations' => ['response'],
        'measurements' => ['note'],
        'pain_points' => ['note'],
        'patient_comorbidities' => ['name'],
        'therapy_milestones' => ['goal'],
        'booking_verifications' => ['payload'],
    ];

    public function up(): void
    {
        // Kept apart from the clinical data on purpose: backups store the keys
        // in a separate, short-lived file (see BackupRunner), so deleting a
        // patient's key makes their records unreadable in older backups too.
        Schema::create('patient_keys', function (Blueprint $table) {
            $table->foreignId('patient_id')->primary()->constrained('patients')->cascadeOnUpdate()->cascadeOnDelete();
            $table->text('encrypted_key');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('erased_at')->nullable();
        });

        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    // Required fields stay required in validation; the column
                    // itself only has to hold whatever the cipher produces.
                    $blueprint->longText($column)->nullable()->change();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('patients', fn (Blueprint $table) => $table->dropColumn('erased_at'));
        Schema::dropIfExists('patient_keys');
        // Columns stay text: shrinking them back would cut off encrypted values.
    }
};
