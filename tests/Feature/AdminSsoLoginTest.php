<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminSsoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_valid_sso_token_logs_in_configured_existing_admin(): void
    {
        config([
            'geoflow.sso_secret' => 'shared-sso-secret',
            'geoflow.sso_admin_username' => 'admin',
        ]);

        $admin = $this->createAdmin('admin');

        $this->get(route('admin.sso-login', ['token' => $this->makeSsoToken()]))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertSame($admin->id, Auth::guard('admin')->id());
        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'username' => 'admin',
        ]);
    }

    public function test_sso_token_cannot_be_reused(): void
    {
        config([
            'geoflow.sso_secret' => 'shared-sso-secret',
            'geoflow.sso_admin_username' => 'admin',
        ]);

        $this->createAdmin('admin');
        $token = $this->makeSsoToken();

        $this->get(route('admin.sso-login', ['token' => $token]))
            ->assertRedirect(route('admin.dashboard'));

        Auth::guard('admin')->logout();
        $this->get(route('admin.sso-login', ['token' => $token]))
            ->assertForbidden();
    }

    public function test_sso_rejects_when_configured_admin_is_missing(): void
    {
        config([
            'geoflow.sso_secret' => 'shared-sso-secret',
            'geoflow.sso_admin_username' => 'missing-admin',
        ]);

        $this->get(route('admin.sso-login', ['token' => $this->makeSsoToken()]))
            ->assertForbidden();

        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_sso_rejects_without_shared_secret(): void
    {
        config([
            'geoflow.sso_secret' => '',
            'geoflow.sso_admin_username' => 'admin',
        ]);

        $this->createAdmin('admin');

        $this->get(route('admin.sso-login', ['token' => $this->makeSsoToken('shared-sso-secret')]))
            ->assertForbidden();

        $this->assertFalse(Auth::guard('admin')->check());
    }

    private function createAdmin(string $username): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'password' => 'password',
            'email' => $username.'@example.test',
            'display_name' => 'SSO Admin',
            'role' => 'super_admin',
            'status' => 'active',
            'last_login' => null,
        ]);
    }

    private function makeSsoToken(string $secret = 'shared-sso-secret'): string
    {
        $now = time();
        $payload = [
            'email' => 'manager@example.test',
            'exp' => $now + 60,
            'iat' => $now,
            'name' => 'Manager',
            'nonce' => bin2hex(random_bytes(16)),
            'role' => 'admin',
            'user_id' => 'website-user-1',
            'username' => 'manager@example.test',
        ];
        $encodedPayload = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encodedPayload, $secret);

        return $encodedPayload.'.'.$signature;
    }
}
