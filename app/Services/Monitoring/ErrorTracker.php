<?php

namespace App\Services\Monitoring;

use App\Models\AppError;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Keeps application errors in the database, grouped by place in the code, so
 * the administrator sees them in the panel and gets an e-mail. The message is
 * cleaned first: database errors can quote the values being saved, so only
 * their error code is kept, and e-mail addresses and long numbers are masked.
 */
class ErrorTracker
{
    public function __construct(private readonly SecurityAlerts $alerts) {}

    public function capture(Throwable $e): void
    {
        try {
            [$file, $line] = self::origin($e);
            $fingerprint = sha1($e::class.'|'.$file.'|'.$line);

            // A queue job or a command has no page behind it.
            $request = request()->route() !== null ? request() : null;
            $url = $request
                ? '/'.ltrim($request->path(), '/')
                : implode(' ', array_slice($_SERVER['argv'] ?? [], 0, 3));

            $values = [
                'exception' => Str::limit($e::class, 250, ''),
                'message' => self::clean($e),
                'file' => Str::limit($file, 250, ''),
                'line' => $line,
                'method' => $request?->method() ?? 'CLI',
                'url' => Str::limit($url, 250, ''),
                'user_id' => $request?->user()?->id,
                'last_seen_at' => now(),
            ];

            $error = AppError::where('fingerprint', $fingerprint)->first();
            $first = $error === null || $error->resolved_at !== null;

            if ($error) {
                $error->fill([...$values, 'occurrences' => $error->occurrences + 1, 'resolved_at' => null])->save();
            } else {
                $error = AppError::create([...$values, 'fingerprint' => $fingerprint, 'first_seen_at' => now()]);
            }

            // A fault that was marked fixed and came back is news again.
            if ($first) {
                Cache::forget('monitoring-alert:error:'.$fingerprint);
            }

            $this->alerts->afterError($error, $first);
        } catch (Throwable) {
            // The tracker must never turn one error into two.
        }
    }

    /**
     * Where in our code it happened. An error thrown deep inside the framework
     * (a failed query) is reported at the first line of the application that
     * led there — that is the line to fix, and it keeps different faults apart.
     *
     * @return array{0: string, 1: int}
     */
    private static function origin(Throwable $e): array
    {
        $frames = [['file' => $e->getFile(), 'line' => $e->getLine()], ...$e->getTrace()];

        foreach ($frames as $frame) {
            $file = str_replace('\\', '/', $frame['file'] ?? '');
            if ($file !== '' && ! str_contains($file, '/vendor/') && str_contains($file, '/app/')) {
                return [Str::after($file, str_replace('\\', '/', base_path()).'/'), (int) ($frame['line'] ?? 0)];
            }
        }

        return [str_replace('\\', '/', Str::after($e->getFile(), base_path().DIRECTORY_SEPARATOR)), $e->getLine()];
    }

    public static function clean(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            return 'Błąd bazy danych (kod '.$e->getCode().')';
        }

        $message = $e->getMessage();
        $message = preg_replace('/[^\s@"\']+@[^\s@"\']+\.[a-z]{2,}/i', '[e-mail]', $message) ?? '';
        $message = preg_replace('/\d[\d \-]{7,}\d/', '[liczba]', $message) ?? '';

        return Str::limit($message, 500);
    }
}
