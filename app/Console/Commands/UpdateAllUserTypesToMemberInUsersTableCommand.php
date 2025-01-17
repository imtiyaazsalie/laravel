<?php

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Console\Command;

class UpdateAllUserTypesToMemberInUsersTableCommand extends Command
{
    protected $signature = 'app:update-all-user-types-to-member-in-users-table';

    protected $description = 'Command description';

    public function handle(): void
    {
        User::where('user_id', '!=', 1)
            ->update(['user_type_id' => UserType::USER]);
    }
}
