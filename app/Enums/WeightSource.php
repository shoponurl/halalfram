<?php

declare(strict_types=1);

namespace App\Enums;

enum WeightSource: string
{
    case Manual = 'manual';
    case Scale = 'scale';   // Sprint 04/POS: WebSerial scale integration
}
