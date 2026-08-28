<?php

declare(strict_types=1);

namespace App\Enums;

enum JobSourceIngestPolicy: string
{
    /** Accept only listings classified as Turkey-relevant after normalization. */
    case TurkeyFirst = 'turkey_first';

    /** Accept all listings that pass existing quality gates (legacy behavior). */
    case Global = 'global';

    /**
     * Remote-oriented source: accept Turkey-relevant listings and remote listings
     * that are not explicitly global-only (EMEA, worldwide, foreign city/country).
     */
    case RemoteOpen = 'remote_open';

    public static function fromConfig(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::Global;
        }

        return self::tryFrom($value) ?? self::Global;
    }
}
