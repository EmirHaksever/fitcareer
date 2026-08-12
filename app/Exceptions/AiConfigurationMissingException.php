<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class AiConfigurationMissingException extends RuntimeException
{
    public static function missingApiKey(): self
    {
        return new self('Gemini API key is not configured.');
    }

    public static function missingModel(): self
    {
        return new self('Gemini model is not configured.');
    }
}
