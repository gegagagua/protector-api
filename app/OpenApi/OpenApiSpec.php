<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Proector API',
    description: 'Backend API documentation for Proector mobile and admin platforms.'
)]
#[OA\Server(
    url: '/api',
    description: 'API base path'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token',
    description: 'Use Bearer {token} from login or OTP verification endpoints.'
)]
final class OpenApiSpec
{
}
