<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function store(
        LoginRequest $request
    ): AuthenticatedUserResource {
        $request->authenticate();
        $request->session()->regenerate();

        return new AuthenticatedUserResource(
            $request->user()->load([
                'tenant',
                'roles.permissions',
            ])
        );
    }

    public function show(
        Request $request
    ): AuthenticatedUserResource {
        return new AuthenticatedUserResource(
            $request->user()->load([
                'tenant',
                'roles.permissions',
            ])
        );
    }

    public function destroy(
        Request $request
    ): JsonResponse {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
