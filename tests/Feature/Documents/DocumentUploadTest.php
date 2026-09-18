<?php

use App\Enums\PatientAccessAction;
use App\Models\Document;
use App\Models\Patient;
use App\Models\PatientAccessLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('patient_documents');

    $this->operator = User::factory()->operator()->create();
    $this->patient = Patient::factory()->forOperator($this->operator)->create();
});

it('stores an uploaded pdf on the private disk', function () {
    $this->actingAs($this->operator)
        ->post(route('documents.store', $this->patient), [
            'file' => UploadedFile::fake()->create('skierowanie.pdf', 500, 'application/pdf'),
        ])
        ->assertRedirect();

    $document = Document::sole();

    expect($document->original_filename)->toBe('skierowanie.pdf')
        ->and($document->patient_id)->toBe($this->patient->id)
        ->and($document->uploaded_by_user_id)->toBe($this->operator->id);

    Storage::disk('patient_documents')->assertExists($document->disk_path);
});

it('rejects a file larger than the configured limit', function () {
    config(['documents.max_size_kb' => 1024]);

    $this->actingAs($this->operator)
        ->post(route('documents.store', $this->patient), [
            'file' => UploadedFile::fake()->create('duzy.pdf', 2048, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');

    expect(Document::count())->toBe(0);
});

it('rejects a non pdf file', function () {
    $this->actingAs($this->operator)
        ->post(route('documents.store', $this->patient), [
            'file' => UploadedFile::fake()->image('zdjecie.jpg'),
        ])
        ->assertSessionHasErrors('file');

    expect(Document::count())->toBe(0);
});

it('blocks uploading a document to another operators patient', function () {
    $foreignPatient = Patient::factory()->forOperator(User::factory()->operator()->create())->create();

    $this->actingAs($this->operator)
        ->post(route('documents.store', $foreignPatient), [
            'file' => UploadedFile::fake()->create('obcy.pdf', 100, 'application/pdf'),
        ])
        ->assertNotFound();

    expect(Document::count())->toBe(0);
});

it('lets the owning operator download a document and logs it', function () {
    $this->actingAs($this->operator)->post(route('documents.store', $this->patient), [
        'file' => UploadedFile::fake()->create('wynik.pdf', 100, 'application/pdf'),
    ]);

    $document = Document::sole();

    $this->actingAs($this->operator)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(PatientAccessLog::where('action', PatientAccessAction::DocumentDownloaded)->count())->toBe(1);
});

it('blocks another operator from downloading a document', function () {
    $this->actingAs($this->operator)->post(route('documents.store', $this->patient), [
        'file' => UploadedFile::fake()->create('wynik.pdf', 100, 'application/pdf'),
    ]);

    $document = Document::sole();
    $otherOperator = User::factory()->operator()->create();

    $this->actingAs($otherOperator)
        ->get(route('documents.show', $document))
        ->assertForbidden();
});

it('does not expose documents through a public storage url', function () {
    $this->actingAs($this->operator)->post(route('documents.store', $this->patient), [
        'file' => UploadedFile::fake()->create('poufne.pdf', 100, 'application/pdf'),
    ]);

    $document = Document::sole();

    $response = $this->actingAs($this->operator)->get('/storage/'.$document->disk_path);

    expect($response->isSuccessful())->toBeFalse();
});
