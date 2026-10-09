<?php

namespace App\Services\Monitoring;

use App\Enums\SecurityEventType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Looks at requests the application refused and logs the suspicious ones:
 * 403 (no permission), 404 on a record the user cannot see (another
 * physiotherapist's patient is invisible, so it is "not found"), 404 on a
 * scanner's favourite address, 419 (forged or expired form), 429 (limit hit).
 */
class SecurityResponses
{
    public function __construct(private readonly SecurityLog $log) {}

    public function inspect(Response $response, Throwable $e, Request $request): void
    {
        match ($response->getStatusCode()) {
            403 => $this->log->record(SecurityEventType::AccessDenied, ['admin_area' => $request->is('admin/*')], request: $request),
            404 => $this->notFound($e, $request),
            419 => $this->log->record(SecurityEventType::CsrfMismatch, request: $request),
            429 => $this->log->record(SecurityEventType::Throttled, request: $request),
            default => null,
        };
    }

    private function notFound(Throwable $e, Request $request): void
    {
        if (SecurityLog::isProbe($request->path())) {
            $this->log->record(SecurityEventType::Probe, request: $request);

            return;
        }

        $missing = $e instanceof ModelNotFoundException ? $e : $e->getPrevious();

        if ($request->user() && $missing instanceof ModelNotFoundException) {
            $this->log->record(SecurityEventType::RecordNotFound, [
                'model' => class_basename($missing->getModel()),
                'ids' => array_slice((array) $missing->getIds(), 0, 5),
            ], request: $request);
        }
    }
}
