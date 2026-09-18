<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Stable per-patient shift applied to every date leaving the system.
            // Never sent anywhere — without it the shifted dates cannot be undone.
            $table->smallInteger('date_offset_days')->nullable()->after('notes');
        });

        Schema::create('document_anonymizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnUpdate()->cascadeOnDelete();

            $table->longText('anonymized_text');
            // Counts per category only. Storing the removed values here would put a
            // second copy of the identifiers somewhere less protected than the record.
            $table->json('redaction_report');
            $table->unsignedSmallInteger('suspicion_count')->default(0);
            $table->enum('confidence', ['high', 'low'])->default('low');
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('draft');

            $table->foreignId('approved_by_user_id')->nullable()
                ->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedSmallInteger('manual_edits')->default(0);
            $table->string('ruleset_version', 20);

            $table->timestamps();

            $table->index(['operator_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_anonymizations');

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('date_offset_days');
        });
    }
};
