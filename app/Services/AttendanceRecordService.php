<?php

namespace App\Services;

use App\Enums\DiscoveryBatchMnemonic;
use App\Enums\DiscoveryVitalityBatchType;
use App\Models\AttendanceRecord;
use App\Models\ClassBooking;
use App\Models\ClassDate;
use App\Models\DiscoveryVitalityBatch;
use App\Models\User;
use Illuminate\Http\Request;

class AttendanceRecordService
{
    public function getLatestAttendanceRecordForMember(User $user, ?ClassBooking $classBooking = null, bool $isCheckedIn = false, bool $isCheckedOut = false)
    {
        return AttendanceRecord::query()
            ->where('user_id', $user->getKey())
            ->when($classBooking instanceof ClassBooking, function ($query) use ($classBooking) {
                $query->where('attendance_records.class_booking_id', $classBooking->getKey());
            })
            ->when($isCheckedIn, function ($query) {
                $query->where(function ($query) {
                    $query->whereNotNull('attendance_records.checked_in_at')
                        ->orWhereNull('attendance_records.checked_in_at');
                });
            })
            ->when($isCheckedOut, function ($query) {
                $query->where(function ($query) {
                    $query->whereNotNull('attendance_records.checked_out_at')
                        ->orWhereNull('attendance_records.checked_out_at');
                });
            })
            ->orderBy('id', 'DESC')
            ->first();

    }

    public function validatePostData(Request $paramFetcher, bool $isMember): array
    {
        $errors = [];

        if ($isMember) {
            // Validation for member
            if ($paramFetcher->get('class_booking_id')) {
                $classBooking = ClassBooking::query()->find($paramFetcher->get('class_booking_id'));

                if (! $classBooking instanceof ClassBooking) {
                    $errors['class_booking_id'] = 'ClassBooking could not be found';
                }
            }
        } else {
            // Validation for non-member
            $idNumber = $paramFetcher->get('id_number');
            $name = $paramFetcher->get('name');
            $surname = $paramFetcher->get('surname');
            $dateOfBirthString = $paramFetcher->get('date_of_birth');

            if (! $paramFetcher->get('health_provider_id')) {
                $errors['health_provider_id'] = 'health_provider_id is a required field';
            }

            if ($idNumber) {
                if (strlen($idNumber) > 13 || strlen($idNumber) < 6 || preg_match('/[^a-z\-0-9]/i', $idNumber)) {
                    $errors['id_number'] = 'Please ensure that your RSA ID/Passport number is correct';
                }

                if (strlen($idNumber) === 13 && preg_match('/[a-z]/i', $idNumber)) {
                    $errors['id_number'] = 'Please ensure that your RSA ID number is correct';
                }
            } else {
                $errors['id_number'] = 'id_number is a required field';
            }

            if (! $name) {
                $errors['name'] = 'Name is a required field';
            } elseif (preg_match('~\d+~', $name)) {
                $errors['name'] = 'Name cannot contain digits';
            }

            if (! $surname) {
                $errors['surname'] = 'Surname is a required field';
            } elseif (preg_match('~\d+~', $surname)) {
                $errors['surname'] = 'Surname cannot contain digits';
            }

            if (! $dateOfBirthString) {
                $errors['date_of_birth'] = 'date_of_birth is a required field';
            } else {
                $dateOfBirth = new \DateTime($dateOfBirthString);

                if ($dateOfBirth < new \DateTime('1900-01-01') || $dateOfBirth > new \DateTime('today')) {
                    $errors['dateOfBirth'] = 'Invalid date of birth';
                }
            }
        }

        if ($paramFetcher->get('class_date_id')) {
            $classDate = ClassDate::query()->find($paramFetcher->get('class_date_id'));

            if (! $classDate instanceof ClassDate) {
                $errors['class_date_id'] = 'class_date_id could not be found';
            }
        }

        return $errors;
    }

    public function createDiscoveryVitalityBatch(string $filename, DiscoveryVitalityBatchType $batchType, int $noOfRecords = 0, int $checkTotal = 0): void
    {
        $latestBatch = DiscoveryVitalityBatch::query()->whereBatchType($batchType)->latest()->first();

        $batchNumber = $latestBatch ? (int) $latestBatch->batch_number + 1 : 000001;

        DiscoveryVitalityBatch::create([
            'batch_number' => str_pad(trim($batchNumber), 6, '0', STR_PAD_LEFT),
            'file_path' => config('discovery.directories.internal.recon').$filename,
            'batch_type' => $batchType,
            'no_of_records' => $noOfRecords,
            'check_total' => $checkTotal,
        ]);
    }

    public function getDiscoveryVitalityMnemonic(AttendanceRecord $attendanceRecord): DiscoveryBatchMnemonic
    {
        $isQualifying = false;
        $qualifiesThreshold = 30;

        if ($attendanceRecord->checked_in_at && $attendanceRecord->checked_out_at) {
            $isQualifying = $attendanceRecord->checked_in_at->diffInMinutes($attendanceRecord->checked_out_at) >= $qualifiesThreshold;
        }

        if ($attendanceRecord->classDate?->class->isVirtual()) {
            $mnemonic = $isQualifying ? DiscoveryBatchMnemonic::ONLINE_QUALIFYING : DiscoveryBatchMnemonic::ONLINE_NON_QUALIFYING;
        } else {
            $mnemonic = $isQualifying ? DiscoveryBatchMnemonic::FACILITY_QUALIFYING : DiscoveryBatchMnemonic::FACILITY_NON_QUALIFYING;
        }

        return $mnemonic;
    }
}
