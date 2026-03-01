<?php

namespace App\Services\Otp;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function __construct(private readonly OtpSender $sender)
    {
    }

    public function createAndSend(string $phone, string $type): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::where('phone', $phone)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        OtpCode::create([
            'phone' => $phone,
            'code' => substr(hash('sha256', $code . '|' . $phone . '|' . now()->timestamp), 0, 6),
            'code_hash' => Hash::make($code),
            'type' => $type,
            'attempt_count' => 0,
            'expires_at' => now()->addMinutes((int) config('services.otp.expire_minutes', 5)),
            'last_sent_at' => now(),
        ]);

        $this->sender->send($phone, $code);

        return $code;
    }
}
