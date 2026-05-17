<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $members = User::where('organization_id', $request->user()->organization_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'avatar_path', 'created_at']);

        return response()->json($members);
    }

    public function invite(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role'  => 'required|in:admin,editor,approver,viewer',
        ]);

        $org = $request->user()->organization;
        if ($org->users()->count() >= $org->max_team_members) {
            return response()->json(['message' => 'Team member limit reached for your plan.'], 422);
        }

        $member = User::create([
            'organization_id' => $request->user()->organization_id,
            'name'            => $request->name,
            'email'           => $request->email,
            'role'            => $request->role,
            'password'        => Hash::make(Str::random(16)), // temp password
        ]);

        // TODO: Send invitation email with password reset link

        return response()->json($member, 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($user->organization_id === $request->user()->organization_id, 403);
        abort_if($user->id === $request->user()->id, 422, 'Cannot change your own role.');

        $request->validate(['role' => 'required|in:admin,editor,approver,viewer']);

        $user->update(['role' => $request->role]);
        return response()->json($user->fresh());
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($user->organization_id === $request->user()->organization_id, 403);
        abort_if($user->id === $request->user()->id, 422, 'Cannot remove yourself.');

        $user->delete();
        return response()->json(['message' => 'Team member removed.']);
    }
}
