<?php

namespace App\Enums;

enum TextExtractionStatus: string
{
    case Pending = 'pending';
    case FromPdfLayer = 'from_pdf_layer';
    case FromOcr = 'from_ocr';
    case NoText = 'no_text';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Oczekuje na odczyt',
            self::FromPdfLayer => 'Tekst odczytany',
            self::FromOcr => 'Tekst odczytany (OCR)',
            self::NoText => 'Brak czytelnego tekstu',
            self::Failed => 'Odczyt nieudany',
        };
    }

    public function hasText(): bool
    {
        return in_array($this, [self::FromPdfLayer, self::FromOcr], true);
    }
}
