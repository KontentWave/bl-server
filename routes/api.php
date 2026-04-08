<?php

use App\Http\Controllers\Api\Auth\InitiateAuthController;
use App\Http\Controllers\Api\Auth\VerifyAuthController;
use App\Http\Controllers\Api\Blacklist\CheckBlacklistController;
use App\Http\Controllers\Api\Report\StoreReportController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/initiate', InitiateAuthController::class);
Route::post('/auth/verify', VerifyAuthController::class);
Route::post('/reports', StoreReportController::class);
Route::post('/blacklist/check', CheckBlacklistController::class);
