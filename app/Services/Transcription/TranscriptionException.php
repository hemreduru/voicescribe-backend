<?php

namespace App\Services\Transcription;

use RuntimeException;

/**
 * A transcription failure that is safe to map to an HTTP response: the message
 * is generic (never contains upstream bodies or secrets) and `status` is the
 * status code the API should answer with.
 */
class TranscriptionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $retryAfter = null,
    ) {
        parent::__construct($message);
    }
}
