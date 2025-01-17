<?php

namespace App\Http\Resources;

use App\Models\Wod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\ResourceCollection;

class WhiteboardResource extends ResourceCollection
{
    /**
     * @param  mixed  $resource
     */
    public function __construct(
        $resource,
        public Wod $wod,
        public Collection $tenantUsers,
    ) {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return tap($this->collection)->transform(function ($classBooking) {
            $tenantUser = $this->tenantUsers->where('user_id', $classBooking->user_id)->first();

            $data = [
                'sort' => $classBooking->sortValue,
                'wod_id' => $this->wod->wod_id,
                'class' => new ClassResource($classBooking->class),
                'class_booking' => new ClassBookingResource($classBooking),

                $this->merge(new MultiUserResource($classBooking)),

                $this->mergeWhen($tenantUser, [
                    'tenant_user' => new TenantUserResource($tenantUser),
                ]),
            ];

            if ($classBooking->user && $this->wod->captures->where('user_id', $classBooking->user_id)->count()) {
                $data['captureExersies'] = WodCaptureExerciseResource::collection(
                    $this->wod->captures->where('user_id', $classBooking->user_id)->first()->exercises
                );
            } else {
                $data['captureExersies'] = [];
            }

            return $data;
        });
    }
}
