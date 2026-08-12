<?php

namespace App\Enums;

enum CompanySize: string
{
    case OneToTen = '1-10';
    case ElevenToFifty = '11-50';
    case FiftyOneToTwoHundred = '51-200';
    case TwoHundredOneToFiveHundred = '201-500';
    case FiveHundredOneToOneThousand = '501-1000';
    case OneThousandPlus = '1000+';
}
