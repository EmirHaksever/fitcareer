<?php

declare(strict_types=1);

namespace App\Enums;

enum TurkeyLocationCategory: string
{
    case Istanbul = 'istanbul';
    case OtherTurkey = 'other_turkey';
    case RemoteTurkey = 'remote_tr';
    case Foreign = 'foreign';
    case Unknown = 'unknown';
}
