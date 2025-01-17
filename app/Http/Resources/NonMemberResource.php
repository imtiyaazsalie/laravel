<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NonMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'non_member' => [
                'name' => $this->non_member_name,
                'surname' => $this->non_surname,
                'email' => $this->non_member_email,
                'id_number' => $this->id_number,
                'date_of_birth' => $this->date_of_birth?->toDateString(),
            ],
        ];
    }
}
