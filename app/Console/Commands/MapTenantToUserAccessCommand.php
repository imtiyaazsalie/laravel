<?php

namespace App\Console\Commands;

use App\Models\TenantUser;
use App\Models\UserAccessPrivilege;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MapTenantToUserAccessCommand extends Command
{
    protected $signature = 'app:map-tenant-to-user-access';

    protected $description = 'Command description';

    public function handle(): void
    {
        $userAccessPrivileges = UserAccessPrivilege::query()
            ->withoutGlobalScopes()
            ->whereNull('box_id')
            ->get();

        foreach ($userAccessPrivileges as $userAccessPrivilege) {

            $tenantId = TenantUser::query()
                ->withoutGlobalScopes()
                ->where('user_id', $userAccessPrivilege->user_id)
                ->whereBetween(DB::raw('CURDATE()'), [
                    DB::raw('user_to_box.effective_date'),
                    DB::raw('user_to_box.end_date'),
                ])
                ->orderBy('user_to_box.user_to_box_id', 'DESC')
                ->select('user_to_box.box_id')
                ->limit(1)
                ->first();

            if (! $tenantId) {
                continue;
            }

            $userAccessPrivilege->update(['box_id' => $tenantId->box_id]);

        }
    }
}
