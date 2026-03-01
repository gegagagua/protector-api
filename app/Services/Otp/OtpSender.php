<?php

namespace App\Services\Otp;

interface OtpSender
{
    public function send(string $phone, string $code): void;
}
