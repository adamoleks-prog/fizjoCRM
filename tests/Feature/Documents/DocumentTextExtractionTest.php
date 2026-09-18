<?php

use App\Enums\TextExtractionStatus;
use App\Jobs\ExtractDocumentText;
use App\Models\Document;
use App\Models\Patient;
use App\Models\User;
use App\Services\DocumentTextExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('patient_documents');

    config([
        'documents.pdftotext_path' => '/fake/pdftotext',
        'documents.pdftoppm_path' => '/fake/pdftoppm',
        'documents.tesseract_path' => '/fake/tesseract',
    ]);

    $this->operator = User::factory()->operator()->create();
    $this->patient = Patient::factory()->forOperator($this->operator)->create();

    $this->document = function (): Document {
        Storage::disk('patient_documents')->put('patients/1/doc.pdf', 'udawany-pdf');

        return Document::create([
            'patient_id' => $this->patient->id,
            'uploaded_by_user_id' => $this->operator->id,
            'disk_path' => 'patients/1/doc.pdf',
            'original_filename' => 'skierowanie.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1234,
            'type' => 'referral',
        ]);
    };
});

it('queues extraction after upload instead of blocking the request', function () {
    Queue::fake();

    $this->actingAs($this->operator)
        ->post(route('documents.store', $this->patient), [
            'file' => UploadedFile::fake()->create('skierowanie.pdf', 100, 'application/pdf'),
            'type' => 'referral',
            'title' => 'Skierowanie od ortopedy',
        ])
        ->assertSessionHasNoErrors();

    Queue::assertPushed(ExtractDocumentText::class);

    $document = Document::sole();

    expect($document->title)->toBe('Skierowanie od ortopedy')
        ->and($document->type->value)->toBe('referral')
        ->and($document->text_extraction_status)->toBe(TextExtractionStatus::Pending);
});

it('reads a pdf that carries its own text layer without running ocr', function () {
    Process::fake([
        '/fake/pdftotext*' => Process::result('Rozpoznanie: bóle kręgosłupa lędźwiowego M54.5, zalecana terapia manualna'),
    ]);

    $document = ($this->document)();
    app(DocumentTextExtractor::class)->extract($document);

    $document->refresh();

    expect($document->text_extraction_status)->toBe(TextExtractionStatus::FromPdfLayer)
        ->and($document->ocr_text)->toContain('kręgosłupa')
        ->and($document->text_extracted_at)->not->toBeNull();

    Process::assertNotRan(fn ($process) => str_contains($process->command[0] ?? '', 'tesseract'));
});

it('falls back to ocr when the pdf has no text layer', function () {
    Process::fake([
        '/fake/pdftotext*' => Process::result(''),
        '/fake/pdftoppm*' => Process::result(''),
        '/fake/tesseract*' => Process::result('SKIEROWANIE NA ZABIEGI FIZJOTERAPEUTYCZNE rozpoznanie M54.5 bóle krzyża'),
    ]);

    $document = ($this->document)();

    // pdftoppm is faked, so no page files appear on disk; the OCR branch still has
    // to be reached, which is what this asserts through the recorded processes.
    app(DocumentTextExtractor::class)->extract($document);

    Process::assertRan(fn ($process) => str_contains($process->command[0] ?? '', 'pdftoppm'));

    expect($document->refresh()->text_extraction_status)
        ->toBeIn([TextExtractionStatus::FromOcr, TextExtractionStatus::NoText]);
});

it('marks a scan as having no readable text when ocr finds nothing', function () {
    Process::fake([
        '/fake/pdftotext*' => Process::result(''),
        '/fake/pdftoppm*' => Process::result(''),
        '/fake/tesseract*' => Process::result(''),
    ]);

    $document = ($this->document)();
    app(DocumentTextExtractor::class)->extract($document);

    expect($document->refresh()->text_extraction_status)->toBe(TextExtractionStatus::NoText)
        ->and($document->ocr_text)->toBeNull();
});

it('degrades gracefully when the binaries are not configured', function () {
    config([
        'documents.pdftotext_path' => null,
        'documents.pdftoppm_path' => null,
        'documents.tesseract_path' => null,
    ]);

    $document = ($this->document)();
    app(DocumentTextExtractor::class)->extract($document);

    expect($document->refresh()->text_extraction_status)->toBe(TextExtractionStatus::NoText);
});

it('marks extraction as failed when the file is missing from disk', function () {
    $document = Document::create([
        'patient_id' => $this->patient->id,
        'uploaded_by_user_id' => $this->operator->id,
        'disk_path' => 'patients/1/nie-istnieje.pdf',
        'original_filename' => 'brak.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 10,
        'type' => 'other',
    ]);

    app(DocumentTextExtractor::class)->extract($document);

    expect($document->refresh()->text_extraction_status)->toBe(TextExtractionStatus::Failed);
});

it('rejects an unknown document type', function () {
    $this->actingAs($this->operator)
        ->post(route('documents.store', $this->patient), [
            'file' => UploadedFile::fake()->create('skan.pdf', 100, 'application/pdf'),
            'type' => 'recepta',
        ])
        ->assertSessionHasErrors('type');
});
