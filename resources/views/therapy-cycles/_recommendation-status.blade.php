@if ($recommendation->isQueued())
    <span class="text-xs px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700">W przygotowaniu</span>
@elseif ($recommendation->isFailed())
    <span class="text-xs px-2 py-0.5 rounded bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300">Nieudana</span>
@elseif (($recommendation->response['status'] ?? null) === 'wymaga_konsultacji')
    <span class="text-xs px-2 py-0.5 rounded bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300">Wymaga konsultacji</span>
@elseif (($recommendation->response['status'] ?? null) === 'wymaga_uzupelnienia_danych')
    <span class="text-xs px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200">Brakuje danych</span>
@else
    <span class="text-xs px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300">Gotowa</span>
@endif
