<?php

declare(strict_types=1);

namespace App\Http\Resources\Candidate;

use App\Models\ApplicationStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApplicationStatusHistory */
class ApplicationStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status->value,
            'note' => $this->note,
            'changed_by' => $this->changed_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
