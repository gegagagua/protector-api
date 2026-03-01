<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\Client;
use App\Models\SecurityPersonnel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActorAndAbility
{
    public function handle(Request $request, Closure $next, string $actor, string $ability): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $expectedClass = match ($actor) {
            'client' => Client::class,
            'security' => SecurityPersonnel::class,
            'admin' => Admin::class,
            default => null,
        };

        if (!$expectedClass || !($user instanceof $expectedClass)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden.',
            ], 403);
        }

        $token = $user->currentAccessToken();

        if (!$token || !$token->can($ability)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient token ability.',
            ], 403);
        }

        return $next($request);
    }
}
