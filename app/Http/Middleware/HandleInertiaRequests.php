<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;
use Laravel\Sanctum\PersonalAccessToken;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        // Web routes don't run Sanctum middleware, so resolve the user from
        // the Bearer token in localStorage that the frontend sends on XHR.
        // For Inertia full-page loads (no Authorization header), fall back to null.
        $user = $request->user();
        if (!$user) {
            $bearerToken = $request->bearerToken();
            if ($bearerToken) {
                $token = PersonalAccessToken::findToken($bearerToken);
                $user  = $token?->tokenable;
            }
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? $user->load('organization') : null,
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error'   => fn() => $request->session()->get('error'),
            ],
            'platform_rules' => config('platform_rules'),
        ]);
    }
}
