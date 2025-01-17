<?php

namespace App\Services;

use App\Models\BodyMeasurements;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BodyMeasurementService
{
    #[QueryParam('filter[location_id]', 'string', '', false)]
    #[QueryParam('filter[user_id]', 'string', '', false)]
    #[QueryParam('filter[recorded_on]', 'string', '', false)]
    public function list()
    {
        return QueryBuilder::for(BodyMeasurements::class)
            ->allowedFilters(
                [
                    AllowedFilter::exact('location_id', 'user.location.box_facility_id', true),
                    AllowedFilter::exact('user_id'),
                    AllowedFilter::partial('recorded_on', 'recorded_on'),
                ]
            )
            ->paginate()
            ->appends(request()->query());
    }

    public function store($data)
    {
        $bodyMeasurement = new BodyMeasurements();
        $bodyMeasurement->forceFill($data);
        $bodyMeasurement->save();

        return $bodyMeasurement;
    }

    public function update(array $data, BodyMeasurements $bodyMeasurement)
    {
        $bodyMeasurement->update($data);

        return $bodyMeasurement;
    }

    public function delete(BodyMeasurements $bodyMeasurement)
    {
        $bodyMeasurement->delete();

        return $bodyMeasurement;
    }
}
