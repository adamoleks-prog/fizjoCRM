<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('type', ['referral', 'imaging', 'questionnaire', 'consent', 'other'])
                ->default('other')->after('appointment_id');
            $table->string('title')->nullable()->after('type');
            $table->enum('text_extraction_status', ['pending', 'from_pdf_layer', 'from_ocr', 'no_text', 'failed'])
                ->default('pending')->after('ocr_text');
            $table->timestamp('text_extracted_at')->nullable()->after('text_extraction_status');

            $table->index(['patient_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['patient_id', 'type']);
            $table->dropColumn(['type', 'title', 'text_extraction_status', 'text_extracted_at']);
        });
    }
};
