<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\Location;
use App\Services\PaymentGateways\GoCardlessService;
use Illuminate\Http\Request;

/** @mixin Location * */
class LocationPaymentGatewayGoCardlessDetailsList extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $gocardlessService = (new GoCardlessService);

        $isOnboarded = $gocardlessService->isLocationOnboarded($this->getModel());

        return [
            'location' => [
                'id' => $this->getKey(),
                'name' => $this->name,
            ],
            'has_onboarded' => (int) $isOnboarded,
            'onboarding_url' => $this->when(
                ! $isOnboarded,
                function () use ($gocardlessService) {
                    return $gocardlessService->beginMerchantOnBoardingFlow($this->getModel());
                }
            ),
        ];
    }
}
