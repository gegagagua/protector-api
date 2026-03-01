<?php

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Client;
use App\Models\SecurityPersonnel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('booking.{bookingId}', function ($user, int $bookingId) {
    $booking = Booking::find($bookingId);

    if (!$booking) {
        return false;
    }

    if ($user instanceof Admin) {
        return true;
    }

    if ($user instanceof Client) {
        return $booking->client_id === $user->id;
    }

    if ($user instanceof SecurityPersonnel) {
        return $booking->security_team_id === $user->security_team_id;
    }

    return false;
});
