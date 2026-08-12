<?php

declare(strict_types=1);

namespace App\Http\Resources\Job;

use App\Models\JobSkill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JobSkill */
class JobSkillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->skill->id,
            'name' => $this->skill->name,
            'slug' => $this->skill->slug,
            'importance' => $this->importance->value,
        ];
    }
}
