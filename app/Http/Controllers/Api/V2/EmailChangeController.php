<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\EmailChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmailChangeController extends Controller
{
    public function update(Request $request, EmailChangeService $service): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->getKey())],
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The password is incorrect.'],
            ]);
        }

        if (strcasecmp($request->user()->email, Str::lower(trim($data['email']))) === 0) {
            throw ValidationException::withMessages([
                'email' => ['Please provide a different email address.'],
            ]);
        }

        $service->change($request->user(), $data['email']);

        // On the browser's stateful Sanctum path, invalidate the CURRENT web
        // session as well. A session middleware write at response completion
        // must not recreate an authenticated session after the email change.
        if ($request->hasSession() && Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // All old tokens and database sessions are revoked by the model event.
        return ApiResponse::message('Email changed. Sign in again and verify the new address.');
    }
}
