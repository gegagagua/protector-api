<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'sender_type',
        'sender_id',
        'receiver_type',
        'receiver_id',
        'message',
        'message_type',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // Relationships
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function sender()
    {
        return $this->morphTo();
    }

    public function receiver()
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForBooking($query, $bookingId)
    {
        return $query->where('booking_id', $bookingId);
    }
}
