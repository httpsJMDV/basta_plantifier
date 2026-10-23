<?php

namespace App\Services;

use RuntimeException;

class AiFailure extends RuntimeException
{
    // $reason: quota, credits, failed, length or connection. $fallback: the failing provider was the backup.
    public function __construct(public readonly string $reason, public readonly string $provider, public readonly bool $fallback = false)
    {
        parent::__construct($reason);
    }
}
