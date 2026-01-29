<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    private const TEST_KEY_HEX = '0000000000000000000000000000000000000000000000000000000000000000';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('auth_session.encrypted', false);
        Config::set('auth_session.decryption_key', null);
    }

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
            ->withUnencryptedCookie('its_no', '')
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
            ->withUnencryptedCookie('its_no', 'abc')
            ->getJson('/api/auth/session');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'unauthorized',
                'message' => 'No valid session.',
            ]);
    }

    public function test_valid_plain_cookie_returns_200(): void
    {
        Config::set('auth_session.encrypted', false);

        $response = $this->withCredentials()
            ->withUnencryptedCookie('its_no', '30361286')
            ->getJson('/api/auth/session');

        $response->assertStatus(200)
            ->assertJson(['its_no' => '30361286'])
            ->assertJsonMissing(['success', 'data']);
    }

    public function test_plain_cookie_trimmed(): void
    {
        Config::set('auth_session.encrypted', false);

        $response = $this->withCredentials()
            ->withUnencryptedCookie('its_no', "  30361286  \n")
            ->getJson('/api/auth/session');

        $response->assertStatus(200)
            ->assertJson(['its_no' => '30361286']);
    }

    public function test_valid_encrypted_cookie_returns_200(): void
    {
        Config::set('auth_session.encrypted', true);
        Config::set('auth_session.decryption_key', self::TEST_KEY_HEX);

        $encrypted = $this->encrypt('30361286');

        $response = $this->withCredentials()
            ->withUnencryptedCookie('its_no', $encrypted)
            ->getJson('/api/auth/session');

        $response->assertStatus(200)
            ->assertJson(['its_no' => '30361286'])
            ->assertJsonMissing(['success', 'data']);
    }

    public function test_decryption_failure_returns_401(): void
    {
        Config::set('auth_session.encrypted', true);
        Config::set('auth_session.decryption_key', self::TEST_KEY_HEX);

        $response = $this->withCredentials()
            ->withUnencryptedCookie('its_no', 'not-valid-base64')
            ->getJson('/api/auth/session');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'unauthorized',
                'message' => 'No valid session.',
            ]);
    }

    public function test_encrypted_mode_with_missing_key_returns_401(): void
    {
        Config::set('auth_session.encrypted', true);
        Config::set('auth_session.decryption_key', null);

        $encrypted = $this->encrypt('30361286');

        $response = $this->withCredentials()
            ->withUnencryptedCookie('its_no', $encrypted)
            ->getJson('/api/auth/session');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'unauthorized',
                'message' => 'No valid session.',
            ]);
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

    private function encrypt(string $plain): string
    {
        $key = hex2bin(self::TEST_KEY_HEX);
        $iv = str_repeat("\0", 16);
        $ciphertext = openssl_encrypt(
            $plain,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        $binary = $iv . $ciphertext;

        return base64_encode($binary);
    }
}
