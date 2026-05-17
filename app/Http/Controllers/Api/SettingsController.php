<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('organization');

        return response()->json([
            'user'         => $user->only('id', 'name', 'email', 'role', 'timezone', 'avatar_path'),
            'organization' => $user->organization->only(
                'id', 'name', 'slug', 'plan', 'status', 'timezone',
                'max_social_accounts', 'max_scheduled_posts', 'max_team_members',
                'feature_flags', 'settings'
            ),
            'platform_rules' => config('platform_rules'),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'timezone' => 'required|timezone',
        ]);

        $request->user()->update($request->only('name', 'timezone'));
        return response()->json($request->user()->fresh());
    }

    public function updateOrganization(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->validate([
            'name'     => 'required|string|max:255',
            'timezone' => 'required|timezone',
        ]);

        $request->user()->organization->update($request->only('name', 'timezone'));
        return response()->json($request->user()->organization->fresh());
    }

    public function updateFeatureFlags(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->validate([
            'feature_flags' => 'required|array',
        ]);

        $org = $request->user()->organization;
        $org->update(['feature_flags' => array_merge($org->feature_flags ?? [], $request->feature_flags)]);

        return response()->json($org->fresh());
    }
}
