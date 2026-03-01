<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class SecurityPersonnel extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    protected $table = 'security_personnel';

    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'phone',
        'email',
        'password',
        'security_team_id',
        'status',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'deleted_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    // Relationships
    public function securityTeam()
    {
        return $this->belongsTo(SecurityTeam::class);
    }

    public function bookings()
    {
        return $this->hasManyThrough(Booking::class, SecurityTeam::class);
    }

    public function locationTracking()
    {
        return $this->hasMany(LocationTracking::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')->where('is_active', true);
    }

    public function scopeBusy($query)
    {
        return $query->where('status', 'busy');
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
