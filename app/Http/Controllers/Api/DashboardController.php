<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PublishingLog;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $postCounts = Post::where('organization_id', $orgId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $upcoming = Post::where('organization_id', $orgId)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->with(['variants.socialAccount', 'creator'])
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();

        $recentActivity = PublishingLog::whereHas('post', fn($q) => $q->where('organization_id', $orgId))
            ->with(['post', 'socialAccount'])
            ->latest()
            ->limit(10)
            ->get();

        $connectedAccounts = SocialAccount::where('organization_id', $orgId)
            ->where('status', 'active')
            ->get(['id', 'platform', 'account_name', 'account_handle', 'avatar_url', 'status']);

        return response()->json([
            'stats' => [
                'drafts'             => $postCounts['draft'] ?? 0,
                'scheduled'          => $postCounts['scheduled'] ?? 0,
                'published'          => ($postCounts['published'] ?? 0) + ($postCounts['partially_published'] ?? 0),
                'failed'             => $postCounts['failed'] ?? 0,
                'pending_approval'   => $postCounts['pending_approval'] ?? 0,
            ],
            'upcoming_posts'      => $upcoming,
            'recent_activity'     => $recentActivity,
            'connected_accounts'  => $connectedAccounts,
        ]);
    }
}
