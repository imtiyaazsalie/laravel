<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;

/** @mixin \App\Models\OwnBenchmark **/
class OwnBenchmarkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->getKey(),

            'score' => $this->score,
            'date' => $this->own_benchmark_date?->toDateString(),
            'notes' => $this->note,

            'is_active' => $this->is_active,
            'is_rx' => $this->is_rx,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->dt_verified?->toDateString(),

            'tenant_id' => $this->tenant_id,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),

            'user_id' => $this->user_id,
            'user' => new UserTenantResource($this->whenLoaded('userTenant')),

            'exercise_id' => $this->exercise_id,
            'exercise' => new ExerciseResource($this->whenLoaded('exercise')),

            'verifier_by_id' => $this->verifier_id,
            'verified_by' => new UserResource($this->whenLoaded('verifier')),

            'created_at' => $this->created_on?->toDateString(),
            'updated_at' => $this->updated_on?->toDateString(),
        ];
    }
}
