<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Services\ReportSubmissionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StoreReportController extends Controller
{
    public function __invoke(StoreReportRequest $request, ReportSubmissionService $reportSubmissionService): JsonResponse
    {
        $result = $reportSubmissionService->submit(
            clientPhoneNumber: $request->string('client_phone_number')->toString(),
            feature: $request->string('feature')->toString(),
            publicKey: $request->string('public_key')->toString(),
            signature: $request->string('signature')->toString(),
        );

        return ApiResponse::success(
            code: 'report.created',
            data: $result,
            status: 201,
        );
    }
}
