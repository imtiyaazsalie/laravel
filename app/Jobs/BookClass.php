<?php

namespace App\Jobs;

use App\Enums\ClassBookingWaitingStatus;
use App\Exceptions\Class\NoEligiblePackageException;
use App\Models\ClassBookingWaitingList;
use App\Models\ClassDate;
use App\Models\TenantUser;
use App\Services\ClassBookingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BookClass implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 300;

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->classDateId;
    }

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        public string $classDateId,
        public string $tenantUserId,
        public string $createdByMembershipId,
        public ?ClassBookingWaitingList $waitingListEntry = null
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(ClassBookingsService $bookings): void
    {
        try {
            $bookings->create(
                classDate: ClassDate::findOrFail($this->classDateId),
                currentTenantUser: TenantUser::findOrFail($this->createdByMembershipId),
                tenantUser: TenantUser::findOrFail($this->tenantUserId)
            );

            //mark waiting list booking as booked
            if ($this->waitingListEntry) {
                $this->waitingListEntry->fill([
                    'status' => ClassBookingWaitingStatus::BOOKED,
                ])->save();
            }
        } catch (NoEligiblePackageException $ex) {

            if ($this->waitingListEntry) {

                //cancel waiting list booking and process waiting list again
                $this->waitingListEntry->fill([
                    'status' => ClassBookingWaitingStatus::CANCELLED,
                ])->save();

                ProcessWaitingList::dispatch(
                    classDateId: $this->classDateId,
                    processLimit: 1
                )->onQueue('bookings')
                    ->afterCommit();
            }
        }
    }
}
