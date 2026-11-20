<?php

namespace App\Support\ErrorTracking;

use RuntimeException;

/**
 * ntfy could not be reached. The message is fixed on purpose: the original connection error names the topic URL,
 * which is the secret of the alert channel.
 */
final class NtfyUnreachable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The ntfy alert channel could not be reached.');
    }
}
