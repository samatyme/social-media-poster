<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Publishers\MockPublisher;
use App\Publishers\PublisherFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accounts = SocialAccount::where('organization_id', $request->user()->organization_id)
            ->orderBy('platform')
            ->get();

        return response()->json($accounts->map(fn($a) => [
            'id'                  => $a->id,
            'platform'            => $a->platform,
            'account_name'        => $a->account_name,
            'account_handle'      => $a->account_handle,
            'status'              => $a->status,
            'avatar_url'          => $a->avatar_url,
            'is_healthy'          => $a->isHealthy(),
            'is_token_expired'    => $a->isTokenExpired(),
            'token_expires_at'    => $a->token_expires_at,
            'last_verified_at'    => $a->last_verified_at,
        ]));
    }

    public function connect(Request $request): JsonResponse
    {
        $request->validate([
            'platform'       => 'required|in:facebook,instagram,tiktok,x,linkedin',
            'method'         => 'nullable|in:oauth,manual',
            'account_name'   => 'required_if:method,manual|nullable|string|max:255',
            'account_handle' => 'nullable|string|max:255',
            'access_token'   => 'required_if:method,manual|nullable|string',
            'external_id'    => 'nullable|string|max:255',
        ]);

        $orgId = $request->user()->organization_id;

        // Manual token entry — store the provided token directly
        if ($request->method === 'manual') {
            $account = SocialAccount::updateOrCreate(
                [
                    'organization_id'    => $orgId,
                    'platform'           => $request->platform,
                    'external_account_id'=> $request->external_id ?: ('manual_' . uniqid()),
                ],
                [
                    'account_name'     => $request->account_name,
                    'account_handle'   => $request->account_handle ?? '',
                    'access_token'     => $request->access_token,
                    'refresh_token'    => null,
                    'token_expires_at' => null,
                    'status'           => 'active',
                    'last_verified_at' => now(),
                    'deleted_at'       => null,
                ]
            );
            return response()->json($account, 201);
        }

        if (env('PUBLISHER_MODE', 'mock') === 'mock') {
            $publisher = new MockPublisher();
            $account   = $publisher->connectAccount([
                'organization_id' => $orgId,
                'platform'        => $request->platform,
                'account_name'    => $request->account_name,
                'account_handle'  => $request->account_handle,
            ]);
            return response()->json($account, 201);
        }

        // Verify org has credentials configured for this platform
        try {
            PublisherFactory::make($request->platform, $orgId);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Real OAuth: return the redirect URL for the frontend to navigate to
        return response()->json([
            'redirect_url' => url("/api/oauth/{$request->platform}/redirect?org={$orgId}"),
        ]);
    }

    public function destroy(Request $request, SocialAccount $socialAccount): JsonResponse
    {
        abort_unless($socialAccount->organization_id === $request->user()->organization_id, 403);
        abort_unless($request->user()->isAdmin(), 403);

        $socialAccount->update(['status' => 'disconnected']);
        $socialAccount->delete();

        return response()->json(['message' => 'Account disconnected.']);
    }

    public function verify(Request $request, SocialAccount $socialAccount): JsonResponse
    {
        abort_unless($socialAccount->organization_id === $request->user()->organization_id, 403);

        $socialAccount->update(['last_verified_at' => now(), 'status' => 'active']);

        return response()->json(['message' => 'Account verified.', 'account' => $socialAccount->fresh()]);
    }
}
