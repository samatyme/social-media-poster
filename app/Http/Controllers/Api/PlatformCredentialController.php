<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformCredentialController extends Controller
{
    private const PLATFORM_FIELDS = [
        'facebook'  => ['app_id', 'app_secret'],
        'instagram' => ['app_id', 'app_secret'],
        'x'         => ['api_key', 'api_secret', 'bearer_token'],
        'linkedin'  => ['client_id', 'client_secret'],
        'tiktok'    => ['client_key', 'client_secret'],
    ];

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $orgId = $request->user()->organization_id;
        $stored = PlatformCredential::where('organization_id', $orgId)->get()
            ->keyBy('platform')
            ->map(fn($c) => $c->toSafeArray());

        $result = [];
        foreach (self::PLATFORM_FIELDS as $platform => $fields) {
            $result[$platform] = [
                'platform'    => $platform,
                'fields'      => $fields,
                'configured'  => isset($stored[$platform]),
                'credentials' => $stored[$platform]['credentials'] ?? null,
                'updated_at'  => $stored[$platform]['updated_at'] ?? null,
            ];
        }

        return response()->json($result);
    }

    public function upsert(Request $request, string $platform): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless(array_key_exists($platform, self::PLATFORM_FIELDS), 404);

        $fields = self::PLATFORM_FIELDS[$platform];
        $request->validate(
            collect($fields)->mapWithKeys(fn($f) => [$f => 'required|string|max:512'])->all()
        );

        $orgId = $request->user()->organization_id;
        $cred = PlatformCredential::firstOrNew(['organization_id' => $orgId, 'platform' => $platform]);
        $cred->credentials = $request->only($fields);
        $cred->save();

        return response()->json($cred->toSafeArray());
    }

    public function destroy(Request $request, string $platform): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        PlatformCredential::where('organization_id', $request->user()->organization_id)
            ->where('platform', $platform)
            ->delete();

        return response()->json(['message' => 'Credentials removed.']);
    }

    // Returns raw (decrypted) credentials for internal use by OAuth controllers
    public static function getFor(int $orgId, string $platform): ?array
    {
        $cred = PlatformCredential::where('organization_id', $orgId)
            ->where('platform', $platform)
            ->first();

        return $cred?->credentials;
    }
}
