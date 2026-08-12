<?php

namespace Tests\Feature;

use App\DTOs\JobSearchQuery;
use App\Repositories\Contracts\JobSearchRepositoryInterface;
use App\Repositories\Eloquent\MySqlFulltextJobSearchRepository;
use App\Services\Job\JobSearchService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InfrastructureLayerTest extends TestCase
{
    #[Test]
    public function repository_service_provider_binds_job_search_repository(): void
    {
        $repository = $this->app->make(JobSearchRepositoryInterface::class);

        $this->assertInstanceOf(MySqlFulltextJobSearchRepository::class, $repository);
    }

    #[Test]
    public function job_search_service_resolves_repository_dependency(): void
    {
        $service = $this->app->make(JobSearchService::class);

        $result = $service->search(new JobSearchQuery);

        $this->assertSame(0, $result->total());
    }

    #[Test]
    public function api_response_trait_returns_standard_success_shape(): void
    {
        $responder = new class
        {
            use ApiResponseTrait;

            public function respond(): JsonResponse
            {
                return $this->successResponse(['id' => 1], 'Saved.');
            }
        };

        $response = $responder->respond();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'message' => 'Saved.',
            'data' => ['id' => 1],
            'errors' => null,
        ], $response->getData(true));
    }

    #[Test]
    public function api_response_trait_returns_standard_error_shape(): void
    {
        $responder = new class
        {
            use ApiResponseTrait;

            public function respond(): JsonResponse
            {
                return $this->validationErrorResponse(['email' => ['Invalid email.']]);
            }
        };

        $response = $responder->respond();

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => 'Validation failed.',
            'data' => null,
            'errors' => ['email' => ['Invalid email.']],
        ], $response->getData(true));
    }
}
