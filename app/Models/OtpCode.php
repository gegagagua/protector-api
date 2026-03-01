<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OtpCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'code',
        'type',
        'is_used',
        'expires_at',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'expires_at' => 'datetime',
    ];

    // Scopes
    public function scopeValid($query)
    {
        return $query->where('is_used', false)
            ->where('expires_at', '>', now());
    }

    public function scopeForPhone($query, $phone)
    {
        return $query->where('phone', $phone);
    }

    public function scopeForCode($query, $code)
    {
        return $query->where('code', $code);
    }

    // Methods
    public function isValid()
    {
        return !$this->is_used && $this->expires_at->isFuture();
    }

    public function markAsUsed()
    {
        $this->update(['is_used' => true]);
    }
}
