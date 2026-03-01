<?php

namespace App\Http\Controllers\Client;

use App\Events\MessageSent;
use App\Events\SosTriggered;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Message;
use App\Models\SecurityPersonnel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function getMessages(Request $request, int $id): JsonResponse
    {
        $client = $request->user();
        $booking = $client->bookings()->findOrFail($id);

        $messages = $booking->messages()
            ->with(['sender', 'receiver'])
            ->latest()
            ->paginate(50);

        return response()->json([
            'status' => 'success',
            'messages' => $messages,
        ]);
    }

    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $client = $request->user();
        $booking = $client->bookings()->findOrFail($id);

        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'message_type' => 'nullable|in:text,quick_response',
        ]);

        $receiverPersonnelId = optional($booking->securityTeam?->personnel()->first())->id;

        $message = Message::create([
            'booking_id' => $booking->id,
            'sender_type' => Client::class,
            'sender_id' => $client->id,
            'receiver_type' => SecurityPersonnel::class,
            'receiver_id' => $receiverPersonnelId,
            'message' => $validated['message'],
            'message_type' => $validated['message_type'] ?? 'text',
        ]);

        event(new MessageSent($message));

        return response()->json([
            'status' => 'success',
            'message' => $message->load(['sender', 'receiver']),
        ], 201);
    }

    public function markRead(Request $request, int $bookingId, int $messageId): JsonResponse
    {
        $client = $request->user();
        $booking = $client->bookings()->findOrFail($bookingId);

        $message = $booking->messages()->findOrFail($messageId);
        $message->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Message marked as read.',
        ]);
    }

    public function triggerSos(Request $request, int $id): JsonResponse
    {
        $client = $request->user();
        $booking = $client->bookings()->findOrFail($id);

        $validated = $request->validate([
            'message' => 'nullable|string|max:500',
        ]);

        $sosText = $validated['message'] ?? 'SOS triggered by client.';
        $receiverPersonnelId = optional($booking->securityTeam?->personnel()->first())->id;

        $message = Message::create([
            'booking_id' => $booking->id,
            'sender_type' => Client::class,
            'sender_id' => $client->id,
            'receiver_type' => SecurityPersonnel::class,
            'receiver_id' => $receiverPersonnelId,
            'message' => $sosText,
            'message_type' => 'sos',
        ]);

        event(new MessageSent($message));
        event(new SosTriggered($booking, $sosText));

        return response()->json([
            'status' => 'success',
            'message' => 'SOS sent successfully.',
        ]);
    }
}
