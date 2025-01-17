<?php

namespace App\Events\Users;

use App\Enums\UserType;
use Illuminate\Foundation\Events\Dispatchable;

class UserUpdated
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $user_id,
        public int $tenant_id,
        public UserType $type,
    ) {
        //
    }
}
