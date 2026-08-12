<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\ApplicationStatusChanged;
use App\Events\JobImportCompleted;
use App\Events\JobTrustAnalysisCompleted;
use App\Events\JobTrustAnalysisFailed;
use App\Listeners\DispatchApplicationNotificationListener;
use App\Listeners\RecordApplicationStatusHistoryListener;
use App\Listeners\UpdateJobSourceLastRunListener;
use App\Listeners\UpdateJobTrustFieldsListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        JobImportCompleted::class => [
            UpdateJobSourceLastRunListener::class,
        ],
        JobTrustAnalysisCompleted::class => [
            UpdateJobTrustFieldsListener::class.'@handleJobTrustAnalysisCompleted',
        ],
        JobTrustAnalysisFailed::class => [
            UpdateJobTrustFieldsListener::class.'@handleJobTrustAnalysisFailed',
        ],
        ApplicationStatusChanged::class => [
            RecordApplicationStatusHistoryListener::class,
            DispatchApplicationNotificationListener::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
