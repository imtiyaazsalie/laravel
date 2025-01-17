<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExtendMemberships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:extend-memberships {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extend tenant and location memberships.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $date = $this->setDate();

        $sql = 'UPDATE user_to_box bm INNER JOIN users u ON bm.user_id = u.user_id INNER JOIN boxes b ON bm.box_id = b.box_id AND b.box_status_id = 1 SET bm.end_date = DATE_ADD(bm.end_date, INTERVAL 1 YEAR) WHERE bm.end_date = ?';

        DB::update($sql, [$date->format('Y-m-d')]);

        $sql = 'UPDATE user_to_facility fm INNER JOIN users u ON fm.user_id = u.user_id INNER JOIN box_facility bf ON bf.box_facility_id = fm.`box_facility_id` INNER JOIN boxes b ON bf.box_id = b.box_id AND b.box_status_id = 1 SET fm.end_date = DATE_ADD(fm.end_date, INTERVAL 1 YEAR) WHERE fm.end_date = ?';

        DB::update($sql, [$date->format('Y-m-d')]);
    }

    public function setDate(): Carbon
    {
        if ($this->option('date')) {
            return Carbon::parse($this->option('date'));
        } else {
            return today();
        }
    }
}
