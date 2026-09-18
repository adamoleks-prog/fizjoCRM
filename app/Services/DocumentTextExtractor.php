<?php

namespace App\Services;

use App\Enums\TextExtractionStatus;
use App\Models\Document;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pulls readable text out of an uploaded document.
 *
 * A PDF exported from another system carries its own text layer and is read
 * directly. A scan — which is what the phone camera upload always produces —
 * has no text at all, so each page is rasterised and passed through OCR.
 */
class DocumentTextExtractor
{
    private const DISK = 'patient_documents';

    public function extract(Document $document): void
    {
        $localPath = $this->localCopy($document);

        if ($localPath === null) {
            $this->store($document, null, TextExtractionStatus::Failed);

            return;
        }

        try {
            $text = $this->fromPdfLayer($localPath);

            if ($this->isUsable($text)) {
                $this->store($document, $text, TextExtractionStatus::FromPdfLayer);

                return;
            }

            $text = $this->fromOcr($localPath);

            $this->store(
                $document,
                $this->isUsable($text) ? $text : null,
                $this->isUsable($text) ? TextExtractionStatus::FromOcr : TextExtractionStatus::NoText,
            );
        } finally {
            @unlink($localPath);
        }
    }

    private function fromPdfLayer(string $path): ?string
    {
        $binary = config('documents.pdftotext_path');

        if (! $binary) {
            return null;
        }

        $result = Process::timeout(config('documents.process_timeout'))
            ->run([$binary, '-layout', '-enc', 'UTF-8', $path, '-']);

        return $result->successful() ? $result->output() : null;
    }

    private function fromOcr(string $path): ?string
    {
        $rasteriser = config('documents.pdftoppm_path');
        $tesseract = config('documents.tesseract_path');

        if (! $rasteriser || ! $tesseract) {
            return null;
        }

        $workDir = sys_get_temp_dir().'/ocr-'.Str::uuid();

        if (! mkdir($workDir) && ! is_dir($workDir)) {
            return null;
        }

        try {
            $rasterised = Process::timeout(config('documents.process_timeout'))
                ->run([
                    $rasteriser,
                    '-png',
                    '-r', (string) config('documents.ocr_dpi'),
                    '-f', '1',
                    '-l', (string) config('documents.ocr_max_pages'),
                    $path,
                    $workDir.'/page',
                ]);

            if (! $rasterised->successful()) {
                return null;
            }

            $pages = glob($workDir.'/page*.png') ?: [];
            sort($pages);

            $text = '';

            foreach ($pages as $page) {
                $ocr = Process::timeout(config('documents.process_timeout'))
                    ->run([$tesseract, $page, 'stdout', '-l', config('documents.ocr_language')]);

                if ($ocr->successful()) {
                    $text .= $ocr->output()."\n";
                }
            }

            return $text;
        } finally {
            array_map('unlink', glob($workDir.'/*') ?: []);
            @rmdir($workDir);
        }
    }

    /**
     * Copies the document out of the (possibly non-local) disk so the external
     * binaries get a real filesystem path to work with.
     */
    private function localCopy(Document $document): ?string
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($document->disk_path)) {
            return null;
        }

        $target = sys_get_temp_dir().'/doc-'.Str::uuid().'.pdf';

        if (file_put_contents($target, $disk->get($document->disk_path)) === false) {
            return null;
        }

        return $target;
    }

    private function isUsable(?string $text): bool
    {
        if ($text === null) {
            return false;
        }

        return mb_strlen(trim(preg_replace('/\s+/u', ' ', $text) ?? '')) >= config('documents.min_text_length');
    }

    private function store(Document $document, ?string $text, TextExtractionStatus $status): void
    {
        $document->forceFill([
            'ocr_text' => $text !== null ? trim($text) : null,
            'text_extraction_status' => $status,
            'text_extracted_at' => now(),
        ])->save();
    }
}
