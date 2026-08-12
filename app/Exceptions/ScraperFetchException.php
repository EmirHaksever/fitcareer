<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ScraperFetchException extends RuntimeException
{
    public static function unsupportedProvider(string $provider): self
    {
        return new self('Unsupported job source provider: '.$provider);
    }

    public static function missingConfiguration(string $detail): self
    {
        return new self('Job source configuration is invalid: '.$detail);
    }

    public static function httpFailure(int $status, string $url): self
    {
        return new self('Job source request failed with HTTP '.$status.' for '.$url);
    }

    public static function invalidPayload(string $detail): self
    {
        return new self('Job source response could not be parsed: '.$detail);
    }
}
