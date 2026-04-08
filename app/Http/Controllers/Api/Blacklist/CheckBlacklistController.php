<?php

namespace App\Http\Controllers\Api\Blacklist;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blacklist\CheckBlacklistRequest;
use App\Services\BlacklistQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CheckBlacklistController extends Controller
{
    public function __invoke(CheckBlacklistRequest $request, BlacklistQueryService $blacklistQueryService): JsonResponse
    {
        $result = $blacklistQueryService->check(
            targetHash: strtolower($request->string('target_hash')->toString()),
            publicKey: $request->input('public_key'),
            signature: $request->input('signature'),
        );

        return ApiResponse::success(
            code: 'blacklist.checked',
            data: $result,
        );
    }
}
