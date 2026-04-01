<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyAuthRequest;
use App\Services\AuthVerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class VerifyAuthController extends Controller
{
    public function __invoke(VerifyAuthRequest $request, AuthVerificationService $authVerificationService): JsonResponse
    {
        $deviceBinding = $authVerificationService->verify(
            challengeId: $request->string('challenge_id')->toString(),
            otp: $request->string('otp')->toString(),
            publicKey: $request->string('public_key')->toString(),
            signature: $request->string('signature')->toString(),
        );

        return ApiResponse::success(
            code: 'auth.verified',
            data: [
                'challenge_id' => $request->string('challenge_id')->toString(),
                'masked_phone_number' => \App\Support\PhoneNumberRedactor::redact($deviceBinding->phone_number),
                'verified_at' => $deviceBinding->verified_at->toIso8601String(),
            ],
        );
    }
}
