<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    public function test_no_cookie_returns_401(): void
    {
        $response = $this->getJson('/api/auth/session');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'unauthorized',
                'message' => 'No valid session.',
            ]);
    }

    public function test_empty_cookie_returns_401(): void
    {
        $response = $this->withCredentials()
            ->withUnencryptedCookie('user', '')
            ->getJson('/api/auth/session');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'unauthorized',
                'message' => 'No valid session.',
            ]);
    }

    public function test_invalid_plain_cookie_non_digits_returns_401(): void
    {
        $response = $this->withCredentials()
            ->withUnencryptedCookie('user', 'abc')
            ->getJson('/api/auth/session');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'unauthorized',
                'message' => 'No valid session.',
            ]);
    }

    public function test_valid_plain_cookie_returns_200(): void
    {
        $response = $this->withCredentials()
            ->withUnencryptedCookie('user', '30361286')
            ->getJson('/api/auth/session');

        $response->assertStatus(200)
            ->assertJson(['its_no' => '30361286'])
            ->assertJsonMissing(['success', 'data']);
    }

    public function test_plain_cookie_trimmed(): void
    {
        $response = $this->withCredentials()
            ->withUnencryptedCookie('user', "  30361286  \n")
            ->getJson('/api/auth/session');

        $response->assertStatus(200)
            ->assertJson(['its_no' => '30361286']);
    }

    public function test_cors_headers_when_origin_allowed(): void
    {
        Config::set('cors.allowed_origins', ['http://localhost:5173']);
        Config::set('cors.supports_credentials', true);

        $response = $this->getJson('/api/auth/session', [
            'Origin' => 'http://localhost:5173',
        ]);

        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
