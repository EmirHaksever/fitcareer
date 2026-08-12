<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

class AuthService
{
    /**
     * @param  array{name: string, email: string, password: string, role: string, company_name?: string|null}  $payload
     * @return array{user: User, token: string}
     */
    public function register(array $payload): array
    {
        $role = UserRole::tryFromSelfRegistration($payload['role']);

        if ($role === null) {
            throw ValidationException::withMessages([
                'role' => ['The selected role is invalid.'],
            ]);
        }

        $user = DB::transaction(function () use ($payload, $role): User {
            $user = User::query()->create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'password' => $payload['password'],
                'role' => $role,
                'status' => UserStatus::Active,
            ]);

            UserSetting::query()->create([
                'user_id' => $user->id,
            ]);

            if ($user->role === UserRole::Candidate) {
                CandidateProfile::query()->create([
                    'user_id' => $user->id,
                ]);
            }

            if ($user->role === UserRole::Company) {
                $companyName = (string) ($payload['company_name'] ?? $payload['name']);
                $baseSlug = Str::slug($companyName);
                $slug = $baseSlug;
                $suffix = 1;

                while (Company::query()->where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-{$suffix}";
                    $suffix++;
                }

                Company::query()->create([
                    'user_id' => $user->id,
                    'name' => $companyName,
                    'slug' => $slug,
                ]);
            }

            return $user;
        });

        $user->sendEmailVerificationNotification();

        return $this->issueToken($user);
    }

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}|null
     */
    public function login(array $credentials): ?array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        if ($user->status === UserStatus::Suspended) {
            return null;
        }

        return $this->issueToken($user);
    }

    public function logout(User $user, ?string $bearerToken = null): void
    {
        $currentToken = $user->currentAccessToken();

        if ($currentToken instanceof TransientToken) {
            PersonalAccessToken::findToken($bearerToken)?->delete();
        } elseif ($currentToken !== null) {
            $currentToken->delete();
        } elseif ($bearerToken !== null) {
            PersonalAccessToken::findToken($bearerToken)?->delete();
        }
    }

    public function sendEmailVerificationNotification(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->sendEmailVerificationNotification();
    }

    public function verifyEmail(User $user, string $hash): bool
    {
        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return false;
        }

        if ($user->hasVerifiedEmail()) {
            return true;
        }

        $user->markEmailAsVerified();

        return true;
    }

    public function requestPasswordReset(string $email): void
    {
        Password::broker()->sendResetLink(['email' => $email]);
    }

    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $payload
     */
    public function resetPassword(array $payload): bool
    {
        $status = Password::reset(
            $payload,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        return $status === Password::PASSWORD_RESET;
    }

    public function updatePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => $newPassword,
        ])->save();

        $user->tokens()->delete();
    }

    /**
     * @return array{user: User, token: string}
     */
    private function issueToken(User $user): array
    {
        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $token = $user->createToken('api-access')->plainTextToken;

        return [
            'user' => $user->fresh(),
            'token' => $token,
        ];
    }
}
