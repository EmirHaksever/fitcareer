<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CandidateProfile;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Auth\AuthService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Password123!';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rate_limits.auth.login_per_minute_by_ip' => 2,
            'rate_limits.auth.login_per_minute_by_email' => 2,
            'rate_limits.auth.password_reset_per_minute_by_ip' => 2,
            'rate_limits.auth.password_reset_per_minute_by_email' => 2,
            'rate_limits.auth.register_per_minute' => 2,
        ]);
    }

    #[Test]
    public function register_creates_candidate_account_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->candidateRegisterPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'candidate@example.com')
            ->assertJsonPath('data.user.role', UserRole::Candidate->value)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'role', 'status'],
                ],
            ]);

        $user = User::query()->where('email', 'candidate@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertNotNull($user->candidateProfile);
        $this->assertNotNull($user->settings);
        $this->assertNull($user->email_verified_at);
    }

    #[Test]
    public function register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplicate@example.com']);

        $payload = $this->candidateRegisterPayload([
            'email' => 'duplicate@example.com',
        ]);

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['email']]);
    }

    #[Test]
    public function register_rejects_admin_role_escalation_from_client(): void
    {
        $this->postJson('/api/v1/auth/register', $this->candidateRegisterPayload([
            'email' => 'admin-attempt@example.com',
            'role' => UserRole::Admin->value,
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'admin-attempt@example.com']);
    }

    #[Test]
    public function register_rejects_unknown_privileged_role_escalation_from_client(): void
    {
        $this->postJson('/api/v1/auth/register', $this->candidateRegisterPayload([
            'email' => 'company-admin-attempt@example.com',
            'role' => 'company_admin',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'company-admin-attempt@example.com']);
    }

    #[Test]
    public function register_allows_only_whitelisted_public_roles(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->candidateRegisterPayload([
            'email' => 'public-candidate@example.com',
            'role' => UserRole::Candidate->value,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.user.role', UserRole::Candidate->value);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Acme Inc',
            'email' => 'public-company@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => UserRole::Company->value,
            'company_name' => 'Acme Inc',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.role', UserRole::Company->value);

        $companyUser = User::query()->where('email', 'public-company@example.com')->first();

        $this->assertNotNull($companyUser);
        $this->assertSame(UserRole::Company, $companyUser->role);
        $this->assertNotNull($companyUser->company);
        $this->assertFalse($companyUser->company->is_verified);
        $this->assertSame('unverified', $companyUser->company->verification_status->value);
    }

    #[Test]
    public function auth_service_rejects_non_public_registration_role(): void
    {
        $this->expectException(ValidationException::class);

        app(AuthService::class)->register([
            'name' => 'Bad Actor',
            'email' => 'service-layer@example.com',
            'password' => self::PASSWORD,
            'role' => UserRole::Admin->value,
        ]);
    }

    #[Test]
    public function company_name_does_not_grant_verified_company_privileges(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->candidateRegisterPayload([
            'email' => 'candidate-with-company-name@example.com',
            'role' => UserRole::Candidate->value,
            'company_name' => 'Should Not Matter Corp',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_name']);

        $this->assertDatabaseMissing('users', ['email' => 'candidate-with-company-name@example.com']);
    }

    #[Test]
    public function login_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => self::PASSWORD,
        ]);
        CandidateProfile::query()->create(['user_id' => $user->id]);
        UserSetting::query()->create(['user_id' => $user->id]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => self::PASSWORD,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token']]);
    }

    #[Test]
    public function login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'wrong@example.com',
            'password' => self::PASSWORD,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'invalid-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    #[Test]
    public function logout_revokes_current_token(): void
    {
        $token = $this->authenticateUser();

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    #[Test]
    public function authenticated_user_endpoint_returns_current_user(): void
    {
        $user = User::factory()->create([
            'email' => 'me@example.com',
        ]);
        CandidateProfile::query()->create(['user_id' => $user->id]);
        UserSetting::query()->create(['user_id' => $user->id]);

        $token = $user->createToken('api-access')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'me@example.com')
            ->assertJsonMissing(['password']);
    }

    #[Test]
    public function unauthenticated_user_is_rejected_from_protected_endpoint(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    #[Test]
    public function email_verification_marks_user_as_verified(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'verify@example.com',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'auth.email.verify',
            now()->addHour(),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->getJson($verificationUrl)
            ->assertOk()
            ->assertJsonPath('message', 'Email verified successfully.');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function password_reset_request_returns_generic_message(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'If the account exists, a password reset link has been sent.',
            );

        Notification::assertSentTo($user, ResetPassword::class);
    }

    #[Test]
    public function password_reset_completion_updates_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'complete-reset@example.com',
            'password' => self::PASSWORD,
        ]);

        $oldToken = $user->createToken('api-access')->plainTextToken;
        $resetToken = '';

        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'complete-reset@example.com',
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$resetToken): bool {
            $resetToken = $notification->token;

            return true;
        });

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'complete-reset@example.com',
            'token' => $resetToken,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password reset successful.');

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertFalse(Hash::check(self::PASSWORD, $user->password));

        $this->withToken($oldToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    #[Test]
    public function old_password_is_invalid_after_password_reset(): void
    {
        $user = User::factory()->create([
            'email' => 'old-cred@example.com',
            'password' => self::PASSWORD,
        ]);

        $resetToken = '';

        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'old-cred@example.com',
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$resetToken): bool {
            $resetToken = $notification->token;

            return true;
        });

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'old-cred@example.com',
            'token' => $resetToken,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'old-cred@example.com',
            'password' => self::PASSWORD,
        ])->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'old-cred@example.com',
            'password' => 'NewPassword123!',
        ])->assertOk();
    }

    #[Test]
    public function auth_rate_limit_middleware_is_applied_to_register_endpoint(): void
    {
        $this->postJson('/api/v1/auth/register', $this->candidateRegisterPayload([
            'email' => 'register-one@example.com',
        ]))->assertCreated();

        $this->postJson('/api/v1/auth/register', $this->candidateRegisterPayload([
            'email' => 'register-two@example.com',
        ]))->assertCreated();

        $this->postJson('/api/v1/auth/register', $this->candidateRegisterPayload([
            'email' => 'register-three@example.com',
        ]))
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Too many requests.');
    }

    #[Test]
    public function validation_errors_use_standard_api_response_format(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'Validation failed.',
                'data' => null,
                'errors' => [
                    'email' => ['The email field is required.'],
                    'password' => ['The password field is required.'],
                ],
            ]);
    }

    #[Test]
    public function password_reset_request_does_not_reveal_account_existence(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'known@example.com']);

        $existingResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'known@example.com',
        ]);

        Notification::assertSentTo(
            User::query()->where('email', 'known@example.com')->first(),
            ResetPassword::class,
        );

        $missingResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@example.com',
        ]);

        $existingResponse->assertOk();
        $missingResponse->assertOk();

        $this->assertSame(
            $existingResponse->json('message'),
            $missingResponse->json('message'),
        );
        $this->assertSame(
            $existingResponse->json('success'),
            $missingResponse->json('success'),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function candidateRegisterPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Candidate',
            'email' => 'candidate@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => UserRole::Candidate->value,
        ], $overrides);
    }

    private function authenticateUser(): string
    {
        $user = User::factory()->create([
            'email' => 'auth-user@example.com',
            'password' => self::PASSWORD,
            'status' => UserStatus::Active,
        ]);
        CandidateProfile::query()->create(['user_id' => $user->id]);
        UserSetting::query()->create(['user_id' => $user->id]);

        return $user->createToken('api-access')->plainTextToken;
    }
}
