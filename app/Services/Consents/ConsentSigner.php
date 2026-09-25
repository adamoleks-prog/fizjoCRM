<?php

namespace App\Services\Consents;

use App\Enums\DocumentType;
use App\Enums\PatientAccessAction;
use App\Enums\TextExtractionStatus;
use App\Models\ConsentTemplate;
use App\Models\Patient;
use App\Models\SignedConsent;
use App\Models\User;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a signature drawn on the screen into a signed PDF kept with the patient's
 * documents, and records who witnessed it, when and on which device.
 */
class ConsentSigner
{
    private const DISK = 'patient_documents';

    private const MAX_SIGNATURE_BYTES = 400_000;

    public function sign(Patient $patient, ConsentTemplate $template, string $signatureDataUrl, User $witness, Request $request): SignedConsent
    {
        $signature = $this->decodeSignature($signatureDataUrl);

        $physiotherapist = User::findOrFail($patient->operator_id);
        $text = $template->render($patient, $physiotherapist);
        $signedAt = now();

        $pdf = Pdf::loadView('pdf.signed-consent', [
            'title' => $template->name,
            'text' => $text,
            'patient' => $patient,
            'physiotherapist' => $physiotherapist,
            'witness' => $witness,
            'signature' => 'data:image/png;base64,'.base64_encode($signature),
            'signedAt' => $signedAt,
            'hash' => hash('sha256', $text),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output();

        $path = "patients/{$patient->id}/".Str::uuid().'.pdf';
        Storage::disk(self::DISK)->put($path, $pdf);

        return DB::transaction(function () use ($patient, $template, $witness, $request, $text, $signedAt, $path, $pdf) {
            $document = $patient->documents()->create([
                'uploaded_by_user_id' => $witness->id,
                'disk_path' => $path,
                'original_filename' => Str::slug($template->name).'-'.$signedAt->format('Y-m-d').'.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($pdf),
                'type' => DocumentType::Consent,
                'title' => $template->name.' (podpisana '.$signedAt->format('d.m.Y').')',
            ]);

            // The text is known exactly — no need to read it back from the PDF.
            $document->forceFill([
                'ocr_text' => $text,
                'text_extraction_status' => TextExtractionStatus::FromPdfLayer,
                'text_extracted_at' => $signedAt,
            ])->save();

            $consent = new SignedConsent;
            $consent->operator_id = $patient->operator_id;
            $consent->patient_id = $patient->id;
            $consent->document_id = $document->id;
            $consent->consent_template_id = $template->id;
            $consent->witnessed_by_user_id = $witness->id;
            $consent->name = $template->name;
            $consent->body_hash = hash('sha256', $text);
            $consent->ip_address = $request->ip();
            $consent->user_agent = mb_substr((string) $request->userAgent(), 0, 255);
            $consent->signed_at = $signedAt;
            $consent->save();

            AuditLogService::log($patient, PatientAccessAction::ConsentSigned);

            return $consent;
        });
    }

    /**
     * @throws ValidationException when it is not a PNG, too large, or empty
     */
    private function decodeSignature(string $dataUrl): string
    {
        $fail = fn (string $message) => ValidationException::withMessages(['signature' => $message]);

        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw $fail('Brak podpisu.');
        }

        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);

        if ($binary === false || strlen($binary) > self::MAX_SIGNATURE_BYTES || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            throw $fail('Nieprawidłowy obraz podpisu.');
        }

        if ($this->inkedPixels($binary) < 40) {
            throw $fail('Podpis jest pusty albo zbyt krótki. Podpisz się w ramce.');
        }

        return $binary;
    }

    /** Counts drawn pixels on a coarse grid — a tap or an empty canvas is not a signature. */
    private function inkedPixels(string $png): int
    {
        $image = @imagecreatefromstring($png);

        if ($image === false) {
            return 0;
        }

        $count = 0;
        $width = imagesx($image);
        $height = imagesy($image);

        for ($x = 0; $x < $width; $x += 3) {
            for ($y = 0; $y < $height; $y += 3) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $brightness = (($rgba >> 16) & 0xFF) + (($rgba >> 8) & 0xFF) + ($rgba & 0xFF);

                if ($alpha < 100 && $brightness < 600) {
                    $count++;
                }
            }
        }

        imagedestroy($image);

        return $count;
    }
}
