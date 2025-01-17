<?php

namespace App\Imports;

use App\Enums\Gender;
use App\Enums\LeadMemberStatus;
use App\Enums\LeadMemberType;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\LeadMember;
use App\Models\Location;
use App\Models\User;
use App\Services\TenantUserService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class LeadUsersImport implements ToArray, WithHeadingRow, WithValidation
{
    private array $progress = [];

    public function prepareForValidation($data, $index)
    {
        if ($status = Arr::get($data, '7')) {
            $data[7] = strtolower($status);
        }

        if ($status = Arr::get($data, '8')) {
            $data[8] = strtolower($status);
        }

        return $data;
    }

    public function rules(): array
    {
        return [
            '0' => function ($attribute, $value, $onFailure) {
                $location = Location::query()
                    ->where('box_facility_name', $value)
                    ->where('box_id', request()->input('tenant_id'))
                    ->where('is_active', true)
                    ->exists();
                if (! $location) {
                    $onFailure("Location $value is invalid");
                }
            }, // Location
            '1' => 'required|string', // first name
            '2' => 'required|string', // last name
            '3' => 'nullable|in:M,F', // gender
            '4' => 'required|email', // email
            '5' => 'nullable', // mobile
            '6' => 'nullable|date_format:Y-m-d', // date of birth
            '7' => 'required|in:'.implode(',', LeadMemberStatus::values()), // status
            '8' => 'nullable', // source
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            '3.in' => 'Invalid option for gender. Available options 1,2,3',
            '7.in' => 'Invalid option for status. Available options '.implode(',', LeadMemberStatus::values()),
            '8.in' => 'Invalid option for source. Available options '.implode(',', LeadMemberType::values()),
        ];
    }

    public function result(): array
    {
        return $this->progress;
    }

    public function array(array $array): void
    {
        $result = [];

        foreach ($array as $index => $row) {

            if ($row[3] == 'M') {
                $gender = Gender::MALE->value;
            } elseif ($row[3] == 'F') {
                $gender = Gender::FEMALE->value;
            } else {
                $gender = Gender::OTHER->value;
            }

            $location = Location::query()
                ->where('box_facility_name', $row[0])
                ->where('box_id', request()->input('tenant_id'))
                ->where('is_active', true)
                ->first();

            $result[$index] = [
                'row' => $index,
                'name' => $row[1],
                'surname' => $row[2],
                'email' => $row[4],
                'status' => 'imported',
            ];

            if (! $location) {
                $result[$index]['status'] = 'Box Facility does not exist.';

                continue;
            }

            $leadMember = LeadMember::query()
                ->where('user_id', $row[1])
                ->where('box_facility_id', $location->getKey())
                ->first();

            if ($leadMember) {
                $result[$index]['status'] = 'Location does not exist.';

                continue;
            }

            $user = User::where('email', '=', $row[4])->first();

            if (! $user instanceof User) {
                $user = User::create([
                    'name' => $row[1],
                    'surname' => $row[2],
                    'email' => $row[4],
                    'mobile' => $row[5],
                    'date_of_birth' => $row[6],
                    'gender_id' => $gender,
                    'user_type_id' => UserType::USER,
                    'user_status_id' => UserStatus::ACTIVE,
                ]);
            }

            if ((new TenantUserService())->getCurrentUserTenantForTenant($user, request()->input('tenant_id'))?->isMember()) {
                continue;
            }

            // First or Create member
            $leadMember = LeadMember::firstOrCreate([
                'user_id' => $user->getKey(),
                'box_facility_id' => $location->getKey(),
            ], [
                'status' => $row[7],
                'source' => $row[8],
                'referred_by_id' => auth()->user()->getAuthIdentifier(),
                'type' => LeadMemberType::REFERRAL,
            ]);

            // Create Lead Membership
            (new TenantUserService())->createUserBoxMembership(
                user: $user,
                tenant: $location->tenant,
                userType: UserType::LEAD_MEMBER,
                startingDate: Carbon::now(),
                userDebitStatus: UserDebitStatus::NO_PAYMENT,
                userStatus: UserStatus::ACTIVE
            );

            (new TenantUserService())->createUserFacilityMembership(
                $leadMember,
                $location,
                Carbon::now()
            );
        }

        $this->progress = $result;
    }
}
