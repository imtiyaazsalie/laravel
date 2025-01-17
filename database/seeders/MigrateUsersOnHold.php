<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateUsersOnHold extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::update('update users_on_hold set deleted_at = dt_modified where is_on_hold = ?', [0]);
    }
}
