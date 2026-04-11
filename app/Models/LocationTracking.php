<?php

namespace App\Models;

use App\Support\MapLinks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationTracking extends Model
{
    use HasFactory;

    protected $table = 'location_tracking';

    protected $fillable = [
        'booking_id',
        'security_personnel_id',
        'client_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'heading',
        'tracked_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'accuracy' => 'decimal:2',
        'speed' => 'decimal:2',
        'heading' => 'decimal:2',
        'tracked_at' => 'datetime',
    ];

    // Relationships
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function securityPersonnel()
    {
        return $this->belongsTo(SecurityPersonnel::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // Scopes
    public function scopeForBooking($query, $bookingId)
    {
        return $query->where('booking_id', $bookingId);
    }

    public function scopeRecent($query, $minutes = 5)
    {
        return $query->where('tracked_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * @return array<string, mixed>
     */
    public function toMapArray(): array
    {
        $lat = (float) $this->latitude;
        $lng = (float) $this->longitude;

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'security_personnel_id' => $this->security_personnel_id,
            'latitude' => $lat,
            'longitude' => $lng,
            'lat' => $lat,
            'lng' => $lng,
            'google_maps_url' => MapLinks::googleMapsSearchUrl($lat, $lng),
            'accuracy' => $this->accuracy !== null ? (float) $this->accuracy : null,
            'speed' => $this->speed !== null ? (float) $this->speed : null,
            'heading' => $this->heading !== null ? (float) $this->heading : null,
            'tracked_at' => $this->tracked_at?->toIso8601String(),
        ];
    }
}
