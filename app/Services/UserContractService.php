<?php

namespace App\Services;

use App\Models\Location;
use App\Models\User;
use App\Models\UserContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UserContractService
{
    public function store($data)
    {
        $userContract = new UserContract();
        $userContract->fill($data);
        $userContract->save();

        return $userContract;
    }

    public function createOrUpdateUserContract(?UserContract $userContract, User $user, Location $boxFacility, ?\DateTime $startingDate = null, ?\DateTime $endingDate = null, ?UploadedFile $userContractFile = null, ?bool $accepted = false, $ipAddress = null): ?UserContract
    {
        if (! $userContract instanceof UserContract) {
            // Create user contract
            $userContract = new UserContract();
        }

        // Set defaults if not dates were passed
        if (! $startingDate) {
            $startingDate = new \DateTime();
        }

        if (! $endingDate) {
            $endingDate = new \DateTime(date('Y-m-d', strtotime('+1 year')));
        }

        $userContract
            ->setAttribute('user_id', $user->getKey())
            ->setAttribute('box_id', $boxFacility->box_id)
            ->setAttribute('box_facility_id', $boxFacility->getKey())
            ->setAttribute('starting_on', $startingDate)
            ->setAttribute('ending_on', $endingDate)
            ->setAttribute('contract_terms_and_conditions', $boxFacility->tenant->contract_terms_and_conditions);

        if ($accepted) {
            $userContract
                ->setAttribute('accepted', true)
                ->setAttribute('accepted_on', new \DateTime())
                ->setAttribute('ip_address', $ipAddress);
        }

        $userContract->save();

        // File upload
        if ($userContractFile) {
            // Determines if file needs to be uploaded
            $shouldUpload = true;

            // Existing logo is set
            if ($userContract->file_path) {
                $absolutePath = Storage::disk('public')->get($userContract->file_path);

                if ($userContractFile !== $absolutePath) {
                    // Delete the current file
                    Storage::disk('public')->delete($userContract->file_path);
                } else {
                    // If the paths are the same don't upload a new file
                    $shouldUpload = false;
                }
            }

            if ($shouldUpload) {
                $fileName = md5(time().$userContract->getKey()).'_attachment.'.$userContractFile->getClientOriginalExtension();
                $filePath = 'user-contracts/';

                $userContractFile->storePubliclyAs($filePath, $fileName, ['disk' => 'public']);

                $userContract->update([
                    'file_path' => $filePath.$fileName,
                    'file_name' => $fileName,
                    'file_mime' => $userContractFile->getMimeType(),
                ]);
            }
        } elseif ($userContract->file_path) {
            // Delete the current file
            Storage::disk('public')->delete($userContract->file_path);
            // Set path to null
            $userContract->update([
                'file_path' => null,
                'file_name' => null,
                'file_mime' => null,
            ]);
        }

        return $userContract;
    }

    public function sendUserContract(UserContract $userContract): void
    {
        $contractSignLink = config('octiv.web_app_url').'/sign/contract/'.$userContract->getKey();

        (new CrmService())->createScheduledEmailForNotification(
            tenantOrLocation: $userContract->tenant,
            context: 'accept_terms',
            recipient: $userContract->user,
            data: [
                'member_name' => $userContract->user->name,
                'member_surname' => $userContract->user->surname,
                'link' => '<a href="'.$contractSignLink.'">'.$contractSignLink.'</a>',
            ]
        );
    }

    public function generateContractPdf(UserContract $contract): string
    {
        $path = str('user-contracts/')
            ->append($contract->user->full_name)
            ->append('_contract_')
            ->append(time())
            ->append('.pdf')
            ->toString();

        $contract->loadMissing([
            'user',
            'tenant',
            'location',
            'tenantUser.bankAccount.bank',
        ]);

        $userPackages = $contract->user->userPackages()
            ->active()
            ->whereRelation('package', 'box_id', '=', $contract->tenant_id)
            ->with('package')
            ->get();

        app()->setLocale($contract->tenant->settings->locale_language);

        Pdf::loadView('pdf.user-contract', [
            'contract' => $contract,
            'memberFee' => (new FinanceService())->calculateMemberFee($contract->user, $contract->tenant),
            'isUserOnSpecialRateOrDiscount' => (new FinanceService())->isUserOnSpecialRateOrDiscount($contract->user, $contract->tenant_id) ? trans('contracts.yes') : trans('contracts.no'),
            'userPackages' => $userPackages,
        ])->save($path, 'tmp');

        return $path;
    }
}
