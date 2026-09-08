<?php

namespace App\Enums;

enum AcademicTrack: string
{
    case Bit = 'BIT';
    case Css = 'CSS';

    public function label(): string
    {
        return match ($this) {
            self::Bit => 'Business Information Technology (BIT)',
            self::Css => 'Computer Systems and Security (CSS)',
        };
    }
}
