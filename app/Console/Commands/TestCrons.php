<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestCrons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-crons';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $users = DB::table('user_to_box')
            ->select([
                'users.email',
                'users.user_status_id',
                'user_to_box.user_to_box_id',
                'user_to_box.user_id',
                'user_to_box.box_id',
                'user_to_box.effective_date',
                'user_to_box.end_date',
            ])
            ->join('users', 'users.user_id', '=', 'user_to_box.user_id')
            ->where('user_to_box.end_date', '>=', '2024-06-08')
            ->where('user_to_box.end_date', '<', today()->format('Y-m-d'))
            ->where('user_to_box.user_type_id', 4)
            ->where('user_to_box.user_status_id', 2)
            ->where('user_to_box.deleted', 0)
            ->orderBy('user_to_box.user_id')
            ->orderBy('user_to_box.box_id')
            ->get();

        foreach ($users as $user) {
            $active = DB::table('class_bookings')
                ->join('classes', 'classes.class_id', '=', 'class_bookings.class_id')
                ->where('user_id', $user->user_id)
                ->where('classes.box_id', $user->box_id)
                ->whereBetween('class_bookings.dt_added', ['2024-06-08', '2024-07-09'])
                ->limit(1)
                ->first();
            if (! $active) {
                continue;
            }

            if ($active) {
                DB::table('user_to_box')
                    ->where('user_to_box_id', $user->user_to_box_id)
                    ->update(['end_date' => '2025-12-01']);
                dump($user->user_to_box_id);
            }

        }

    }
}
