<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailOwnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class TaskForgeSessionController extends Controller
{
    /**
     * A same-origin, HTTP-only cookie session for the React app. No API token
     * is sent to JavaScript or saved in browser local/session storage.
     */
    public function session(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user(),
            'csrf_token' => $request->session()->token(),
        ])->header('Cache-Control', 'no-store');
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($data)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        return $this->session($request);
    }

    public function register(Request $request, EmailOwnershipService $ownership): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = DB::transaction(function () use ($data, $ownership) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->refresh();
            $ownership->issue($user);
            return $user;
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $this->session($request)->setStatusCode(201);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->session($request);
    }
}
