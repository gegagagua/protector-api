<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class OtpCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'code',
        'code_hash',
        'type',
        'is_used',
        'attempt_count',
        'expires_at',
        'last_sent_at',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'attempt_count' => 'integer',
        'expires_at' => 'datetime',
        'last_sent_at' => 'datetime',
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

    // Methods
    public function isValid()
    {
        return !$this->is_used && $this->expires_at->isFuture();
    }

    public function markAsUsed()
    {
        $this->update([
            'is_used' => true,
        ]);
    }
}
