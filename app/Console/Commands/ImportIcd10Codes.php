<?php

namespace App\Console\Commands;

use App\Models\Icd10Code;
use Illuminate\Console\Command;

class ImportIcd10Codes extends Command
{
    protected $signature = 'icd10:import
                            {file : Ścieżka do pliku CSV z kolumnami: kod, nazwa, [rozdział]}
                            {--separator=; : Separator kolumn}
                            {--skip-header : Pomiń pierwszy wiersz pliku}';

    protected $description = 'Importuje oficjalny słownik ICD-10 z pliku CSV';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Nie można odczytać pliku: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $separator = $this->option('separator');
        $imported = 0;
        $skipped = 0;

        if ($this->option('skip-header')) {
            fgetcsv($handle, escape: '');
        }

        while (($row = fgetcsv($handle, separator: $separator, escape: '')) !== false) {
            $code = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));

            if ($code === '' || $name === '') {
                $skipped++;

                continue;
            }

            Icd10Code::updateOrCreate(['code' => $code], [
                'name' => $name,
                'chapter' => isset($row[2]) ? trim((string) $row[2]) : null,
            ]);

            $imported++;
        }

        fclose($handle);

        $this->info("Zaimportowano: {$imported}. Pominięto wierszy: {$skipped}.");
        $this->line('Łącznie kodów w słowniku: '.Icd10Code::count());

        return self::SUCCESS;
    }
}
