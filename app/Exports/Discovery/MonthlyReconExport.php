<?php

namespace App\Exports\Discovery;

use App\Enums\HealthCareProvider;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MonthlyReconExport implements FromQuery, ShouldQueue, WithHeadings, WithMapping
{
    use Exportable;

    public function headings(): array
    {
        return [
            'Member name',
            'Member surname',
            'RSA ID/Passport number',
            'Date of birth',
        ];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->surname,
            $user->id_number,
            $user->dob?->format('Y-m-d'),
        ];
    }

    public function query(): Builder
    {
        return User::query()
            ->select(['users.name', 'users.surname', 'users.id_number', 'users.dob'])
            ->distinct()
            ->join('user_to_box', 'users.user_id', 'user_to_box.user_id')
            ->where('user_to_box.user_status_id', UserStatus::ACTIVE)
            ->where('health_provider_id', HealthCareProvider::DISCOVERY_VITALITY_ID)
            ->orderBy('users.name', 'ASC')
            ->orderBy('users.surname', 'ASC');
    }
}
