<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\InitiateAuthRequest;
use App\Services\AuthChallengeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class InitiateAuthController extends Controller
{
    public function __invoke(InitiateAuthRequest $request, AuthChallengeService $authChallengeService): JsonResponse
    {
        [$authChallenge, $plainTextPassword] = $authChallengeService->issue(
            $request->string('phone_number')->toString(),
        );

        return ApiResponse::success(
            code: 'auth.initiated',
            data: [
                'phone_number' => $authChallenge->phone_number,
                'password' => $plainTextPassword,
                'expires_at' => $authChallenge->expires_at->toIso8601String(),
            ],
            status: 201,
        );
    }
}
