<?php

namespace Database\Seeders;

use App\Models\LinkedUser;
use App\Models\User;
use Illuminate\Database\Seeder;

class LinkedUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $linkedAccounts = User::query()
            ->whereNotNull('linked_account_uuid')
            ->groupBy('linked_account_uuid')
            ->select('user_id', 'linked_account_uuid', 'name', 'surname')
            ->get();

        foreach ($linkedAccounts as $linkedAccount) {

            $users = User::query()
                ->where('linked_account_uuid', $linkedAccount->linked_account_uuid)
                ->select('user_id')
                ->get();

            if ($users->count() > 1) {

                foreach ($users as $user) {

                    $first = $users->toArray();
                    $second = array_reverse($users->toArray());

                    LinkedUser::create(
                        [
                            'user_id' => $first[0]['user_id'],
                            'linked_user_id' => $first[1]['user_id'],
                        ]
                    );

                    LinkedUser::create(
                        [
                            'user_id' => $second[0]['user_id'],
                            'linked_user_id' => $second[1]['user_id'],
                        ]
                    );

                }
            }

        }
    }
}
