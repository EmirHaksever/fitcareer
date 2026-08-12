<?php

namespace App\Enums;

enum ProfileVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case CompaniesOnly = 'companies_only';
}
