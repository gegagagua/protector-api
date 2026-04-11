<?php

namespace App\Enums;

enum VehicleRole: string
{
    case ArmoredSuv = 'armored_suv';
    case ArmoredSedan = 'armored_sedan';
    case LuxurySedan = 'luxury_sedan';
    case LuxuryVan = 'luxury_van';
    case Convoy = 'convoy';
    case Utility = 'utility';

    public function label(): string
    {
        return match ($this) {
            self::ArmoredSuv => 'Armored SUV',
            self::ArmoredSedan => 'Armored Sedan',
            self::LuxurySedan => 'Luxury Sedan',
            self::LuxuryVan => 'Luxury Van',
            self::Convoy => 'Convoy',
            self::Utility => 'Utility',
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
