<?php

namespace App\Services\Booking;

use App\Models\Booking;
use Illuminate\Validation\ValidationException;

class BookingStateMachine
{
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['ongoing', 'cancelled'],
        'ongoing' => ['arrived', 'cancelled'],
        'arrived' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function transition(Booking $booking, string $to, array $extra = []): Booking
    {
        if (!$this->canTransition($booking->status, $to)) {
            throw ValidationException::withMessages([
                'status' => ["Invalid booking status transition from {$booking->status} to {$to}."],
            ]);
        }

        $payload = ['status' => $to];

        if ($to === 'confirmed') {
            $payload['confirmed_at'] = now();
        }

        if ($to === 'ongoing') {
            $payload['started_at'] = now();
        }

        if ($to === 'arrived') {
            $payload['arrived_at'] = now();
        }

        if ($to === 'completed') {
            $payload['completed_at'] = now();
        }

        if ($to === 'cancelled') {
            $payload['cancelled_at'] = now();
        }

        $booking->update(array_merge($payload, $extra));

        return $booking->fresh();
    }
}
