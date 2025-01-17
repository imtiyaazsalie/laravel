<?php

namespace App\Services;

use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Models\DebitBatch;
use App\Models\UserBatch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;

class UserBatchService
{
    public function getUserBatchesForDebitBatchSubmission(DebitBatch $debitBatch): Collection|array
    {
        return UserBatch::query()
            ->select('user_to_batch.*')
            ->join('user_to_box', function (JoinClause $join) use ($debitBatch) {
                $join->on('user_to_batch.user_id', '=', 'user_to_box.user_id')
                    ->where('user_to_box.box_id', '=', $debitBatch->location->tenant->getKey());
            })
            ->join('user_banking_details', function (JoinClause $join) use ($debitBatch) {
                $join->on('user_to_batch.user_id', '=', 'user_banking_details.user_id')
                    ->where('user_banking_details.box_id', '=', $debitBatch->location->tenant->getKey());
            })
            ->where('user_to_batch.amount_editable', '>', 0)
            ->where('user_to_batch.is_active', '=', true)
            ->where('user_to_batch.debit_batch_id', '=', $debitBatch->getKey())
            ->where('user_banking_details.is_active', '=', true)
            ->where('user_to_box.end_date', '>', today()->toDateString())
            ->where('user_to_box.user_debit_status_id', '=', UserDebitStatus::DEBIT_ORDER)
            ->where('user_to_box.user_status_id', '!=', UserStatus::DEACTIVATED)
            ->where('user_to_box.deleted', '=', false)
            ->whereHas('user')
            ->distinct('user_to_batch.user_to_batch_id')
            ->get();
    }
}
