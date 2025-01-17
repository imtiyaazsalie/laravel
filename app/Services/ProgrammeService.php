<?php

namespace App\Services;

use App\Enums\Affiliate;
use App\Models\Programme;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Wod;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class ProgrammeService
{
    public function getProgrammesForTenant(Tenant|int $tenant, bool $isActive = true, ?string $search = null): array|Collection
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return Programme::query()->where('box_id', '=', $tenantId)
            ->where('is_active', '=', $isActive)
            ->when(! is_null($search), function ($query) use ($search) {
                return $query->where('name', 'LIKE', '%'.$search.'%');
            })
            ->orderBy('name', 'ASC')
            ->get();
    }

    public function hasPackageWithProgrammeVisibility(Programme $programme, User $user): bool
    {
        $activeUserPackages = (new UserPackageService())->getActiveUserPackagesForTenant($user, $programme->tenant);

        $activeUserPackages->load('package.programmeVisibility');

        return $activeUserPackages->filter(function (UserPackage $userPackage) use ($programme) {
            return ! $userPackage->package->programmeVisibility ||
                $userPackage->package->programmeVisibility->isEmpty() ||
                $userPackage->package->programmeVisibility->pluck('programme_id')->contains($programme->getKey());
        })->count() > 0;
    }

    public function deactivateAffiliateProgrammes(array $tenantIds, Affiliate $affiliate): void
    {
        if (empty($tenantIds)) {
            return;
        }

        Programme::query()
            ->active()
            ->where('affiliate_id', $affiliate)
            ->whereIn('box_id', $tenantIds)
            ->update([
                'is_active' => false,
            ]);
    }

    public function copy(Programme $programme, int $tenantId): Programme
    {
        if (! $programme->isGlobal()) {
            throw new RuntimeException('Cannot copy a non global programme');
        }

        if (Programme::query()
            ->where('box_id', $tenantId)
            ->where('parent_id', $programme->getKey())
            ->exists()
        ) {
            throw new RuntimeException('Global programme already exists for this tenant.');
        }

        $copy = $programme->replicate()->fill([
            'tenant_id' => $tenantId,
            'parent_id' => $programme->getKey(),
            'name' => Programme::query()->where('box_id', $tenantId)->whereName($programme->name)->exists()
                ? $programme->name.' - Official'
                : $programme->name,
        ]);

        $copy->save();

        $this->copyWods($programme, $copy);

        return $copy;
    }

    public function copyWods(Programme $programme, Programme $copy): void
    {
        $programme->load([
            'wods' => function ($query) {
                return $query->whereDate('wod_date', '>', today()->toDateString())
                    ->with([
                        'exercises.prefix',
                        'exercises.exercise',
                    ]);
            },
        ]);

        $programme->wods->each(function ($wod) use ($copy) {

            /**
             * Skip existing
             */
            if (Wod::query()
                ->where('programme_id', $copy->getKey())
                ->whereDate('wod_date', $wod->wod_date->toDateString())
                ->exists()
            ) {
                return;
            }

            /**
             * Wod
             */
            $wodCopy = $wod->replicate()->fill([
                'box_id' => $copy->tenant_id,
                'programme_id' => $copy->getKey(),
            ]);

            $wodCopy->save();

            $wod->exercises->each(function ($wodExercise) use ($wodCopy, $copy) {

                /**
                 * Wod exercise
                 */
                if ($wodExercise->exercise->isNotBenchmark()) {

                    $exerciseCopy = $wodExercise->exercise->replicate()->fill([
                        'box_id' => $copy->tenant_id,
                    ]);

                    $exerciseCopy->save();
                } else {
                    $exerciseCopy = $wodExercise->exercise;
                }

                $wodExerciseCopy = $wodExercise->replicate()->fill([
                    'wod_id' => $wodCopy->getKey(),
                    'exercise_id' => $exerciseCopy->getKey(),
                ]);

                $wodExerciseCopy->save();

                /**
                 * Wod prefix
                 */
                $wodExercise->prefix->replicate()->fill([
                    'wod_to_exercise_id' => $wodExerciseCopy->getKey(),
                ])->save();
            });

        });

    }
}
