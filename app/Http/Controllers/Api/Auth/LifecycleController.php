<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LifecycleRequest;
use App\Services\AuthLifecycleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LifecycleController extends Controller
{
    public function __invoke(LifecycleRequest $request, AuthLifecycleService $service): JsonResponse
    {
        $result = $service->handle($request->is('api/auth/renew') ? 'auth.renew' : 'auth.status',
            $request->validated(), $request->ip());
        if (isset($result['error'])) {
            throw $result['error'];
        }

        return ApiResponse::success($result['code'], $result['data']);
    }
}
