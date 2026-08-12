<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\JobSearchRepositoryInterface;
use App\Repositories\Eloquent\MySqlFulltextJobSearchRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            JobSearchRepositoryInterface::class,
            MySqlFulltextJobSearchRepository::class,
        );
    }
}
