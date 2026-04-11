<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceIcon;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class ServiceController extends Controller
{
    #[OA\Get(
        path: '/api/admin/services',
        summary: 'List guarding services',
        description: 'Returns all catalog services (hourly/daily GEL, icon, vehicle requirement) for admin and dashboard forms.',
        tags: ['Admin Services'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Service list')]
    )]
    public function index(): JsonResponse
    {
        $services = Service::query()->ordered()->get();

        return response()->json([
            'status' => 'success',
            'services' => $services,
        ]);
    }

    #[OA\Post(
        path: '/api/admin/services',
        summary: 'Create guarding service',
        tags: ['Admin Services'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created'), new OA\Response(response: 422, description: 'Validation error')]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:64|unique:services,slug',
            'name_en' => 'required|string|max:255',
            'name_ka' => 'required|string|max:255',
            'description_en' => 'nullable|string',
            'description_ka' => 'nullable|string',
            'icon' => ['required', Rule::enum(ServiceIcon::class)],
            'hourly_rate' => 'required|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'requires_vehicle' => 'sometimes|boolean',
            'team_service_type' => 'required|in:armed,unarmed',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:65535',
        ]);

        $validated['daily_rate'] = $validated['daily_rate'] ?? 0;
        $validated['requires_vehicle'] = (bool) ($validated['requires_vehicle'] ?? false);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        $service = Service::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Service created successfully.',
            'service' => $service,
        ], 201);
    }

    #[OA\Put(
        path: '/api/admin/services/{id}',
        summary: 'Update guarding service',
        tags: ['Admin Services'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Updated'), new OA\Response(response: 422, description: 'Validation error')]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $service = Service::findOrFail($id);

        $validated = $request->validate([
            'slug' => ['sometimes', 'string', 'max:64', Rule::unique('services', 'slug')->ignore($service->id)],
            'name_en' => 'sometimes|string|max:255',
            'name_ka' => 'sometimes|string|max:255',
            'description_en' => 'nullable|string',
            'description_ka' => 'nullable|string',
            'icon' => ['sometimes', Rule::enum(ServiceIcon::class)],
            'hourly_rate' => 'sometimes|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'requires_vehicle' => 'sometimes|boolean',
            'team_service_type' => 'sometimes|in:armed,unarmed',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:65535',
        ]);

        $service->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Service updated successfully.',
            'service' => $service->fresh(),
        ]);
    }
}
