<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\CreatePostRequest;
use App\Http\Requests\Post\SchedulePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(private PostService $postService) {}

    public function index(Request $request): JsonResponse
    {
        $posts = Post::where('organization_id', $request->user()->organization_id)
            ->with(['variants.socialAccount', 'postMedia.asset', 'creator'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where(fn($q2) =>
                $q2->where('title', 'like', "%{$request->search}%")
                   ->orWhere('base_content', 'like', "%{$request->search}%")
            ))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($posts);
    }

    public function store(CreatePostRequest $request): JsonResponse
    {
        $post = $this->postService->create($request->user(), $request->validated());
        return response()->json($post, 201);
    }

    public function show(Request $request, Post $post): JsonResponse
    {
        $this->authorizePost($request, $post);

        $post->load([
            'variants.socialAccount',
            'variants.publishingLogs',
            'postMedia.asset',
            'creator',
            'approver',
            'comments.user',
            'publishingLogs.socialAccount',
        ]);

        return response()->json($post);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorizePost($request, $post);

        if (!in_array($post->status, ['draft', 'approved'])) {
            return response()->json(['message' => 'Only draft or approved posts can be edited.'], 422);
        }

        $post = $this->postService->update($post, $request->validated());
        return response()->json($post);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorizePost($request, $post);

        if (in_array($post->status, ['publishing'])) {
            return response()->json(['message' => 'Cannot delete a post that is currently publishing.'], 422);
        }

        $post->delete();
        return response()->json(['message' => 'Post deleted.']);
    }

    public function schedule(SchedulePostRequest $request, Post $post): JsonResponse
    {
        $this->authorizePost($request, $post);
        $post = $this->postService->schedule($post, $request->scheduled_at, $request->timezone ?? 'UTC');
        return response()->json($post);
    }

    public function publishNow(Request $request, Post $post): JsonResponse
    {
        $this->authorizePost($request, $post);
        $this->postService->publishNow($post);
        return response()->json(['message' => 'Post is being published.', 'post' => $post->fresh()]);
    }

    public function cancel(Request $request, Post $post): JsonResponse
    {
        $this->authorizePost($request, $post);
        $post = $this->postService->cancel($post);
        return response()->json($post);
    }

    public function duplicate(Request $request, Post $post): JsonResponse
    {
        $this->authorizePost($request, $post);
        $newPost = $this->postService->duplicate($post, $request->user());
        return response()->json($newPost, 201);
    }

    public function submitForApproval(Request $request, Post $post): JsonResponse
    {
        $this->authorizePost($request, $post);
        $post = $this->postService->submitForApproval($post);
        return response()->json($post);
    }

    public function approve(Request $request, Post $post): JsonResponse
    {
        $this->authorizePost($request, $post);
        if (!$request->user()->isApprover()) {
            return response()->json(['message' => 'You do not have permission to approve posts.'], 403);
        }
        $post = $this->postService->approve($post, $request->user(), $request->notes);
        return response()->json($post);
    }

    public function reject(Request $request, Post $post): JsonResponse
    {
        $this->authorizePost($request, $post);
        if (!$request->user()->isApprover()) {
            return response()->json(['message' => 'You do not have permission to reject posts.'], 403);
        }
        $this->validateRejection($request);
        $post = $this->postService->reject($post, $request->user(), $request->notes);
        return response()->json($post);
    }

    private function authorizePost(Request $request, Post $post): void
    {
        abort_unless($post->organization_id === $request->user()->organization_id, 403);
    }

    private function validateRejection(Request $request): void
    {
        $request->validate(['notes' => 'required|string|min:10']);
    }
}
