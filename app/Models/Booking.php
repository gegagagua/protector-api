<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'security_team_id',
        'vehicle_id',
        'service_type',
        'security_personnel_count',
        'persons_to_protect_count',
        'address',
        'latitude',
        'longitude',
        'start_time',
        'end_time',
        'duration_hours',
        'booking_type',
        'status',
        'total_amount',
        'paid_amount',
        'refunded_amount',
        'payment_status',
        'cancellation_reason',
        'cancelled_at',
        'confirmed_at',
        'started_at',
        'arrived_at',
        'completed_at',
        'admin_notes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'cancelled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'started_at' => 'datetime',
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'duration_hours' => 'integer',
        'security_personnel_count' => 'integer',
        'persons_to_protect_count' => 'integer',
    ];

    // Relationships
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function securityTeam()
    {
        return $this->belongsTo(SecurityTeam::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function bookingPersons()
    {
        return $this->hasMany(BookingPerson::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function rating()
    {
        return $this->hasOne(Rating::class);
    }

    public function locationTracking()
    {
        return $this->hasMany(LocationTracking::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['confirmed', 'ongoing', 'arrived']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Methods
    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'confirmed']) && !$this->started_at;
    }

    public function calculateRefund()
    {
        if ($this->status === 'cancelled' && $this->started_at) {
            // Partial refund if cancelled after team started
            return $this->total_amount * 0.5; // 50% refund
        }
        return $this->total_amount; // Full refund
    }
}
