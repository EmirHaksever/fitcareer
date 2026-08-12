<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class AiProviderUnavailableException extends RuntimeException
{
    public static function fromHttpStatus(int $status, ?string $detail = null): self
    {
        $message = 'AI provider request failed with HTTP '.$status.'.';

        if ($detail !== null && $detail !== '') {
            $message .= ' '.$detail;
        }

        return new self($message);
    }

    public static function transport(string $detail): self
    {
        return new self('AI provider transport error: '.$detail);
    }
}
