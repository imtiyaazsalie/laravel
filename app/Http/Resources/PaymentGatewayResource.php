<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;

/** @mixin PaymentGateway * */
class PaymentGatewayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
        ];
    }
}
