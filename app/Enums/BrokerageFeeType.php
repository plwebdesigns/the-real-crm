<?php

namespace App\Enums;

enum BrokerageFeeType: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';
}
