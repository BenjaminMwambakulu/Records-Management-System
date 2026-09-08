<?php

namespace App\Enums;

enum AssetStatus: string
{
    case AVAILABLE = 'available';
    case BORROWED = 'borrowed';
    case MAINTENANCE = 'maintenance';
    case RETIRED = 'retired';
}
