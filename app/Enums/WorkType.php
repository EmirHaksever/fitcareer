<?php

namespace App\Enums;

enum WorkType: string
{
    case Remote = 'remote';
    case Hybrid = 'hybrid';
    case Onsite = 'onsite';
}
