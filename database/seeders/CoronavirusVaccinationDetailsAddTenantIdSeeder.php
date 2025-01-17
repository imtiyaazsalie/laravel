<?php

namespace Database\Seeders;

use App\Models\CoronavirusVaccinationDetails;
use App\Models\TenantUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class CoronavirusVaccinationDetailsAddTenantIdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $records = CoronavirusVaccinationDetails::class::all();

        foreach ($records as $record) {
            if ($record) {
                $tenant_id = $this->getActiveTenant($record->user_id);
                if ($tenant_id->first()) {
                    $record->box_id = $tenant_id->first();
                    $record->save();
                }
            }
        }
    }

    protected function getActiveTenant($userId): Collection
    {
        return TenantUser::where('deleted', '=', 0)
            ->where('end_date', '>', today())
            ->where('user_id', '=', $userId)
            ->orderBy('end_date', 'desc')
            ->orderBy('updated_on', 'desc')
            ->limit(1)
            ->pluck('box_id');
    }
}
