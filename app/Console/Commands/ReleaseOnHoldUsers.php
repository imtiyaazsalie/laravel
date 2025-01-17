<?php

namespace App\Console\Commands;

use App\Jobs\ReleaseOnHoldUser;
use App\Models\UserOnHold;
use Illuminate\Console\Command;
use Illuminate\Database\Query\JoinClause;

class ReleaseOnHoldUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:release-on-hold-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release on hold users.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        UserOnHold::query()
            ->join('user_to_box', function (JoinClause $join) {
                $join->on('users_on_hold.user_id', '=', 'user_to_box.user_id')
                    ->on('users_on_hold.box_id', '=', 'user_to_box.box_id');
            })
            ->whereDate('users_on_hold.release_date', '<=', today())
            ->whereDate('user_to_box.end_date', '>', today())
            ->get()
            ->each(function ($user) {
                ReleaseOnHoldUser::dispatch($user);
            });

        return Command::SUCCESS;
    }
}
