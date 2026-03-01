<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_registration_requires_name_and_surname_for_new_phone(): void
    {
        $sendOtp = $this->postJson('/api/client/send-otp', [
            'phone' => '+995555000111',
            'type' => 'registration',
        ]);

        $sendOtp->assertOk();
        $otp = $sendOtp->json('debug_otp');

        $verifyWithoutNames = $this->postJson('/api/client/verify-otp', [
            'phone' => '+995555000111',
            'code' => $otp,
        ]);

        $verifyWithoutNames->assertStatus(422);

        $this->travel(31)->seconds();

        $otpResponse = $this->postJson('/api/client/send-otp', [
            'phone' => '+995555000111',
            'type' => 'registration',
        ]);

        $otpResponse->assertOk();
        $newOtp = $otpResponse->json('debug_otp');

        $verify = $this->postJson('/api/client/verify-otp', [
            'phone' => '+995555000111',
            'code' => $newOtp,
            'first_name' => 'Giorgi',
            'last_name' => 'Gelashvili',
        ]);

        $verify->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('is_new_user', true);
    }
}
