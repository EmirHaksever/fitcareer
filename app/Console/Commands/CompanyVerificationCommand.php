<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Company\CompanyService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CompanyVerificationCommand extends Command
{
    protected $signature = 'company:verification
        {action : approve or reject}
        {company : Company id or slug}';

    protected $description = 'Approve or reject a pending company verification request.';

    public function handle(CompanyService $companyService): int
    {
        $action = strtolower(trim((string) $this->argument('action')));
        $identifier = trim((string) $this->argument('company'));

        try {
            $company = $companyService->findForVerification($identifier);
            $updated = $companyService->applyOperationalVerification($company, $action);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Company #%d (%s): verification_status=%s is_verified=%s',
            $updated->id,
            $updated->slug,
            $updated->verification_status->value,
            $updated->is_verified ? 'true' : 'false',
        ));

        return self::SUCCESS;
    }
}
