<?php

namespace App\Support;

class MapLinks
{
    /**
     * Opens Google Maps at a coordinate (works in mobile browsers and the Google Maps app).
     */
    public static function googleMapsSearchUrl(float|string $latitude, float|string $longitude): string
    {
        $lat = rawurlencode((string) $latitude);
        $lng = rawurlencode((string) $longitude);

        return "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
    }
}
