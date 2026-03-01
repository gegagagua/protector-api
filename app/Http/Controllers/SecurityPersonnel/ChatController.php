<?php

namespace App\Http\Controllers\SecurityPersonnel;

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
        path: "/api/security/orders/{id}/messages",
        summary: "Get messages for order",
        tags: ["Security Personnel Chat"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Messages")]
    )]
    public function getMessages(Request $request, $id): JsonResponse
    {
        $personnel = $request->user();
        $team = $personnel->securityTeam;

        $booking = Booking::where('security_team_id', $team->id)->findOrFail($id);

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
        path: "/api/security/orders/{id}/messages",
        summary: "Send message",
        tags: ["Security Personnel Chat"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["message"],
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "message_type", type: "string", enum: ["text", "sos", "quick_response"], default: "text")
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Message sent")]
    )]
    public function sendMessage(Request $request, $id): JsonResponse
    {
        $personnel = $request->user();
        $team = $personnel->securityTeam;

        $booking = Booking::where('security_team_id', $team->id)->findOrFail($id);

        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'message_type' => 'nullable|in:text,sos,quick_response',
        ]);

        $message = Message::create([
            'booking_id' => $booking->id,
            'sender_type' => SecurityPersonnel::class,
            'sender_id' => $personnel->id,
            'receiver_type' => Client::class,
            'receiver_id' => $booking->client_id,
            'message' => $validated['message'],
            'message_type' => $validated['message_type'] ?? 'text',
        ]);

        // TODO: Send push notification to client

        return response()->json([
            'status' => 'success',
            'message' => $message->load(['sender', 'receiver']),
        ], 201);
    }
}
