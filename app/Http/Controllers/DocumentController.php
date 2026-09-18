<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\PatientAccessAction;
use App\Http\Requests\StoreDocumentRequest;
use App\Jobs\ExtractDocumentText;
use App\Models\Document;
use App\Models\Patient;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const DISK = 'patient_documents';

    public function store(StoreDocumentRequest $request, Patient $patient): RedirectResponse
    {
        $file = $request->file('file');

        $path = $file->storeAs(
            "patients/{$patient->id}",
            Str::uuid().'.pdf',
            self::DISK,
        );

        $document = $patient->documents()->create([
            'appointment_id' => $request->integer('appointment_id') ?: null,
            'uploaded_by_user_id' => $request->user()->id,
            'disk_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'type' => $request->enum('type', DocumentType::class) ?? DocumentType::Other,
            'title' => $request->input('title'),
        ]);

        ExtractDocumentText::dispatch($document);

        return back()->with('status', 'Dokument został dodany. Odczyt tekstu trwa w tle.');
    }

    public function show(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        AuditLogService::log($document->patient, PatientAccessAction::DocumentDownloaded);

        return Storage::disk(self::DISK)->response(
            $document->disk_path,
            $document->original_filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        Storage::disk(self::DISK)->delete($document->disk_path);
        $document->delete();

        return back()->with('status', 'Dokument został usunięty.');
    }
}
