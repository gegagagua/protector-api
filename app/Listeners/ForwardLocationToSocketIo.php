<?php

namespace App\Listeners;

use App\Events\LocationUpdated;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ForwardLocationToSocketIo
{
    public function handle(LocationUpdated $event): void
    {
        $url = config('services.socket.emit_url');
        $secret = config('services.socket.secret');

        if (empty($url) || empty($secret)) {
            return;
        }

        $loc = $event->location;
        $payload = [
            'booking_id' => $loc->booking_id,
            'latitude' => (float) $loc->latitude,
            'longitude' => (float) $loc->longitude,
            'accuracy' => $loc->accuracy !== null ? (float) $loc->accuracy : null,
            'speed' => $loc->speed !== null ? (float) $loc->speed : null,
            'heading' => $loc->heading !== null ? (float) $loc->heading : null,
            'tracked_at' => $loc->tracked_at?->toIso8601String(),
            'security_personnel_id' => $loc->security_personnel_id,
        ];

        try {
            $response = Http::timeout(2)
                ->acceptJson()
                ->post($url, [
                    'secret' => $secret,
                    'booking_id' => $loc->booking_id,
                    'event' => 'location.updated',
                    'payload' => $payload,
                ]);

            if (! $response->successful()) {
                Log::warning('Socket.IO location emit failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Socket.IO location emit exception: '.$e->getMessage());
        }
    }
}
