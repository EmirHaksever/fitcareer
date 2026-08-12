<?php

namespace App\Enums;

enum JobSourceType: string
{
    case Scraper = 'scraper';
    case ApiIntegration = 'api_integration';
}
