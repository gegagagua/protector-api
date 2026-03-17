<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Client extends Authenticatable
{
    use HasFactory, SoftDeletes, HasApiTokens, Notifiable;

    protected $appends = ['avatar_url', 'selfie_url', 'id_document_url'];

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
        'date_of_birth',
        'sex',
        'verification_status',
        'selfie_path',
        'id_document_path',
        'avatar_path',
        'verification_rejection_reason',
        'phone_verified_at',
        'email_verified_at',
        'is_active',
        'notification_preferences',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'date_of_birth' => 'date',
        'is_active' => 'boolean',
        'notification_preferences' => 'array',
        'password' => 'hashed',
    ];

    protected $hidden = [
        'password',
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

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->toAbsolutePublicUrl($this->avatar_path);
    }

    public function getSelfieUrlAttribute(): ?string
    {
        return $this->toAbsolutePublicUrl($this->selfie_path);
    }

    public function getIdDocumentUrlAttribute(): ?string
    {
        return $this->toAbsolutePublicUrl($this->id_document_path);
    }

    private function toAbsolutePublicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $url = Storage::disk('public')->url($path);
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $base = request()?->getSchemeAndHttpHost() ?: rtrim((string) config('app.url', ''), '/');
        if ($base === '') {
            return $url;
        }

        return rtrim($base, '/') . '/' . ltrim($url, '/');
    }
}
