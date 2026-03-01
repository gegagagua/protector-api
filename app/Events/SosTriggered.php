<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SosTriggered implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Booking $booking, public string $message)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('booking.' . $this->booking->id)];
    }

    public function broadcastAs(): string
    {
        return 'sos.triggered';
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'client_id' => $this->booking->client_id,
            'message' => $this->message,
            'at' => now()->toISOString(),
        ];
    }
}
