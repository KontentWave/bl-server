<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\InitiateOwnerAuthRequest;
use App\Services\OtpChallengeService;
use App\Support\ApiResponse;
use App\Support\PhoneNumberRedactor;
use Illuminate\Http\JsonResponse;

class InitiateOwnerAuthController extends Controller
{
    public function __invoke(InitiateOwnerAuthRequest $request, OtpChallengeService $otpChallengeService): JsonResponse
    {
        [$otpChallenge] = $otpChallengeService->issueForOwner(
            $request->string('phone_number')->toString(),
        );

        return ApiResponse::success(
            code: 'auth.sms_initiated',
            data: [
                'challenge_id' => $otpChallenge->challenge_id,
                'masked_phone_number' => PhoneNumberRedactor::redact($otpChallenge->phone_number),
                'otp_expires_at' => $otpChallenge->expires_at->toIso8601String(),
            ],
            status: 201,
        );
    }
}
