<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'make',
        'model',
        'license_plate',
        'color',
        'year',
        'vehicle_type',
        'status',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'year' => 'integer',
    ];

    // Relationships
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')->where('is_active', true);
    }

    public function scopeInUse($query)
    {
        return $query->where('status', 'in_use');
    }
}
