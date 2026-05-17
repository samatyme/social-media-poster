<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(private MediaService $mediaService) {}

    public function index(Request $request): JsonResponse
    {
        $assets = MediaAsset::where('organization_id', $request->user()->organization_id)
            ->when($request->type, function ($q) use ($request) {
                match ($request->type) {
                    'image' => $q->where('mime_type', 'like', 'image/%'),
                    'video' => $q->where('mime_type', 'like', 'video/%'),
                    default => null,
                };
            })
            ->when($request->search, fn($q) => $q->where('original_name', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 24);

        return response()->json($assets);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:512000', // 500MB max
        ]);

        $asset = $this->mediaService->upload($request->file('file'), $request->user());
        return response()->json($asset, 201);
    }

    public function destroy(Request $request, MediaAsset $mediaAsset): JsonResponse
    {
        abort_unless($mediaAsset->organization_id === $request->user()->organization_id, 403);

        $this->mediaService->delete($mediaAsset);
        return response()->json(['message' => 'Media deleted.']);
    }
}
