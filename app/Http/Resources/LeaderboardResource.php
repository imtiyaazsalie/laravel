<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\Leaderboard;
use App\Services\TenantUserService;
use Illuminate\Http\Request;

/** @mixin Leaderboard **/
class LeaderboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $userLocation = (new TenantUserService())->getLocationUserByTenant($this->user_id, $this->box_id);

        return [
            'id' => $this->getKey(),
            'score' => (float) $this->score,

            $this->mergeWhen(isset($this->position), [
                'position' => $this->position,
            ]),

            'tenant_id' => $userLocation?->tenant_id,
            'tenant' => new TenantResource($userLocation?->tenant),

            'user_id' => $this->user_id,
            'user' => new UserMinimalResource($this->whenLoaded('user')),

            'exercise_id' => $this->exercise_id,
            'exercise' => new ExerciseResource($this->whenLoaded('exercise')),

            'location_id' => $userLocation?->location_id,
            'location' => new LocationResource($userLocation?->location),

            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
