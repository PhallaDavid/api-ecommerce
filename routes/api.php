<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductVariantController;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password-otp', [AuthController::class, 'forgotPasswordOtp']);
Route::post('/reset-password-otp', [AuthController::class, 'resetPasswordWithOtp']);
Route::post('/email/verify-otp/send', [AuthController::class, 'sendEmailVerificationOtp']);
Route::post('/email/verify-otp', [AuthController::class, 'verifyEmailWithOtp']);
Route::post('/phone/verify-otp/send', [AuthController::class, 'sendPhoneVerificationOtp']);
Route::post('/phone/verify-otp', [AuthController::class, 'verifyPhoneWithOtp']);
Route::post('/phone/forgot-password/send', [AuthController::class, 'forgotPasswordPhoneOtp']);
Route::post('/phone/forgot-password/reset', [AuthController::class, 'resetPasswordWithPhoneOtp']);
Route::post('/chat', [ChatController::class, 'chat']);
Route::middleware('auth:sanctum')->post('/change-password', [AuthController::class, 'changePassword']);
Route::middleware('auth:sanctum')->get('/profile', [AuthController::class, 'profile']);
Route::middleware('auth:sanctum')->put('/profile', [AuthController::class, 'updateProfile']);
Route::middleware('auth:sanctum')->post('/profile', [AuthController::class, 'updateProfile']);
Route::apiResource('banners', BannerController::class);
Route::apiResource('categories', CategoryController::class);
Route::apiResource('brands', BrandController::class);
Route::apiResource('products', ProductController::class);
Route::apiResource('products.variants', ProductVariantController::class);
