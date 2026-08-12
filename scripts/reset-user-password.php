<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = $argv[1] ?? 'okumus.deniz@example.com';
$password = $argv[2] ?? 'Password123!';

$user = App\Models\User::query()->where('email', $email)->first();

if ($user === null) {
    echo "USER_NOT_FOUND: {$email}\n";
    exit(1);
}

$user->update([
    'password' => Illuminate\Support\Facades\Hash::make($password),
]);

echo "PASSWORD_RESET_OK\n";
echo "EMAIL={$user->email}\n";
echo "ROLE={$user->role->value}\n";
echo "NAME={$user->name}\n";
