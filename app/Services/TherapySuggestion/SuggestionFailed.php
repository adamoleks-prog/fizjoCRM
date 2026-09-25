<?php

namespace App\Services\TherapySuggestion;

use RuntimeException;

/**
 * Carries a reason fit to store and show. The message must never contain any part
 * of the request or the response — both are medical data.
 */
class SuggestionFailed extends RuntimeException {}
