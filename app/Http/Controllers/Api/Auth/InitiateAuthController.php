<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\InitiateAuthRequest;
use App\Services\OtpChallengeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class InitiateAuthController extends Controller
{
    public function __invoke(InitiateAuthRequest $request, OtpChallengeService $otpChallengeService): JsonResponse
    {
        [$otpChallenge] = $otpChallengeService->issue(
            $request->string('ad_url')->toString(),
        );

        return ApiResponse::success(
            code: 'auth.sms_initiated',
            data: [
                'challenge_id' => $otpChallenge->challenge_id,
                'masked_phone_number' => \App\Support\PhoneNumberRedactor::redact($otpChallenge->phone_number),
                'otp_expires_at' => $otpChallenge->expires_at->toIso8601String(),
            ],
            status: 201,
        );
    }
}
