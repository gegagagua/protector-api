<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TeamController extends Controller
{
    #[OA\Get(
        path: "/api/admin/teams",
        summary: "Get all security teams",
        tags: ["Admin Teams"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 200, description: "Teams list")]
    )]
    public function index(): JsonResponse
    {
        $teams = SecurityTeam::with(['personnel', 'bookings' => function($query) {
            $query->whereIn('status', ['confirmed', 'ongoing', 'arrived']);
        }])->latest()->get();

        return response()->json([
            'status' => 'success',
            'teams' => $teams,
        ]);
    }

    #[OA\Post(
        path: "/api/admin/teams",
        summary: "Create security team",
        tags: ["Admin Teams"],
        security: [["sanctum" => []]],
        responses: [new OA\Response(response: 201, description: "Team created")]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'team_size' => 'required|integer|min:1',
            'service_type' => 'required|in:armed,unarmed',
            'description' => 'nullable|string',
        ]);

        $team = SecurityTeam::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Team created successfully',
            'team' => $team,
        ], 201);
    }
}
