<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Events\ApplicationStatusChanged;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class AfterCommitProbeListener implements ShouldHandleEventsAfterCommit
{
    public static int $calls = 0;

    public function handle(ApplicationStatusChanged $event): void
    {
        self::$calls++;
        unset($event);
    }

    public static function reset(): void
    {
        self::$calls = 0;
    }
}
