<?php

namespace App\Enums;

enum ServiceIcon: string
{
    case ShieldCheck = 'shield_check';
    case Shield = 'shield';
    case Car = 'car';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
