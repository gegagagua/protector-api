<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecurityTeam extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = ['title'];

    protected $fillable = [
        'name',
        'team_size',
        'service_type',
        'status',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'team_size' => 'integer',
    ];

    // Relationships
    public function personnel()
    {
        return $this->hasMany(SecurityPersonnel::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')->where('is_active', true);
    }

    public function scopeArmed($query)
    {
        return $query->where('service_type', 'armed');
    }

    public function scopeUnarmed($query)
    {
        return $query->where('service_type', 'unarmed');
    }

    public function getTitleAttribute(): string
    {
        return (string) $this->name;
    }
}
