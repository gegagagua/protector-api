<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'client_id',
        'security_team_id',
        'rating',
        'review',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    // Relationships
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function securityTeam()
    {
        return $this->belongsTo(SecurityTeam::class);
    }
}
