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
use OpenApi\Attributes as OA;

class ChatController extends Controller
{
    #[OA\Get(
        path: "/api/client/bookings/{id}/messages",
        summary: "Get booking chat messages",
        description: "Returns paginated chat messages for a client booking conversation.",
        tags: ["Client Chat"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Messages list")]
    )]
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

    #[OA\Post(
        path: "/api/client/bookings/{id}/messages",
        summary: "Send booking message",
        description: "Sends a message from client to assigned security personnel for a booking.",
        tags: ["Client Chat"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 201, description: "Message sent")]
    )]
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

    #[OA\Post(
        path: "/api/client/bookings/{bookingId}/messages/{messageId}/read",
        summary: "Mark message as read",
        description: "Marks a booking chat message as read for the authenticated client.",
        tags: ["Client Chat"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Message read status updated")]
    )]
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

    #[OA\Post(
        path: "/api/client/bookings/{id}/sos",
        summary: "Trigger SOS",
        description: "Sends an SOS alert from client to assigned security team and broadcasts realtime alert.",
        tags: ["Client Chat"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "SOS sent")]
    )]
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
