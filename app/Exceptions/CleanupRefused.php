<?php

namespace App\Exceptions;

/** Only these deliberately sanitized messages may be shown by the cleanup command. */
class CleanupRefused extends \RuntimeException {}
