<?php

return [
    'max_size_kb' => env('DOCUMENTS_MAX_SIZE_KB', 10240),
    'allowed_mime' => ['application/pdf'],

    /*
     * Text extraction. PHP-FPM does not inherit the shell PATH, so these must be
     * absolute paths on the server; left null the feature degrades to "no text"
     * instead of failing the upload.
     */
    'pdftotext_path' => env('PDFTOTEXT_PATH'),
    'pdftoppm_path' => env('PDFTOPPM_PATH'),
    'tesseract_path' => env('TESSERACT_PATH'),

    'ocr_language' => env('OCR_LANGUAGE', 'pol'),

    // Resolution used when rasterising a scanned page before OCR.
    'ocr_dpi' => env('OCR_DPI', 300),

    // Scans are slow to process; a referral is a page or two, not a book.
    'ocr_max_pages' => env('OCR_MAX_PAGES', 10),

    // Below this many characters a PDF is treated as a scan with no text layer.
    'min_text_length' => env('DOCUMENTS_MIN_TEXT_LENGTH', 40),

    'process_timeout' => env('DOCUMENTS_PROCESS_TIMEOUT', 120),
];
