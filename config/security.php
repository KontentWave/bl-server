<?php

return [
    'api_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 60),
    'initiate_per_minute' => (int) env('AUTH_INITIATE_PER_MINUTE', 3),
    'initiate_per_hour' => (int) env('AUTH_INITIATE_PER_HOUR', 10),
    'verify_per_minute' => (int) env('AUTH_VERIFY_PER_MINUTE', 10),
    'otp_verification_attempts' => (int) env('OTP_VERIFICATION_ATTEMPTS', 5),
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),
    'sms_recipient_per_hour' => (int) env('SMS_RECIPIENT_PER_HOUR', 3),
    'sms_recipient_per_day' => (int) env('SMS_RECIPIENT_PER_DAY', 5),
    'sms_global_per_hour' => (int) env('SMS_GLOBAL_PER_HOUR', 20),
    'sms_global_per_day' => (int) env('SMS_GLOBAL_PER_DAY', 100),
];
