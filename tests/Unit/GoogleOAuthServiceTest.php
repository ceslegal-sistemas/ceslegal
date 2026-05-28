<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Services\GoogleOAuthService;
use Tests\TestCase;

class GoogleOAuthServiceTest extends TestCase
{
    public function test_build_auth_url_contains_required_params(): void
    {
        config([
            'services.google.client_id'    => 'test-client-id',
            'services.google.redirect_uri' => 'https://example.com/callback',
        ]);

        $service = new GoogleOAuthService();
        $url     = $service->buildAuthUrl(42);

        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('gmail.send', urldecode($url));
        $this->assertStringContainsString('offline', $url);
        $this->assertStringContainsString('consent', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $this->assertEquals('test-client-id', $params['client_id']);
        $this->assertEquals(42, decrypt($params['state']));
    }

    public function test_get_valid_access_token_returns_existing_when_not_expired(): void
    {
        $empresa = new Empresa();
        // Setting through the model attribute triggers the encrypted cast
        $empresa->google_oauth_tokens = json_encode([
            'access_token'  => 'ya29.existing_token',
            'refresh_token' => '1//refresh',
            'expires_at'    => time() + 3600,
        ]);

        $service = new GoogleOAuthService();
        $token   = $service->getValidAccessToken($empresa);

        $this->assertEquals('ya29.existing_token', $token);
    }
}
