<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum MandateStatus: string
{
    use SmartEnum;

    case PENDING = 'pending'; // Mandate is newly created but user has not finished flow
    case CREATED = 'created'; // Mandate has been created and awaiting approval
    case CUSTOMER_APPROVAL_GRANTED = 'customer_approval_granted';
    case CUSTOMER_APPROVAL_SKIPPED = 'customer_approval_skipped';
    case ACTIVE = 'active'; // Mandate is active - user has completed flow
    case CANCELLED = 'cancelled'; // Mandate has been cancelled by user, GoCardless, Octiv (note British spelling)
    case FAILED = 'failed';
    case TRANSFERRED = 'transferred';
    case EXPIRED = 'expired';
    case PENDING_SUBMISSION = 'pending_submission'; // Not sure if this is still a thing
    case SUBMITTED = 'submitted';
    case RESUBMISSION_REQUESTED = 'resubmission_requested';
    case REINSTATED = 'reinstated';

    /**
     * Specific to goCardless
     */
    public static function activeStatusValues(): array
    {
        return [
            self::PENDING->value,
            self::PENDING_SUBMISSION->value,
            self::CREATED->value,
            self::SUBMITTED->value,
            self::ACTIVE->value,
        ];
    }
}
