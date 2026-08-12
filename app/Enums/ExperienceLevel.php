<?php

namespace App\Enums;

enum ExperienceLevel: string
{
    case Intern = 'intern';
    case Entry = 'entry';
    case Mid = 'mid';
    case Senior = 'senior';
    case Lead = 'lead';
    case Executive = 'executive';
}
