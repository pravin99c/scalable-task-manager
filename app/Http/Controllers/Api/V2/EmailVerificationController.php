<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\EmailOwnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    public function send(Request $request, EmailOwnershipService $service): JsonResponse
    {
        $user = $request->user();
        if ($user->email_verified_at !== null) {
            return ApiResponse::message('Email already verified.');
        }

        $key = 'taskforge:verification:user:'.$user->getKey();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return ApiResponse::error('Too many verification requests.', 429);
        }

        DB::transaction(fn () => $service->issue($user));
        RateLimiter::hit($key, 600);

        return ApiResponse::message('Verification email queued.', 202);
    }

    public function verify(Request $request, EmailOwnershipService $service): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:40', 'max:256'],
        ]);

        $service->verify($request->user(), $data['token']);

        return ApiResponse::item(['email_verified' => true]);
    }
}
