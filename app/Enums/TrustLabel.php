<?php

namespace App\Enums;

enum TrustLabel: string
{
    case Verified = 'verified';
    case Suspicious = 'suspicious';
    case LowTrust = 'low_trust';
    case Unrated = 'unrated';
}
