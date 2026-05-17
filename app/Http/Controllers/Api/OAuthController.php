<?php

namespace App\Http\Controllers\Api;

use App\Models\Organization;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class OAuthController extends Controller
{
    // -------------------------------------------------------------------------
    // Entry point: redirect the browser to the platform's OAuth consent screen
    // -------------------------------------------------------------------------
    public function redirect(Request $request, string $platform)
    {
        $orgId = (int) $request->query('org');
        $creds = PlatformCredentialController::getFor($orgId, $platform);

        if (!$creds) {
            return redirect(config('app.url') . '/accounts?error=no_credentials');
        }

        $state = Crypt::encryptString(json_encode(['org_id' => $orgId, 'platform' => $platform]));

        $url = match ($platform) {
            'facebook'  => $this->facebookAuthUrl($creds, $state),
            'instagram' => $this->facebookAuthUrl($creds, $state, instagram: true),
            'x'         => $this->xAuthUrl($creds, $state),
            'linkedin'  => $this->linkedinAuthUrl($creds, $state),
            'tiktok'    => $this->tiktokAuthUrl($creds, $state),
            default     => null,
        };

        if (!$url) {
            return redirect(config('app.url') . '/accounts?error=unsupported_platform');
        }

        return redirect($url);
    }

    // -------------------------------------------------------------------------
    // Callback: exchange the auth code for a token and save the social account
    // -------------------------------------------------------------------------
    public function callback(Request $request, string $platform)
    {
        if ($request->has('error')) {
            return redirect(config('app.url') . '/accounts?error=' . urlencode($request->query('error_description', 'OAuth denied')));
        }

        try {
            $state  = json_decode(Crypt::decryptString($request->query('state', '')), true);
            $orgId  = (int) ($state['org_id'] ?? 0);
            $creds  = PlatformCredentialController::getFor($orgId, $platform);

            if (!$creds || !$orgId) {
                return redirect(config('app.url') . '/accounts?error=invalid_state');
            }

            $account = match ($platform) {
                'facebook'  => $this->handleFacebookCallback($request, $orgId, $creds),
                'instagram' => $this->handleFacebookCallback($request, $orgId, $creds, instagram: true),
                'x'         => $this->handleXCallback($request, $orgId, $creds),
                'linkedin'  => $this->handleLinkedinCallback($request, $orgId, $creds),
                'tiktok'    => $this->handleTiktokCallback($request, $orgId, $creds),
                default     => throw new \RuntimeException('Unsupported platform'),
            };

            return redirect(config('app.url') . '/accounts?connected=' . $account->id);

        } catch (\Throwable $e) {
            return redirect(config('app.url') . '/accounts?error=' . urlencode($e->getMessage()));
        }
    }

    // =========================================================================
    // FACEBOOK / INSTAGRAM
    // =========================================================================

    private function facebookAuthUrl(array $creds, string $state, bool $instagram = false): string
    {
        $scopes = $instagram
            ? 'instagram_basic,instagram_content_publish,pages_show_list,pages_read_engagement'
            : 'pages_manage_posts,pages_read_engagement,pages_show_list,public_profile';

        return 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
            'client_id'     => $creds['app_id'],
            'redirect_uri'  => $this->callbackUrl($instagram ? 'instagram' : 'facebook'),
            'scope'         => $scopes,
            'state'         => $state,
            'response_type' => 'code',
        ]);
    }

    private function handleFacebookCallback(Request $request, int $orgId, array $creds, bool $instagram = false): SocialAccount
    {
        $platform    = $instagram ? 'instagram' : 'facebook';
        $redirectUri = $this->callbackUrl($platform);

        // Exchange code for short-lived token
        $tokenRes = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
            'client_id'     => $creds['app_id'],
            'client_secret' => $creds['app_secret'],
            'redirect_uri'  => $redirectUri,
            'code'          => $request->query('code'),
        ])->throw()->json();

        $shortToken = $tokenRes['access_token'];

        // Exchange for long-lived token (60 days)
        $longRes = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $creds['app_id'],
            'client_secret'     => $creds['app_secret'],
            'fb_exchange_token' => $shortToken,
        ])->throw()->json();

        $longToken  = $longRes['access_token'];
        $expiresIn  = $longRes['expires_in'] ?? (60 * 24 * 3600);

        if ($instagram) {
            return $this->connectInstagramAccount($orgId, $longToken, $expiresIn, $creds);
        }

        return $this->connectFacebookPage($orgId, $longToken, $expiresIn);
    }

    private function connectFacebookPage(int $orgId, string $userToken, int $expiresIn): SocialAccount
    {
        // Get user's managed pages and their permanent page tokens
        $pagesRes = Http::get('https://graph.facebook.com/v19.0/me/accounts', [
            'access_token' => $userToken,
            'fields'       => 'id,name,access_token,picture',
        ])->throw()->json();

        $pages = $pagesRes['data'] ?? [];

        if (empty($pages)) {
            throw new \RuntimeException('No Facebook Pages found. Make sure your Facebook account manages at least one Page.');
        }

        // Use the first page (future enhancement: let user pick)
        $page = $pages[0];

        return SocialAccount::updateOrCreate(
            ['organization_id' => $orgId, 'platform' => 'facebook', 'external_account_id' => $page['id']],
            [
                'account_name'    => $page['name'],
                'account_handle'  => '@' . strtolower(str_replace(' ', '', $page['name'])),
                'access_token'    => $page['access_token'], // page token (never expires)
                'refresh_token'   => $userToken,
                'token_expires_at'=> now()->addSeconds($expiresIn),
                'status'          => 'active',
                'avatar_url'      => $page['picture']['data']['url'] ?? null,
                'last_verified_at'=> now(),
                'deleted_at'      => null,
            ]
        );
    }

    private function connectInstagramAccount(int $orgId, string $userToken, int $expiresIn, array $creds): SocialAccount
    {
        // Get pages first, then find linked Instagram business account
        $pagesRes = Http::get('https://graph.facebook.com/v19.0/me/accounts', [
            'access_token' => $userToken,
            'fields'       => 'id,name,access_token,instagram_business_account',
        ])->throw()->json();

        foreach ($pagesRes['data'] ?? [] as $page) {
            if (!isset($page['instagram_business_account'])) continue;

            $igId  = $page['instagram_business_account']['id'];
            $igRes = Http::get("https://graph.facebook.com/v19.0/{$igId}", [
                'access_token' => $page['access_token'],
                'fields'       => 'id,name,username,profile_picture_url',
            ])->throw()->json();

            return SocialAccount::updateOrCreate(
                ['organization_id' => $orgId, 'platform' => 'instagram', 'external_account_id' => $igId],
                [
                    'account_name'     => $igRes['name'] ?? $igRes['username'],
                    'account_handle'   => '@' . ($igRes['username'] ?? ''),
                    'access_token'     => $page['access_token'],
                    'refresh_token'    => $userToken,
                    'token_expires_at' => now()->addSeconds($expiresIn),
                    'status'           => 'active',
                    'avatar_url'       => $igRes['profile_picture_url'] ?? null,
                    'last_verified_at' => now(),
                    'deleted_at'       => null,
                ]
            );
        }

        throw new \RuntimeException('No Instagram Business Account found linked to your Facebook Pages.');
    }

    // =========================================================================
    // X (TWITTER) — OAuth 2.0 PKCE
    // =========================================================================

    private function xAuthUrl(array $creds, string $state): string
    {
        return 'https://twitter.com/i/oauth2/authorize?' . http_build_query([
            'response_type'         => 'code',
            'client_id'             => $creds['api_key'],
            'redirect_uri'          => $this->callbackUrl('x'),
            'scope'                 => 'tweet.read tweet.write users.read offline.access',
            'state'                 => $state,
            'code_challenge'        => 'challenge',
            'code_challenge_method' => 'plain',
        ]);
    }

    private function handleXCallback(Request $request, int $orgId, array $creds): SocialAccount
    {
        $tokenRes = Http::withBasicAuth($creds['api_key'], $creds['api_secret'])
            ->asForm()
            ->post('https://api.twitter.com/2/oauth2/token', [
                'code'          => $request->query('code'),
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $this->callbackUrl('x'),
                'code_verifier' => 'challenge',
            ])->throw()->json();

        $userRes = Http::withToken($tokenRes['access_token'])
            ->get('https://api.twitter.com/2/users/me', ['user.fields' => 'name,username,profile_image_url'])
            ->throw()->json();

        $user = $userRes['data'];

        return SocialAccount::updateOrCreate(
            ['organization_id' => $orgId, 'platform' => 'x', 'external_account_id' => $user['id']],
            [
                'account_name'     => $user['name'],
                'account_handle'   => '@' . $user['username'],
                'access_token'     => $tokenRes['access_token'],
                'refresh_token'    => $tokenRes['refresh_token'] ?? null,
                'token_expires_at' => isset($tokenRes['expires_in']) ? now()->addSeconds($tokenRes['expires_in']) : null,
                'status'           => 'active',
                'avatar_url'       => $user['profile_image_url'] ?? null,
                'last_verified_at' => now(),
                'deleted_at'       => null,
            ]
        );
    }

    // =========================================================================
    // LINKEDIN
    // =========================================================================

    private function linkedinAuthUrl(array $creds, string $state): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $creds['client_id'],
            'redirect_uri'  => $this->callbackUrl('linkedin'),
            'scope'         => 'openid profile w_member_social',
            'state'         => $state,
        ]);
    }

    private function handleLinkedinCallback(Request $request, int $orgId, array $creds): SocialAccount
    {
        $tokenRes = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type'    => 'authorization_code',
            'code'          => $request->query('code'),
            'redirect_uri'  => $this->callbackUrl('linkedin'),
            'client_id'     => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
        ])->throw()->json();

        $profileRes = Http::withToken($tokenRes['access_token'])
            ->get('https://api.linkedin.com/v2/userinfo')
            ->throw()->json();

        return SocialAccount::updateOrCreate(
            ['organization_id' => $orgId, 'platform' => 'linkedin', 'external_account_id' => $profileRes['sub']],
            [
                'account_name'     => $profileRes['name'],
                'account_handle'   => $profileRes['email'] ?? '',
                'access_token'     => $tokenRes['access_token'],
                'refresh_token'    => $tokenRes['refresh_token'] ?? null,
                'token_expires_at' => isset($tokenRes['expires_in']) ? now()->addSeconds($tokenRes['expires_in']) : null,
                'status'           => 'active',
                'avatar_url'       => $profileRes['picture'] ?? null,
                'last_verified_at' => now(),
                'deleted_at'       => null,
            ]
        );
    }

    // =========================================================================
    // TIKTOK
    // =========================================================================

    private function tiktokAuthUrl(array $creds, string $state): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
            'client_key'    => $creds['client_key'],
            'response_type' => 'code',
            'scope'         => 'user.info.basic,video.publish',
            'redirect_uri'  => $this->callbackUrl('tiktok'),
            'state'         => $state,
        ]);
    }

    private function handleTiktokCallback(Request $request, int $orgId, array $creds): SocialAccount
    {
        $tokenRes = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key'    => $creds['client_key'],
            'client_secret' => $creds['client_secret'],
            'code'          => $request->query('code'),
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->callbackUrl('tiktok'),
        ])->throw()->json();

        $userRes = Http::withToken($tokenRes['access_token'])
            ->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,display_name,avatar_url',
            ])->throw()->json();

        $user = $userRes['data']['user'];

        return SocialAccount::updateOrCreate(
            ['organization_id' => $orgId, 'platform' => 'tiktok', 'external_account_id' => $user['open_id']],
            [
                'account_name'     => $user['display_name'],
                'account_handle'   => '@' . $user['display_name'],
                'access_token'     => $tokenRes['access_token'],
                'refresh_token'    => $tokenRes['refresh_token'] ?? null,
                'token_expires_at' => isset($tokenRes['expires_in']) ? now()->addSeconds($tokenRes['expires_in']) : null,
                'status'           => 'active',
                'avatar_url'       => $user['avatar_url'] ?? null,
                'last_verified_at' => now(),
                'deleted_at'       => null,
            ]
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function callbackUrl(string $platform): string
    {
        return config('app.url') . '/api/oauth/' . $platform . '/callback';
    }
}
