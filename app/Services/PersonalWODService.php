<?php

namespace App\Services;

use App\Models\PersonalWod;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PersonalWODService
{
    public function store($data)
    {
        $personalWod = new PersonalWod();
        $personalWod->fill($data);
        $personalWod->save();

        return $personalWod->loadMissing([
            'user',
            'measurementUnit',
        ]);
    }

    public function update(PersonalWod $personalWod, $data)
    {
        return $personalWod->update($data);
    }

    public function delete(PersonalWod $personalWod)
    {
        return $personalWod->delete();
    }

    public function show(PersonalWod $personalWod)
    {
        return $personalWod->loadMissing([
            'tenant',
            'user',
            'measurementUnit',
        ]);
    }

    public function getAllPersonalWodsForUser(User $user, ?string $search = null, array $relations = []): Collection
    {
        return PersonalWod::query()
            ->select('personal_wod.*')
            ->with('measurementUnit')
            ->where('personal_wod.user_id', '=', $user->getAuthIdentifier())
            ->where('personal_wod.deleted', '=', false)
            ->when($search, function ($query) use ($search) {
                $query->where('personal_wod.name', 'LIKE', '%'.$search.'%');
            })
            ->when(! empty($relations), function ($query) use ($relations) {
                $query->with($relations);
            })
            ->orderBy('personal_wod.created_on', 'DESC')
            ->get();
    }
}
