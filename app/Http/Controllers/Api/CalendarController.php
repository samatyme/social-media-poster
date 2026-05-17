<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'start' => 'required|date',
            'end'   => 'required|date|after:start',
        ]);

        $posts = Post::where('organization_id', $request->user()->organization_id)
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$request->start, $request->end])
            ->whereNotIn('status', ['cancelled'])
            ->with(['variants.socialAccount', 'postMedia.asset'])
            ->orderBy('scheduled_at')
            ->get();

        return response()->json($posts->map(fn($post) => [
            'id'           => $post->id,
            'title'        => $post->title ?: substr($post->base_content ?? '', 0, 50),
            'start'        => $post->scheduled_at->toIso8601String(),
            'status'       => $post->status,
            'platforms'    => $post->variants->pluck('platform')->unique()->values(),
            'accounts'     => $post->variants->map(fn($v) => [
                'id'       => $v->socialAccount->id,
                'platform' => $v->platform,
                'name'     => $v->socialAccount->account_name,
            ]),
            'media_count'  => $post->postMedia->count(),
        ]));
    }
}
