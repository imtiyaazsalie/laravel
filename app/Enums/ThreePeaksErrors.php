<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum ThreePeaksErrors: int
{
    use SmartEnum;

    case AUTHENTICATED = 10001;
    case AUTHENTICATION_FAILED = 10002;
    case LOGOUT_SUCCESSFUL = 10003;
    case LOGOUT_FAILED = 10004;
    case INVALID_CUSTOMER_REFERENCE = 10005;
    case CUSTOMER_AUTHENTICATION_FAILED = 10006;
    case CUSTOMER_AUTHENTICATION_SUCCESSFUL = 10007;
    case IP_ADDRESS_VALID = 10020;
    case POSSIBLE_HACK_ATTEMPT_DETECTED_FOR_YOUR_SESSION = 10021;
    case OK_OR_TRUE = 10220;
    case FAILED_OR_FALSE = 10221;
    case PARAMETER_NOT_SET = 11001;
    case INVALID_FUNCTION = 11002;
    case UNKNOWN_ERROR = 11003;
    case INVALID_XML_STRING_NO_XML_ENTRY_OR_EMPTY = 12001;
    case VALID_XML_STRING = 12002;
    case INVALID_XML_STRING = 12003;
    case INVALID_LENGTH = 20001;
    case INVALID_STRING_CONTAINED_SPECIAL_CHARACTERS = 20002;
    case INVALID_STRING_LENGTH_MAXED = 20004;
    case INVALID_STRING_LENGTH_MIN = 20005;
    case INVALID_NUMBER = 21001;
    case INVALID_NUMERIC_VALUE = 21002;
    case INVALID_DATE_STRING = 22001;
    case INVALID_DATE = 22002;
    case INVALID_DATE_PAST_CURRENT = 22003;
    case INVALID_DATE_PUBLIC_HOLIDAY_SUNDAY = 22004;
    case INVALID_ACCOUNT_TYPE_TYPE_03 = 23001;
    case VALID_AMOUNT = 23002;
    case INVALID_AMOUNT = 23003;
    case AMOUNT_IS_NEGATIVE_NOT_ALLOWED_FOR_DEBIT_PROCESSING = 23004;
    case RECALL_REQUEST_ACCEPTED_CHECK_THE_SUBMISSION_STATUS = 30001;
    case RECALL_REQUEST_FAILED = 30002;
    case INVALID_DEBTOR_REFERENCE_DUPLICATE_FOUND = 31001;
    case SUBMISSION_REMOVED = 40001;
    case SUBMISSION_REMOVAL_FAILED = 40002;
    case NUMBER_OF_RECORDS_MATCH = 41001;
    case NUMBER_OF_RECORDS_DO_NOT_MATCH_THE_INITIAL_RECORDS_SENT = 41002;
    case THE_SUBMISSION_TOTAL_MATCH = 41003;
    case THE_SUBMISSION_TOTAL_CALCULATED_FOR_DOES_NOT_MATCH = 41004;
    case UNKNOWN_ERROR_CONTACT_US = 99999;

    public function toString(): string
    {
        return match ($this) {
            self::AUTHENTICATED => 'Authenticated',
            self::AUTHENTICATION_FAILED => 'Authentication Failed',
            self::LOGOUT_SUCCESSFUL => 'Logout Successful',
            self::LOGOUT_FAILED => 'Logout Failed',
            self::INVALID_CUSTOMER_REFERENCE => 'Invalid Customer Reference',
            self::CUSTOMER_AUTHENTICATION_FAILED => 'Customer Authentication Failed',
            self::CUSTOMER_AUTHENTICATION_SUCCESSFUL => 'Customer Authentication Successful',
            self::IP_ADDRESS_VALID => 'IP Address valid',
            self::POSSIBLE_HACK_ATTEMPT_DETECTED_FOR_YOUR_SESSION => 'Possible Hack Attempt Detected for your Session',
            self::OK_OR_TRUE => 'OK or True',
            self::FAILED_OR_FALSE => 'Failed or False',
            self::PARAMETER_NOT_SET => 'Parameter not set',
            self::INVALID_FUNCTION => 'Invalid Function',
            self::UNKNOWN_ERROR => 'Unknown Error',
            self::INVALID_XML_STRING_NO_XML_ENTRY_OR_EMPTY => 'Invalid XML String - No XML Entry or Empty',
            self::VALID_XML_STRING => 'Valid XML String',
            self::INVALID_XML_STRING => 'Invalid XML String',
            self::INVALID_LENGTH => 'Invalid Length',
            self::INVALID_STRING_CONTAINED_SPECIAL_CHARACTERS => 'Invalid String - Contained Special Characters',
            self::INVALID_STRING_LENGTH_MAXED => 'Invalid String Length (Maxed)',
            self::INVALID_STRING_LENGTH_MIN => 'Invalid String Length (Min)',
            self::INVALID_NUMBER => 'Invalid number',
            self::INVALID_NUMERIC_VALUE => 'Invalid numeric value',
            self::INVALID_DATE_STRING => 'Invalid Date String (ddmmyyyy)',
            self::INVALID_DATE => 'Invalid Date',
            self::INVALID_DATE_PAST_CURRENT => 'Invalid Date - (Past/Current)',
            self::INVALID_DATE_PUBLIC_HOLIDAY_SUNDAY => 'Invalid Date - (Public holiday/Sunday)',
            self::INVALID_ACCOUNT_TYPE_TYPE_03 => 'Invalid Account Type (Type 0-3)',
            self::VALID_AMOUNT => 'Valid Amount',
            self::INVALID_AMOUNT => 'Invalid Amount',
            self::AMOUNT_IS_NEGATIVE_NOT_ALLOWED_FOR_DEBIT_PROCESSING => 'Amount is Negative Not allowed for Debit Processing',
            self::RECALL_REQUEST_ACCEPTED_CHECK_THE_SUBMISSION_STATUS => 'Recall Request Accepted check the submission status.',
            self::RECALL_REQUEST_FAILED => 'Recall Request Failed',
            self::INVALID_DEBTOR_REFERENCE_DUPLICATE_FOUND => 'Invalid Debtor Reference (Duplicate Found)',
            self::SUBMISSION_REMOVED => 'Submission Removed',
            self::SUBMISSION_REMOVAL_FAILED => 'Submission Removal Failed',
            self::NUMBER_OF_RECORDS_MATCH => 'Number of Records Match',
            self::NUMBER_OF_RECORDS_DO_NOT_MATCH_THE_INITIAL_RECORDS_SENT => 'Number of Records do not Match the Initial Records Sent',
            self::THE_SUBMISSION_TOTAL_MATCH => 'The Submission Total Match',
            self::THE_SUBMISSION_TOTAL_CALCULATED_FOR_DOES_NOT_MATCH => 'The Submission Total Calculated for does not Match',
            self::UNKNOWN_ERROR_CONTACT_US => 'Unknown Error - Contact Us',
        };
    }
}
