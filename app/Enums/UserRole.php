<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Candidate = 'candidate';
    case Company = 'company';

    /**
     * @return list<string>
     */
    public static function selfRegistrationValues(): array
    {
        return [
            self::Candidate->value,
            self::Company->value,
        ];
    }

    public static function tryFromSelfRegistration(string $role): ?self
    {
        return match ($role) {
            self::Candidate->value => self::Candidate,
            self::Company->value => self::Company,
            default => null,
        };
    }
}
