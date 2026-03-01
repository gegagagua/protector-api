<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    protected $table = 'admins';

    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'email',
        'phone',
        'password',
        'notification_preferences',
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
        'notification_preferences' => 'array',
    ];

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
