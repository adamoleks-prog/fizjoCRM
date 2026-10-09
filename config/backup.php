<?php

return [

    // Kept outside the folders that are backed up, and outside public/.
    'path' => storage_path('app/backups'),

    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    // Patient documents and signed consents (patient_documents disk) and the
    // application's private files. Paths relative to storage/app.
    'directories' => ['patient-documents', 'private'],

    // The MySQL connection whose database is dumped.
    'connection' => env('BACKUP_DB_CONNECTION', 'mysql'),

    'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),

    'openssl' => env('BACKUP_OPENSSL', 'openssl'),

    'tar' => env('BACKUP_TAR', 'tar'),

    // openssl enc parameters — restoring needs the same ones (see the backups page).
    'cipher' => 'aes-256-cbc',
    'iterations' => 200000,

];
