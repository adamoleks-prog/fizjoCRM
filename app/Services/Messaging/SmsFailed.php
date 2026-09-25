<?php

namespace App\Services\Messaging;

use RuntimeException;

/** Carries a reason safe to store and show — never the message text. */
class SmsFailed extends RuntimeException {}
