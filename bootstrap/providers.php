<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    EventServiceProvider::class,
    RepositoryServiceProvider::class,
];
