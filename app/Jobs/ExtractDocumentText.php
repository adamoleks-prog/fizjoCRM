<?php

namespace App\Jobs;

use App\Enums\TextExtractionStatus;
use App\Models\Document;
use App\Services\DocumentTextExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * OCR of a multi-page scan takes far too long to keep an upload request
 * waiting, so extraction always happens out of band.
 */
class ExtractDocumentText implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public Document $document) {}

    public function handle(DocumentTextExtractor $extractor): void
    {
        $extractor->extract($this->document);
    }

    public function failed(?Throwable $exception): void
    {
        $this->document->forceFill([
            'text_extraction_status' => TextExtractionStatus::Failed,
            'text_extracted_at' => now(),
        ])->save();
    }
}
