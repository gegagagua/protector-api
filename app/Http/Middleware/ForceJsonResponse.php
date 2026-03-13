<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');

            $contentType = (string) $request->header('Content-Type', '');
            $rawBody = trim((string) $request->getContent());
            $hasJsonBody = $rawBody !== '' && str_contains(strtolower($contentType), 'application/json');
            $expectsMutableBody = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);

            if ($expectsMutableBody && $hasJsonBody) {
                json_decode($rawBody, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 422,
                        'message' => 'Invalid JSON body.',
                        'errors' => [
                            'json' => [
                                'Malformed JSON payload. Check commas, quotes, and brackets.',
                            ],
                        ],
                    ], 422);
                }
            }
        }

        return $next($request);
    }
}
