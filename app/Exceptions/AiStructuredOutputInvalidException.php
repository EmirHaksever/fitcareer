<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class AiStructuredOutputInvalidException extends RuntimeException
{
    public static function missingContent(): self
    {
        return new self('AI response did not contain structured content.');
    }

    public static function invalidJson(string $detail = ''): self
    {
        $message = 'AI structured output is not valid JSON.';

        if ($detail !== '') {
            $message .= ' '.$detail;
        }

        return new self($message);
    }

    public static function schemaViolation(string $detail): self
    {
        return new self('AI structured output failed validation: '.$detail);
    }
}
