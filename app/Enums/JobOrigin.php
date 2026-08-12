<?php

namespace App\Enums;

enum JobOrigin: string
{
    case Internal = 'internal';
    case Scraped = 'scraped';
}
