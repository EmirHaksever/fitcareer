<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Enums\ApplicationStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ListApplicationsRequest extends ApiFormRequest
{
    public const SORT_ATTENTION = 'attention';

    public const SORT_MATCH_SCORE_DESC = 'match_score_desc';

    public const SORT_MATCH_SCORE_ASC = 'match_score_asc';

    public const SORT_APPLIED_AT_DESC = 'applied_at_desc';

    public const SORT_APPLIED_AT_ASC = 'applied_at_asc';

    /** @var list<string> */
    public const SORTS = [
        self::SORT_ATTENTION,
        self::SORT_MATCH_SCORE_DESC,
        self::SORT_MATCH_SCORE_ASC,
        self::SORT_APPLIED_AT_DESC,
        self::SORT_APPLIED_AT_ASC,
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'job_id' => ['sometimes', 'integer', 'exists:jobs,id'],
            'status' => ['sometimes', Rule::enum(ApplicationStatus::class)],
            'sort' => ['sometimes', 'string', Rule::in(self::SORTS)],
        ];
    }

    public function sort(): string
    {
        $sort = $this->query('sort');

        return is_string($sort) && in_array($sort, self::SORTS, true)
            ? $sort
            : self::SORT_ATTENTION;
    }
}
