<?php

namespace Tests\Feature;

use App\Enums\SecurityPersonnelRole;
use App\Models\Admin;
use App\Models\Client;
use App\Models\SecurityPersonnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_token_cannot_access_admin_routes(): void
    {
        $client = Client::create([
            'first_name' => 'Test',
            'last_name' => 'Client',
            'phone' => '+995500000001',
            'verification_status' => 'verified',
            'phone_verified_at' => now(),
        ]);

        $token = $client->createToken('client-auth', ['client'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_admin_token_cannot_access_client_routes(): void
    {
        $admin = Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'username' => 'admin01',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $token = $admin->createToken('admin-auth', ['admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/client/profile');

        $response->assertStatus(403);
    }

    public function test_security_token_cannot_access_admin_routes(): void
    {
        $security = SecurityPersonnel::create([
            'first_name' => 'Guard',
            'last_name' => 'One',
            'username' => 'guard01',
            'phone' => '+995500000099',
            'password' => 'password123',
            'role' => SecurityPersonnelRole::Driver,
            'status' => 'available',
            'is_active' => true,
        ]);

        $token = $security->createToken('security-auth', ['security'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/payments');

        $response->assertStatus(403);
    }
}
