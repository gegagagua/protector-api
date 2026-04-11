<?php

namespace App\Models;

use App\Enums\ServiceIcon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'slug',
        'name_en',
        'name_ka',
        'description_en',
        'description_ka',
        'icon',
        'hourly_rate',
        'daily_rate',
        'requires_vehicle',
        'team_service_type',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'icon' => ServiceIcon::class,
        'hourly_rate' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'requires_vehicle' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function localizedName(string $locale): string
    {
        return $locale === 'ka' ? (string) $this->name_ka : (string) $this->name_en;
    }

    public function localizedDescription(string $locale): ?string
    {
        $d = $locale === 'ka' ? $this->description_ka : $this->description_en;

        return $d !== null && $d !== '' ? (string) $d : null;
    }
}
