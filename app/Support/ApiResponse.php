<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(string $code, array $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => $code,
            'data' => $data,
            'meta' => self::normalizeMeta($meta),
        ], $status);
    }

    public static function error(string $code, string $message, int $status, array $errors = [], array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'errors' => $errors,
            'meta' => self::normalizeMeta($meta),
        ], $status);
    }

    private static function normalizeMeta(array $meta): object
    {
        return (object) $meta;
    }
}
