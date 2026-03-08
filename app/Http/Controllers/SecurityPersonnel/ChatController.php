<?php

namespace App\Http\Controllers\SecurityPersonnel;

use App\Events\MessageSent;
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
        description: "Returns chat thread for a security-assigned booking.",
        tags: ["Security Personnel Chat"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", description: "Booking ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [new OA\Response(response: 200, description: "Messages")]
    )]
    public function getMessages(Request $request, $id): JsonResponse
    {
        $personnel = $request->user();
        $team = $personnel->securityTeam;
        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Security personnel is not assigned to a team.',
            ], 422);
        }

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
        description: "Sends chat message from security personnel to client and broadcasts realtime event.",
        tags: ["Security Personnel Chat"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["message"],
                properties: [
                    new OA\Property(property: "message", type: "string", description: "Message content sent to client", example: "Team arrived nearby."),
                    new OA\Property(property: "message_type", type: "string", description: "Message category used by chat UI", enum: ["text", "sos", "quick_response"], default: "text")
                ]
            )
        ),
        parameters: [
            new OA\Parameter(name: "id", description: "Booking ID", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [new OA\Response(response: 200, description: "Message sent")]
    )]
    public function sendMessage(Request $request, $id): JsonResponse
    {
        $personnel = $request->user();
        $team = $personnel->securityTeam;
        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Security personnel is not assigned to a team.',
            ], 422);
        }

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

        event(new MessageSent($message));

        return response()->json([
            'status' => 'success',
            'message' => $message->load(['sender', 'receiver']),
        ], 201);
    }
}
