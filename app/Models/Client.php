<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Client extends Model
{
    use HasFactory, SoftDeletes, HasApiTokens;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'verification_status',
        'selfie_path',
        'id_document_path',
        'verification_rejection_reason',
        'phone_verified_at',
        'email_verified_at',
        'is_active',
        'notification_preferences',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'notification_preferences' => 'array',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    // Relationships
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function verificationDocuments()
    {
        return $this->hasMany(VerificationDocument::class);
    }

    public function otpCodes()
    {
        return $this->hasMany(OtpCode::class, 'phone', 'phone');
    }

    // Scopes
    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopePending($query)
    {
        return $query->where('verification_status', 'pending');
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isVerified()
    {
        return $this->verification_status === 'verified';
    }
}
