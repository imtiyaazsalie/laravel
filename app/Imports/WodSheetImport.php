<?php

namespace App\Imports;

use App\Enums\Affiliate;
use App\Jobs\CopyGlobalWods;
use App\Models\Exercise;
use App\Models\MeasurementUnit;
use App\Models\Programme;
use App\Models\Tenant;
use App\Models\Wod;
use App\Services\WODExerciseService;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;

class WodSheetImport implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithValidation
{
    private WODExerciseService $wod;

    private Collection $programmes;

    private Collection $benchmarks;

    private Collection $units;

    private array $parts = ['a', 'b', 'c', 'd', 'e', 'f'];

    public function __construct(
        private ?Tenant $tenant = null,
        private ?Affiliate $affiliate = null,
    ) {
        $this->wod = new WODExerciseService();

        if (! $this->tenant && ! $this->affiliate) {
            throw new RuntimeException('Tenant or affiliate must be provided');
        }

    }

    public function prepareForValidation($data, $index)
    {
        try {
            $data['date'] = Date::excelToDateTimeObject($data['date'])->format('Y-m-d');
        } catch (\Exception) {
        }

        return array_map(
            fn ($value) => is_string($value) ? $this->replaceEmDash($value) : $value, $data
        );
    }

    public function rules(): array
    {
        $rules = [
            'programme' => 'required|string',
            'date' => 'required|date_format:Y-m-d',
            'name' => 'required|string',
            'warm_up' => 'nullable|string',
            'cool_donwn' => 'nullable|string',
            'trainer_notes' => 'nullable|string',
            'member_notes' => 'nullable|string',
        ];

        foreach ($this->parts as $key => $part) {

            $prefix = 'part_'.$part;

            $rules = array_merge($rules, [

                // type
                $prefix.'_type' => [
                    $key === 0 ? 'required' : 'nullable',
                    'in:benchmark,exercise',
                ],

                // part
                $prefix.'_part' => 'nullable',

                // benchmarks
                $prefix.'_category' => 'required_if:*.'.$prefix.'_type,benchmark|nullable|string',
                $prefix.'_benchmark' => 'required_if:*.'.$prefix.'_type,benchmark|nullable|string',

                // exercises
                $prefix.'_name' => 'required_if:*.'.$prefix.'_type,exercise|nullable|string',
                $prefix.'_description' => 'required_if:*.'.$prefix.'_type,exercise|nullable|string',
                $prefix.'_measurement' => 'required_if:*.'.$prefix.'_type,exercise|nullable|string',
                $prefix.'_resource_url' => 'nullable|url',
            ]);
        }

        return $rules;
    }

    private function replaceEmDash(string $string): string
    {
        return str_replace('–', '-', $string);
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {

            $data = collect(
                $validator->getData()
            );

            /**
             * Get programmes
             */
            $uniqueProgrammeNames = $data->pluck('programme')->unique()->toArray();

            $this->programmes = Programme::query()
                ->active()
                ->when(
                    $this->tenant,
                    fn ($q) => $q->where('box_id', $this->tenant->getKey()),
                    fn ($q) => $q->where('affiliate_id', $this->affiliate)->whereNull('box_id')
                )
                ->whereIn('name', $uniqueProgrammeNames)
                ->get();

            /**
             * Validate programme names
             */
            foreach ($uniqueProgrammeNames as $programme) {
                if ($this->programmes->pluck('name')->doesntContain($programme)) {
                    foreach ($data->where('programme', $programme) as $index => $row) {
                        $validator->errors()->add($index, 'The selected programme name is invalid.');
                    }
                }
            }

            /**
             * Check WOD dates are unique for Programme.
             */
            $wods = Wod::query()
                ->whereIn('wod_date', $data->pluck('date')->unique()->toArray())
                ->whereIn('programme_id', $this->programmes->modelKeys())
                ->get();

            $this->programmes->each(function ($programme) use ($data, $wods, $validator) {
                // check the import file
                $programmeDates = $data->where('programme', $programme->name)->pluck('date');

                $duplicates = $programmeDates->duplicates();

                if ($duplicates->isNotEmpty()) {
                    $duplicates->each(fn ($row, $key) => $validator->errors()->add($key, 'Dates must be unique for each programme.'));
                }

                // now check the database
                $wods->where('programme_id', $programme->getKey())
                    ->whereIn('wod_date', $programmeDates->map(fn ($d) => Carbon::parse($d))->toArray())
                    ->each(function ($wod) use ($data, $programme, $validator) {
                        $data
                            ->where('programme', $programme->name)
                            ->where('date', $wod->wod_date->toDateString())
                            ->each(fn ($row, $key) => $validator->errors()->add($key, "A workout already exists for {$programme->name} on {$wod->wod_date->toDateString()}"));
                    });
            });

            /**
             * Validate benchmark names.
             */
            $benchmarks = $data->map(fn ($row) => array_filter(array_map(
                fn ($part) => Arr::get($row, 'part_'.$part.'_type') === 'benchmark' ? [
                    'name' => Arr::get($row, 'part_'.$part.'_benchmark'),
                    'category' => Arr::get($row, 'part_'.$part.'_category'),
                ] : null,
                $this->parts
            )))->flatten(1)->unique();

            $this->benchmarks = Exercise::query()
                ->global()
                ->active()
                ->benchmarks()
                ->with('exerciseCategory')
                ->whereIn('exercise_name', $benchmarks->pluck('name')->toArray())
                ->get();

            // validate benchmark names and categories
            $data->each(function ($row, $key) use ($validator) {
                foreach ($this->parts as $part) {

                    if (Arr::get($row, 'part_'.$part.'_type') !== 'benchmark') {
                        continue;
                    }

                    $benchmark = Arr::get($row, 'part_'.$part.'_benchmark');

                    $found = $this->benchmarks->where('exercise_name', $benchmark)->first();

                    if (! $found) {
                        $validator->errors()->add($key, 'Benchmark name is invalid for part '.strtoupper($part));
                    } elseif ($found->exerciseCategory->name !== Arr::get($row, 'part_'.$part.'_category')) {
                        $validator->errors()->add($key, 'Benchmark category is invalid for part '.strtoupper($part));
                    }
                }
            });

            /**
             * Validate measurement input for exercises.
             */
            $measurements = $data->map(fn ($row) => array_filter(array_map(
                fn ($part) => Arr::get($row, 'part_'.$part.'_type') === 'exercise'
                    ? Arr::get($row, 'part_'.$part.'_measurement')
                    : null,
                $this->parts
            )))->flatten()->unique();

            $this->units = MeasurementUnit::query()
                ->where('is_active', 1)
                ->whereIn('measuring_unit_desc', $measurements->toArray())
                ->get();

            $measurements->diff($this->units->pluck('measuring_unit_desc'))
                ->each(fn ($missing) => $data->each(function ($row, $key) use ($validator, $missing) {
                    foreach ($this->parts as $part) {
                        if ($missing === Arr::get($row, 'part_'.$part.'_measurement')) {
                            $validator->errors()->add($key, 'Measurement unit is invalid for part '.strtoupper($part));
                        }
                    }
                })
                );

        });
    }

    public function collection(Collection $collection)
    {
        $collection->each(function ($row) {

            $programme = $this->programmes->where('name', $row['programme'])->firstOrFail();

            $wod = Wod::create([
                'tenant_id' => $this->tenant?->getKey(),
                'programme_id' => $programme->getKey(),
                'coach_notes' => Arr::get($row->toArray(), 'trainer_notes'),
                ...Arr::only($row->toArray(), [
                    'date',
                    'name',
                    'description',
                    'warm_up',
                    'cool_down',
                    'member_notes',
                ]),
            ]);

            foreach ($this->parts as $key => $part) {

                $prefix = 'part_'.$part;

                if (! $type = Arr::get($row, $prefix.'_type')) {
                    continue;
                }

                $data = $type === 'benchmark' ? [
                    'benchmark_id' => $this->benchmarks->where('exercise_name', Arr::get($row, $prefix.'_benchmark'))->firstOrFail()->getKey(),
                ] : [
                    // exercises
                    'measure_id' => $this->units->where('measuring_unit_desc', Arr::get($row, $prefix.'_measurement'))->firstOrFail()->getKey(),
                    'name' => Arr::get($row, $prefix.'_name'),
                    'description' => Arr::get($row, $prefix.'_description'),
                    'resource_url' => Arr::get($row, $prefix.'_resource_url'),
                ];

                $this->wod->storeWodExercise([
                    'prefix' => Arr::get($row, $prefix.'_part') ?? strtoupper($part),
                    'order' => $key + 1,
                    ...$data,
                ], $wod);
            }
        });

        $this->programmes->each(function ($programme) {
            if ($programme->isGlobal()) {
                dispatch(new CopyGlobalWods($programme));
            }
        });
    }
}
