<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityPersonnel;
use App\Models\SecurityTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class SecurityPersonnelController extends Controller
{
    #[OA\Get(
        path: "/api/admin/security-personnel",
        summary: "List security personnel",
        description: "Returns paginated security staff with optional filter by team.",
        tags: ["Admin Security Personnel"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "team_id", description: "Filter by security_team_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
        ],
        responses: [new OA\Response(response: 200, description: "Personnel list")]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = SecurityPersonnel::query()->with('securityTeam')->latest();

        if ($request->filled('team_id')) {
            $query->where('security_team_id', (int) $request->query('team_id'));
        }

        $personnel = $query->paginate(30);

        return response()->json([
            'status' => 'success',
            'personnel' => $personnel,
        ]);
    }

    #[OA\Post(
        path: "/api/admin/security-personnel",
        summary: "Create security personnel",
        description: "Creates a new security account (login). Password is stored hashed. Default status is offline unless specified.",
        tags: ["Admin Security Personnel"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["first_name", "last_name", "username", "phone", "password"],
                properties: [
                    new OA\Property(property: "first_name", type: "string", example: "Giorgi"),
                    new OA\Property(property: "last_name", type: "string", example: "Maisuradze"),
                    new OA\Property(property: "username", type: "string", example: "guard_new"),
                    new OA\Property(property: "phone", type: "string", example: "+995555770099"),
                    new OA\Property(property: "email", type: "string", format: "email", nullable: true, example: "guard@proector.local"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "SecurePass1"),
                    new OA\Property(property: "security_team_id", type: "integer", nullable: true, description: "Assign to team (optional)", example: 1),
                    new OA\Property(property: "status", type: "string", enum: ["available", "busy", "offline"], nullable: true, example: "offline"),
                    new OA\Property(property: "is_active", type: "boolean", nullable: true, example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Personnel created"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $personnel = SecurityPersonnel::create($this->validatedNewPersonnel($request, null));

        return response()->json([
            'status' => 'success',
            'message' => 'Security personnel created successfully.',
            'personnel' => $personnel->load('securityTeam'),
        ], 201);
    }

    #[OA\Post(
        path: "/api/admin/teams/{team}/personnel",
        summary: "Add security personnel to a team",
        description: "Creates a new security account and assigns it to the given team. Body is the same as POST /security-personnel except security_team_id must not be sent (it is taken from the URL).",
        tags: ["Admin Teams", "Admin Security Personnel"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "team", description: "Security team ID", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["first_name", "last_name", "username", "phone", "password"],
                properties: [
                    new OA\Property(property: "first_name", type: "string", example: "Giorgi"),
                    new OA\Property(property: "last_name", type: "string", example: "Maisuradze"),
                    new OA\Property(property: "username", type: "string", example: "guard_alpha_2"),
                    new OA\Property(property: "phone", type: "string", example: "+995555770088"),
                    new OA\Property(property: "email", type: "string", format: "email", nullable: true),
                    new OA\Property(property: "password", type: "string", format: "password", example: "SecurePass1"),
                    new OA\Property(property: "status", type: "string", enum: ["available", "busy", "offline"], nullable: true),
                    new OA\Property(property: "is_active", type: "boolean", nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Personnel created and linked to team"),
            new OA\Response(response: 404, description: "Team not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function storeForTeam(Request $request, SecurityTeam $team): JsonResponse
    {
        $personnel = SecurityPersonnel::create($this->validatedNewPersonnel($request, $team->id));

        return response()->json([
            'status' => 'success',
            'message' => 'Security personnel added to team successfully.',
            'personnel' => $personnel->load('securityTeam'),
            'team' => $team->fresh()->load('personnel'),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedNewPersonnel(Request $request, ?int $forcedTeamId): array
    {
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:security_personnel,username',
            'phone' => 'required|string|max:50|unique:security_personnel,phone',
            'email' => 'nullable|email|max:255',
            'password' => 'required|string|min:8',
            'status' => 'nullable|in:available,busy,offline',
            'is_active' => 'sometimes|boolean',
        ];
        $rules['security_team_id'] = $forcedTeamId !== null
            ? 'prohibited'
            : 'nullable|exists:security_teams,id';

        $validated = $request->validate($rules);

        $validated['security_team_id'] = $forcedTeamId ?? ($validated['security_team_id'] ?? null);
        $validated['status'] = $validated['status'] ?? 'offline';
        $validated['is_active'] = array_key_exists('is_active', $validated)
            ? (bool) $validated['is_active']
            : true;

        return $validated;
    }

    #[OA\Put(
        path: "/api/admin/security-personnel/{id}",
        summary: "Update security personnel",
        description: "Updates profile fields, optional password, team assignment, operational status (available/busy/offline), and is_active.",
        tags: ["Admin Security Personnel"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", description: "Security personnel ID", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "first_name", type: "string"),
                    new OA\Property(property: "last_name", type: "string"),
                    new OA\Property(property: "username", type: "string"),
                    new OA\Property(property: "phone", type: "string"),
                    new OA\Property(property: "email", type: "string", format: "email", nullable: true),
                    new OA\Property(property: "password", type: "string", format: "password", nullable: true, description: "New password (omit to keep current)"),
                    new OA\Property(property: "security_team_id", type: "integer", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["available", "busy", "offline"]),
                    new OA\Property(property: "is_active", type: "boolean", description: "Account can log in when true"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Personnel updated"),
            new OA\Response(response: 404, description: "Not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        $personnel = SecurityPersonnel::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'username' => ['sometimes', 'string', 'max:255', Rule::unique('security_personnel', 'username')->ignore($personnel->id)],
            'phone' => ['sometimes', 'string', 'max:50', Rule::unique('security_personnel', 'phone')->ignore($personnel->id)],
            'email' => 'nullable|email|max:255',
            'password' => 'sometimes|nullable|string|min:8',
            'security_team_id' => 'nullable|exists:security_teams,id',
            'status' => 'sometimes|in:available,busy,offline',
            'is_active' => 'sometimes|boolean',
        ]);

        if (array_key_exists('password', $validated) && ($validated['password'] === null || $validated['password'] === '')) {
            unset($validated['password']);
        }

        $personnel->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Security personnel updated successfully.',
            'personnel' => $personnel->fresh()->load('securityTeam'),
        ]);
    }
}
