<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Support\Facades\Http;

class GoogleOAuthService
{
    private const AUTH_URL                  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL                 = 'https://oauth2.googleapis.com/token';
    private const REVOKE_URL                = 'https://oauth2.googleapis.com/revoke';
    private const SCOPE                     = 'https://www.googleapis.com/auth/gmail.send';
    private const TOKEN_REFRESH_MARGIN_SECONDS = 60;

    public function buildAuthUrl(int $empresaId): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => config('services.google.client_id'),
            'redirect_uri'  => config('services.google.redirect_uri'),
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => encrypt($empresaId),
        ]);
    }

    public function exchangeCode(string $code, string $encryptedState): Empresa
    {
        $empresaId = (int) decrypt($encryptedState);
        $empresa   = Empresa::findOrFail($empresaId);

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri'  => config('services.google.redirect_uri'),
            'grant_type'    => 'authorization_code',
        ]);

        $response->throw();
        $data = $response->json();

        if (empty($data['access_token']) || empty($data['refresh_token']) || empty($data['expires_in'])) {
            throw new \RuntimeException('Invalid token response from Google during code exchange');
        }

        $empresa->update([
            'google_oauth_email'  => $this->fetchGmailEmail($data['access_token']),
            'google_oauth_tokens' => json_encode([
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at'    => time() + (int) $data['expires_in'],
            ]),
        ]);

        return $empresa->fresh();
    }

    public function getValidAccessToken(Empresa $empresa): string
    {
        $tokens = json_decode($empresa->google_oauth_tokens, true);

        if (!is_array($tokens) || !isset($tokens['access_token'], $tokens['expires_at'])) {
            throw new \RuntimeException('OAuth tokens are missing or malformed for empresa ' . $empresa->id);
        }

        if ($tokens['expires_at'] > (time() + self::TOKEN_REFRESH_MARGIN_SECONDS)) {
            return $tokens['access_token'];
        }

        return $this->refreshAccessToken($empresa);
    }

    public function refreshAccessToken(Empresa $empresa): string
    {
        $tokens = json_decode($empresa->google_oauth_tokens, true);

        if (!is_array($tokens) || empty($tokens['refresh_token'])) {
            throw new \RuntimeException('Refresh token is missing for empresa ' . $empresa->id);
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'refresh_token' => $tokens['refresh_token'],
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'grant_type'    => 'refresh_token',
        ]);

        $response->throw();
        $data = $response->json();

        if (empty($data['access_token']) || empty($data['expires_in'])) {
            throw new \RuntimeException('Invalid token response from Google during refresh');
        }

        $tokens['access_token'] = $data['access_token'];
        $tokens['expires_at']   = time() + (int) $data['expires_in'];

        $empresa->update(['google_oauth_tokens' => json_encode($tokens)]);

        return $data['access_token'];
    }

    public function disconnect(Empresa $empresa): void
    {
        try {
            $tokens = json_decode($empresa->google_oauth_tokens, true);
            if (is_array($tokens) && !empty($tokens['refresh_token'])) {
                Http::post(self::REVOKE_URL . '?token=' . $tokens['refresh_token']);
            }
        } catch (\Throwable) {
            // Revocation failure is non-critical; clear locally regardless
        }

        $empresa->update([
            'google_oauth_email'  => null,
            'google_oauth_tokens' => null,
        ]);
    }

    private function fetchGmailEmail(string $accessToken): string
    {
        $response = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        return $response->ok() ? ($response->json('email') ?? '') : '';
    }
}
