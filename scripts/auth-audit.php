<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$users = User::query()->select(['id', 'email', 'role', 'status'])->get();

echo 'USER_COUNT=' . $users->count() . PHP_EOL;

foreach ($users as $user) {
    echo $user->id . '|' . $user->email . '|' . $user->role->value . '|' . $user->status->value . PHP_EOL;
}
