<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ApplicationStatus;
use RuntimeException;

class InvalidStatusTransitionException extends RuntimeException
{
    public function __construct(
        public readonly ?ApplicationStatus $fromStatus,
        public readonly ApplicationStatus $toStatus,
    ) {
        parent::__construct(sprintf(
            'Cannot transition application status from %s to %s.',
            $fromStatus?->value ?? 'none',
            $toStatus->value,
        ));
    }
}
