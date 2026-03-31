<?php

use App\Http\Controllers\Api\Auth\InitiateAuthController;
use App\Http\Controllers\Api\Auth\VerifyAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/initiate', InitiateAuthController::class);
Route::post('/auth/verify', VerifyAuthController::class);
