<?php

namespace App\Contracts;

interface SmsSender
{
    public function sendOtp(string $phoneNumber, string $otp): void;
}
