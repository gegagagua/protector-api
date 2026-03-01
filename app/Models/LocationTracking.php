<?php

namespace App\Models;

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
}
