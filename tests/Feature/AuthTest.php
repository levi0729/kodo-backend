<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;

class AuthTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    // ══════════════════════════════════════════════════════════════
    //  REGISTER
    // ══════════════════════════════════════════════════════════════

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username'              => 'johndoe',
            'email'                 => 'john@example.com',
            'password'              => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'display_name'          => 'John Doe',
            'phone_number'          => '+36301234567',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'username', 'email', 'display_name'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'username' => 'johndoe',
            'email'    => 'john@example.com',
        ]);

        // Verify user settings were created
        $user = User::where('email', 'john@example.com')->first();
        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
        ]);
    }

    public function test_register_uses_username_as_display_name_when_not_provided(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username'              => 'janedoe',
            'email'                 => 'jane@example.com',
            'password'              => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'phone_number'          => '+36301234567',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'username'     => 'janedoe',
            'display_name' => 'janedoe',
        ]);
    }

    public function test_register_fails_with_missing_required_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['username', 'email', 'password', 'phone_number'],
                ],
            ]);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        $this->createUser(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'username'              => 'newuser',
            'email'                 => 'existing@example.com',
            'password'              => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'phone_number'          => '+36301234567',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('email', $response->json('error.details'));
    }

    public function test_register_fails_with_duplicate_username(): void
    {
        $this->createUser(['username' => 'takenname']);

        $response = $this->postJson('/api/auth/register', [
            'username'              => 'takenname',
            'email'                 => 'unique@example.com',
            'password'              => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'phone_number'          => '+36301234567',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('username', $response->json('error.details'));
    }

    public function test_register_fails_with_weak_password_no_uppercase(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username'              => 'testuser',
            'email'                 => 'test@example.com',
            'password'              => 'weakpass1',
            'password_confirmation' => 'weakpass1',
            'phone_number'          => '+36301234567',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('password', $response->json('error.details'));
    }

    public function test_register_fails_with_weak_password_no_number(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username'              => 'testuser',
            'email'                 => 'test@example.com',
            'password'              => 'WeakPassword',
            'password_confirmation' => 'WeakPassword',
            'phone_number'          => '+36301234567',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('password', $response->json('error.details'));
    }

    public function test_register_fails_with_weak_password_too_short(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username'              => 'testuser',
            'email'                 => 'test@example.com',
            'password'              => 'Sh0rt',
            'password_confirmation' => 'Sh0rt',
            'phone_number'          => '+36301234567',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('password', $response->json('error.details'));
    }

    public function test_register_fails_with_password_mismatch(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username'              => 'testuser',
            'email'                 => 'test@example.com',
            'password'              => 'Secret1234',
            'password_confirmation' => 'Different1234',
            'phone_number'          => '+36301234567',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('password', $response->json('error.details'));
    }

    public function test_register_fails_with_invalid_username_characters(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username'              => 'invalid user name!',
            'email'                 => 'test@example.com',
            'password'              => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'phone_number'          => '+36301234567',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('username', $response->json('error.details'));
    }

    // ══════════════════════════════════════════════════════════════
    //  LOGIN
    // ══════════════════════════════════════════════════════════════

    public function test_login_with_valid_credentials_requires_verification(): void
    {
        $user = $this->createUser([
            'email'    => 'login@example.com',
            'password' => Hash::make('Secret1234'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'login@example.com',
            'password' => 'Secret1234',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'verification_required',
                'user_id',
                'email',
            ])
            ->assertJson([
                'verification_required' => true,
                'user_id'               => $user->id,
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->createUser([
            'email'    => 'login@example.com',
            'password' => Hash::make('Secret1234'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'login@example.com',
            'password' => 'WrongPassword1',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'Secret1234',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_login_fails_for_inactive_account(): void
    {
        $this->createUser([
            'email'     => 'inactive@example.com',
            'password'  => Hash::make('Secret1234'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'inactive@example.com',
            'password' => 'Secret1234',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Account is deactivated.']);
    }

    public function test_login_increments_failed_attempts(): void
    {
        $user = $this->createUser([
            'email'    => 'attempts@example.com',
            'password' => Hash::make('Secret1234'),
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'attempts@example.com',
            'password' => 'Wrong1',
        ]);

        $user->refresh();
        $this->assertEquals(1, $user->failed_login_attempts);
    }

    public function test_login_locks_account_after_5_failed_attempts(): void
    {
        $user = $this->createUser([
            'email'                 => 'lockme@example.com',
            'password'              => Hash::make('Secret1234'),
            'failed_login_attempts' => 4,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'lockme@example.com',
            'password' => 'Wrong1',
        ]);

        $response->assertStatus(423)
            ->assertJson(['message' => 'Account locked for 15 minutes due to too many failed attempts.']);

        $user->refresh();
        $this->assertNotNull($user->locked_until);
    }

    public function test_login_rejects_locked_account(): void
    {
        $this->createUser([
            'email'        => 'locked@example.com',
            'password'     => Hash::make('Secret1234'),
            'locked_until' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'locked@example.com',
            'password' => 'Secret1234',
        ]);

        $response->assertStatus(423);
    }

    public function test_login_with_trusted_device_returns_token_directly(): void
    {
        $user = $this->createUser([
            'email'    => 'trusted@example.com',
            'password' => Hash::make('Secret1234'),
        ]);

        $rawToken = 'my-trusted-device-token-123';
        $hashedToken = hash('sha256', $rawToken);

        \App\Models\TrustedDevice::create([
            'user_id'      => $user->id,
            'device_token' => $hashedToken,
            'expires_at'   => now()->addDays(30),
            'created_at'   => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'        => 'trusted@example.com',
            'password'     => 'Secret1234',
            'device_token' => $rawToken,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user', 'token'])
            ->assertJson(['message' => 'Login successful.']);
    }

    public function test_login_validation_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $details = $response->json('error.details');
        $this->assertArrayHasKey('email', $details);
        $this->assertArrayHasKey('password', $details);
    }

    // ══════════════════════════════════════════════════════════════
    //  LOGOUT
    // ══════════════════════════════════════════════════════════════

    public function test_logout_deletes_token_and_sets_offline(): void
    {
        $user = $this->createUser(['presence_status' => 'online']);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);

        $user->refresh();
        $this->assertEquals('offline', $user->presence_status);
        $this->assertCount(0, $user->tokens);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════
    //  ME
    // ══════════════════════════════════════════════════════════════

    public function test_me_returns_current_user(): void
    {
        $user = $this->createUser([
            'username'     => 'meuser',
            'email'        => 'me@example.com',
            'display_name' => 'Me User',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'username', 'email', 'display_name'],
                'settings',
            ])
            ->assertJsonPath('user.username', 'meuser')
            ->assertJsonPath('user.email', 'me@example.com');
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════
    //  CHANGE PASSWORD
    // ══════════════════════════════════════════════════════════════

    public function test_change_password_successfully(): void
    {
        $user = $this->createUser([
            'password' => Hash::make('OldPassword1'),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/change-password', [
                'current_password'      => 'OldPassword1',
                'password'              => 'NewPassword2',
                'password_confirmation' => 'NewPassword2',
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Password changed successfully.']);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword2', $user->password));
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = $this->createUser([
            'password' => Hash::make('OldPassword1'),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/change-password', [
                'current_password'      => 'WrongCurrent1',
                'password'              => 'NewPassword2',
                'password_confirmation' => 'NewPassword2',
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Current password is incorrect.']);
    }

    public function test_change_password_fails_with_weak_new_password(): void
    {
        $user = $this->createUser([
            'password' => Hash::make('OldPassword1'),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/change-password', [
                'current_password'      => 'OldPassword1',
                'password'              => 'weak',
                'password_confirmation' => 'weak',
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('password', $response->json('error.details'));
    }

    public function test_change_password_fails_without_confirmation(): void
    {
        $user = $this->createUser([
            'password' => Hash::make('OldPassword1'),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/change-password', [
                'current_password' => 'OldPassword1',
                'password'         => 'NewPassword2',
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('password', $response->json('error.details'));
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/change-password', [
            'current_password'      => 'OldPassword1',
            'password'              => 'NewPassword2',
            'password_confirmation' => 'NewPassword2',
        ]);

        $response->assertStatus(401);
    }
}
