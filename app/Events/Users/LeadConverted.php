<?php

namespace App\Events\Users;

use Illuminate\Foundation\Events\Dispatchable;

class LeadConverted
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $user_id,
        public int $tenant_id,
    ) {
        //
    }
}
