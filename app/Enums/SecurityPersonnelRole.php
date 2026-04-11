<?php

namespace App\Enums;

enum SecurityPersonnelRole: string
{
    case ArmedGuard = 'armed_guard';
    case UnarmedGuard = 'unarmed_guard';
    case Driver = 'driver';
    case HeavyArmedGuard = 'heavy_armed_guard';

    public function label(): string
    {
        return match ($this) {
            self::ArmedGuard => 'Armed Guard',
            self::UnarmedGuard => 'Unarmed Guard',
            self::Driver => 'Driver',
            self::HeavyArmedGuard => 'Heavy Armed Guard',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
