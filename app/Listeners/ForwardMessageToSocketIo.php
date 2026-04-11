<?php

namespace App\Listeners;

use App\Events\MessageSent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ForwardMessageToSocketIo
{
    public function handle(MessageSent $event): void
    {
        $url = config('services.socket.emit_url');
        $secret = config('services.socket.secret');

        if (empty($url) || empty($secret)) {
            return;
        }

        $payload = [
            'id' => $event->message->id,
            'booking_id' => $event->message->booking_id,
            'message' => $event->message->message,
            'message_type' => $event->message->message_type,
            'sender_type' => $event->message->sender_type,
            'sender_id' => $event->message->sender_id,
            'created_at' => $event->message->created_at,
        ];

        try {
            $response = Http::timeout(2)
                ->acceptJson()
                ->post($url, [
                    'secret' => $secret,
                    'booking_id' => $event->message->booking_id,
                    'event' => 'message.sent',
                    'payload' => $payload,
                ]);

            if (! $response->successful()) {
                Log::warning('Socket.IO emit failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Socket.IO emit exception: '.$e->getMessage());
        }
    }
}
