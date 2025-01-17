<?php

namespace App\Http\Resources;

use App\Helpers\JsonResource;
use App\Models\User;
use Illuminate\Http\Request;

/** @mixin User * */
class UserMinimalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $redacted = (new UserResource($this))->redacted();

        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'email' => $this->email,
            'surname' => $redacted ? str($this->surname)->ucfirst()->charAt(0) : $this->surname,
            'image' => $this->image_url,
        ];
    }
}
