<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\PatientAccessAction;
use App\Http\Requests\StoreDocumentRequest;
use App\Jobs\ExtractDocumentText;
use App\Models\Document;
use App\Models\Patient;
use App\Services\AuditLogService;
use App\Services\Encryption\PatientCipher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DocumentController extends Controller
{
    private const DISK = 'patient_documents';

    public function store(StoreDocumentRequest $request, Patient $patient, PatientCipher $cipher): RedirectResponse
    {
        $file = $request->file('file');

        // Stored encrypted with the patient's key — the PDF never sits on disk in the clear.
        $path = "patients/{$patient->id}/".Str::uuid().'.pdf';
        Storage::disk(self::DISK)->put($path, $cipher->encryptFile($patient->id, (string) $file->get()));

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

    public function show(Document $document, PatientCipher $cipher): Response
    {
        $this->authorize('view', $document);

        $stored = Storage::disk(self::DISK)->get($document->disk_path);
        $contents = $stored === null ? null : $cipher->decryptFile($document->patient_id, $stored);
        abort_if($contents === null, 404);

        AuditLogService::log($document->patient, PatientAccessAction::DocumentDownloaded);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $document->original_filename,
                Str::ascii($document->original_filename) ?: 'dokument.pdf',
            ),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        Storage::disk(self::DISK)->delete($document->disk_path);
        $document->delete();

        return back()->with('status', 'Dokument został usunięty.');
    }
}
