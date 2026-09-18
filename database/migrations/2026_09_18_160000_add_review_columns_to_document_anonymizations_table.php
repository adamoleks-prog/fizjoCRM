<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_anonymizations', function (Blueprint $table) {
            // What the engine produced, kept apart from what the reviewer finally
            // approved — the difference between the two is the measure of how much
            // the filter missed.
            $table->longText('generated_text')->nullable()->after('anonymized_text');

            // Hash of the source text the draft was made from; a different hash later
            // means the document was re-read and the draft no longer matches it.
            $table->string('source_hash', 64)->nullable()->after('generated_text');

            // One reviewed version per document; regenerating replaces it.
            $table->unique('document_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_anonymizations', function (Blueprint $table) {
            $table->dropUnique(['document_id']);
            $table->dropColumn(['generated_text', 'source_hash']);
        });
    }
};
