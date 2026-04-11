<?php

namespace App\Events;

use App\Models\LocationTracking;
use App\Support\MapLinks;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public LocationTracking $location) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('booking.'.$this->location->booking_id)];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    public function broadcastWith(): array
    {
        $lat = (float) $this->location->latitude;
        $lng = (float) $this->location->longitude;

        return [
            'booking_id' => $this->location->booking_id,
            'latitude' => $lat,
            'longitude' => $lng,
            'lat' => $lat,
            'lng' => $lng,
            'google_maps_url' => MapLinks::googleMapsSearchUrl($lat, $lng),
            'accuracy' => $this->location->accuracy,
            'speed' => $this->location->speed,
            'heading' => $this->location->heading,
            'tracked_at' => $this->location->tracked_at,
            'security_personnel_id' => $this->location->security_personnel_id,
        ];
    }
}
