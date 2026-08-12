<?php

namespace App\Enums;

enum WorkPreference: string
{
    case Remote = 'remote';
    case Hybrid = 'hybrid';
    case Onsite = 'onsite';
    case Any = 'any';
}
