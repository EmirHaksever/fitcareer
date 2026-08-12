<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\JobSearchQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface JobSearchRepositoryInterface
{
    public function search(JobSearchQuery $query): LengthAwarePaginator;
}
