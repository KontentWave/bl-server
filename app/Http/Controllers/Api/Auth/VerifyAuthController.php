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
            phoneNumber: $request->string('phone_number')->toString(),
            password: $request->string('password')->toString(),
            publicKey: $request->string('public_key')->toString(),
            signature: $request->string('signature')->toString(),
        );

        return ApiResponse::success(
            code: 'auth.verified',
            data: [
                'phone_number' => $deviceBinding->phone_number,
                'verified_at' => $deviceBinding->verified_at->toIso8601String(),
            ],
        );
    }
}
