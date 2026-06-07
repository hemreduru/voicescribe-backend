<?php

namespace App\Services\Summarization\Exceptions;

use RuntimeException;

/**
 * Thrown when an LLM provider fails to produce a summary (network error,
 * non-2xx response, or an empty/invalid completion).
 */
class SummarizationException extends RuntimeException
{
}
