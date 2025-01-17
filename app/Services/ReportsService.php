<?php

namespace App\Services;

use App\Enums\ClassBookingStatus;
use App\Enums\InvoicePaymentType;
use App\Enums\PaymentGateway;
use App\Enums\TagType;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Exports\ReportsExport;
use App\Helpers\JsonResource;
use App\Http\Resources\UserMinimalResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserTenantResource;
use App\Models\ClassBooking;
use App\Models\ClassDate;
use App\Models\Classes;
use App\Models\CoachRate;
use App\Models\Exercise;
use App\Models\Location;
use App\Models\LocationCategory;
use App\Models\LocationUserDiscount;
use App\Models\Package;
use App\Models\Programme;
use App\Models\Region;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserInvoice;
use App\Models\UserInvoiceItem;
use App\Models\UserInvoicePayment;
use App\Models\WodCaptureExercise;
use Carbon\CarbonPeriod;
use Closure;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;
use RuntimeException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

use function Aws\boolean_value;

class ReportsService
{
    const DEFAULT_CACHE_EXPIRY_IN_SECONDS = 1800; // 30 min

    const LONG_CACHE_EXPIRY_IN_SECONDS = 43200; // 12 hours

    private function cacheResults(string $functionName, array $requestData, Closure $callback, ?int $expiresAfter = self::DEFAULT_CACHE_EXPIRY_IN_SECONDS)
    {
        $cacheKey = md5('reportService-'.$functionName.json_encode($requestData));

        if (request()->header('X-CACHE-INVALIDATE')) {
            Cache::delete($cacheKey);
        }

        return Cache::remember($cacheKey, $expiresAfter, $callback);
    }

    /////////////////////////////////////////////////
    /// Dashboard report helper functions
    /////////////////////////////////////////////////

    public function getMembersMetrics(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {

            $tenantId = $request->input('filter.tenant_id');
            $locationId = $request->input('filter.location_id');
            $year = $request->input('filter.year');
            $isMonthlyBreakdown = boolean_value($request->input('filter.is_monthly_breakdown', false));
            $isCount = ! $isMonthlyBreakdown;

            return [
                'activeMembers' => $this->getActiveMembershipCount($tenantId, $locationId),
                'newMembers' => $this->getNewMemberships($tenantId, $year, $locationId, $isCount, $isMonthlyBreakdown),
                'deactivatedMembers' => $this->getDeactivatedMemberships($tenantId, $year, $locationId, $isCount, $isMonthlyBreakdown),
            ];
        });

    }

    public function getMembersDetailsForMetric(Request $request): Collection|array|int
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $tenantId = $request->input('filter.tenant_id');
            $locationId = $request->input('filter.location_id');
            $year = $request->input('filter.year');
            $month = $request->input('filter.month');

            return match ($request->input('filter.metric')) {
                'activeMembers' => $this->getActiveMembershipCount($tenantId, $locationId),
                'newMembers' => $this->getNewMemberships($tenantId, $year, $locationId, false, false, $month),
                'deactivatedMembers' => $this->getDeactivatedMemberships($tenantId, $year, $locationId, false, false, $month),
                default => [],
            };
        });
    }

    public function getMembersPerPackage(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $year = (int) $request->input('filter.year');

            $params = [
                'year' => $year,
                'tenantId' => $request->input('filter.tenant_id'),
            ];

            $locationAndWhere = '';
            $locationId = $request->input('filter.location_id');

            if ($locationId) {
                $locationAndWhere = 'AND utf.box_facility_id = :locationId';
                $params['locationId'] = $locationId;
            }

            $results = DB::select("
                SELECT
                    utp.effective_date, utp.end_date, p.package_name, utb.user_status_id, utb.deactivated_on
                FROM
                    user_to_package utp
                    LEFT JOIN users u ON u.user_id = utp.user_id
                    LEFT JOIN packages p ON p.package_id = utp.package_id
                    LEFT JOIN user_to_box utb ON utb.user_id = u.user_id AND utb.box_id = p.box_id
                    LEFT JOIN user_to_facility utf ON utf.user_id = u.user_id
                WHERE p.box_id = :tenantId
                AND p.package_limit_type_id != 4
                AND (YEAR(utp.effective_date) <= :year AND (YEAR(utp.end_date) >= :year OR utp.end_date IS NULL) AND utp.deleted = 0)
                AND (utb.deactivated_on IS NULL OR YEAR(utb.deactivated_on) >= :year)
                AND utb.deleted = 0
                AND (YEAR(utb.effective_date) <= :year AND YEAR(utb.end_date) >= :year)
                AND (YEAR(utf.effective_date) <= :year AND YEAR(utf.end_date) >= :year)
                $locationAndWhere
                GROUP BY utp.user_to_package_id;
            ", $params);

            $numberOfMonthsForYear = $year === (int) date('Y') ? (int) date('m') : 12;
            $packagesPerMonthData = [];

            foreach ($results as $result) {
                $months = array_fill_keys(range(1, $numberOfMonthsForYear), null);

                $userStatusId = $result->user_status_id;
                $userDeactivatedOn = isset($result->deactivated_on) ? Carbon::parse($result->deactivated_on) : null;
                $userDeactivatedOnMonth = $userDeactivatedOn instanceof Carbon ? (int) $userDeactivatedOn->format('m') : null;
                $userPackageStartDate = Carbon::parse($result->effective_date);
                $userPackageStartDateYear = (int) $userPackageStartDate->format('Y');
                $userPackageStartDateMonth = (int) $userPackageStartDate->format('m');
                $userPackageEndDate = Carbon::parse($result->end_date);
                $userPackageEndDateYear = $userPackageEndDate instanceof Carbon ? (int) $userPackageEndDate->format('Y') : null;
                $userPackageEndDateMonth = $userPackageEndDate instanceof Carbon ? (int) $userPackageEndDate->format('m') : null;

                if (! array_key_exists($result->package_name, $packagesPerMonthData)) {
                    $packagesPerMonthData[$result->package_name] = $months;
                }

                foreach ($packagesPerMonthData[$result->package_name] as $month => $count) {
                    $isUserPackageValid = ($userPackageStartDateYear < $year || ($userPackageStartDateYear === $year && $userPackageStartDateMonth <= $month)) && (! $userPackageEndDateYear || ($userPackageEndDateYear > $year || $userPackageEndDateMonth >= $month));
                    $isUserValid = ! $userDeactivatedOnMonth || $userStatusId !== UserStatus::DEACTIVATED->value || $userDeactivatedOnMonth >= $month;

                    if ($isUserPackageValid && $isUserValid) {
                        $packagesPerMonthData[$result->package_name][$month] = $count + 1;
                    }
                }
            }

            return $packagesPerMonthData;
        });
    }

    public function getMembersPerProgramme(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $data = [];
            $tenantId = $request->input('filter.tenant_id');
            $locationId = $request->input('filter.location_id');

            // Get active programmes
            $activeProgrammes = (new ProgrammeService())->getProgrammesForTenant($tenantId);

            /** @var Programme $programme */
            foreach ($activeProgrammes as $programme) {
                $total = DB::query()
                    ->selectRaw('COUNT(DISTINCT users.user_id) AS total')
                    ->from('users')
                    ->join('user_to_box', function ($query) {
                        $query->on('user_to_box.user_id', '=', 'users.user_id')
                            ->where('user_to_box.end_date', '>=', today()->toDateString());
                    })
                    ->join('user_to_facility', function ($query) {
                        $query->on('user_to_facility.user_id', '=', 'users.user_id')
                            ->where('user_to_facility.end_date', '>=', today()->toDateString());
                    })
                    ->join('box_facility', 'box_facility.box_facility_id', '=', 'user_to_facility.box_facility_id')
                    ->where('user_to_box.programme_id', $programme->getKey())
                    ->where('user_to_box.user_status_id', '!=', UserStatus::DEACTIVATED->value)
                    ->where('user_to_box.deleted', false)
                    ->where('box_facility.box_id', $tenantId)
                    ->where('user_to_box.user_type_id', '!=', UserType::LEAD_MEMBER->value)
                    ->when($locationId, function ($query) use ($locationId) {
                        $query->where('box_facility.box_facility_id', $locationId);
                    })
                    ->first()
                    ->total;

                $data[] = [
                    'id' => $programme->getKey(),
                    'name' => $programme->name,
                    'description' => trim($programme->description),
                    'is_active' => $programme->is_active,
                    'total' => $total,
                ];
            }

            return $data;
        });
    }

    public function getSchedulingMetrics(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $boxId = $request->input('filter.tenant_id');
            $boxFacility = Location::query()->find($request->input('filter.location_id'))?->getKey();
            $boxFacilityId = Location::query()->find($request->input('filter.location_id'))?->getKey();
            $year = $request->input('filter.year');
            $isMonthlyBreakdown = $request->input('filter.is_monthly_breakdown');

            if ($boxFacility instanceof Location && $boxId !== $boxFacility->tenant->getKey()) {
                abort(400, 'This location does not belong to this facility.');
            }

            $results = (new ClassBookingsService())->getClassBookingsCountForBoxByYear($boxId, $year, $boxFacilityId, $isMonthlyBreakdown)->toArray();

            if ($isMonthlyBreakdown) {
                $months = array_fill_keys(range(1, 12), null);

                $scheduleMetrics = [
                    'bookings' => $months,
                    'checkIns' => $months,
                    'allCancellations' => $months,
                    'lateCancellations' => $months,
                    'noShows' => $months,
                ];

                foreach ($results as $result) {
                    $month = $result['classDateMonth'];

                    $scheduleMetrics['bookings'][$month] = (int) $result['bookings'];
                    $scheduleMetrics['checkIns'][$month] = (int) $result['checkIns'];
                    $scheduleMetrics['allCancellations'][$month] = (int) $result['allCancellations'];
                    $scheduleMetrics['lateCancellations'][$month] = (int) $result['lateCancellations'];
                    $scheduleMetrics['noShows'][$month] = (int) $result['noShows'];
                }

                $results = $scheduleMetrics;
            }

            return $results;
        });
    }

    public function getAccountsMetrics(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $tenantId = $request->input('filter.tenant_id');
            $locationId = $request->input('filter.location_id');
            $year = (int) $request->input('filter.year');

            return [
                'totalInvoiced' => $totalInvoiced = round($this->getTotalInvoiced($tenantId, $locationId, $year)->totalInvoiced, 2),
                'totalReceived' => $totalReceived = round($this->getTotalPaymentsReceived($tenantId, $locationId, $year)->totalReceived, 2),
                'totalOutstanding' => round($totalInvoiced - $totalReceived, 2),
                'averageMemberLifespan' => $this->getAverageMemberLifespan($tenantId, $locationId),
                'averageMemberRevenue' => round($this->getAverageMemberRevenue($tenantId, $locationId, $year), 2),
            ];
        });
    }

    public function getTotalSalesAndPaymentsMetrics(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {

            $tenantId = $request->input('filter.tenant_id');
            $locationId = $request->input('filter.location_id');
            $year = (int) $request->input('filter.year');

            return [
                'totalSales' => $this->getTotalInvoiced($tenantId, $locationId, $year, true),
                'totalPayments' => $this->getTotalPaymentsReceived($tenantId, $locationId, $year, true),
            ];
        });
    }

    public function getSalesByTypeData(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $tenantId = $request->input('filter.tenant_id');
            $locationId = $request->input('filter.location_id');

            $results = $this->getTotalSalesByTypeForYearByMonth($tenantId, $request->input('filter.year'), $locationId);

            $months = array_fill_keys(range(1, 12), null);

            $totalPaymentsByType = [
                'other' => $months,
                'debitOrder' => $months,
            ];

            foreach ($results as $result) {
                $month = $result->month;
                $totalPaymentsByType['other'][$month] = (float) $result->other;
                $totalPaymentsByType['debitOrder'][$month] = (float) $result->debit_order;
            }

            return $totalPaymentsByType;
        });
    }

    public function getPaymentsByTypeData(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $tenantId = $request->input('filter.tenant_id');
            $locationId = $request->input('filter.location_id');

            $results = $this->getTotalPaymentsByTypeForYearByMonth($tenantId, $request->input('filter.year'), $locationId);

            $months = array_fill_keys(range(1, 12), null);

            $totalPaymentsByType = [
                'eft' => $months,
                'card' => $months,
                'cash' => $months,
                'adhoc' => $months,
                'debitOrder' => $months,
            ];

            foreach ($results as $result) {
                $month = $result->month;
                $totalPaymentsByType['eft'][$month] = (float) $result->eft;
                $totalPaymentsByType['card'][$month] = (float) $result->card;
                $totalPaymentsByType['cash'][$month] = (float) $result->cash;
                $totalPaymentsByType['adhoc'][$month] = (float) $result->adhoc;
                $totalPaymentsByType['debitOrder'][$month] = (float) $result->debit_order;
            }

            return $totalPaymentsByType;
        });
    }

    public function getPaymentsByTagData(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $tenantId = $request->input('filter.tenant_id');
            $locationId = $request->input('filter.location_id');
            $year = $request->input('filter.year');

            $paymentsTagData = null;
            $tags = (new TagsService())->getTags($tenantId, TagType::PAYMENT->value, $tenantId, 'boxes');

            foreach ($tags as $tag) {
                $paymentsTagData[$tag->name] = $this->getTotalPaymentsByTagForYearByMonth($tenantId, $year, $tag->id, $locationId);
            }

            return $paymentsTagData;
        });
    }

    public function getMembersByPaymentTypeData($request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $params = [
                'tenantId' => $request->input('filter.tenant_id'),
            ];

            $locationAndWhere = '';
            $locationId = $request->input('filter.location_id');

            if ($locationId) {
                $locationAndWhere = 'AND `box_facility`.`box_facility_id` = :locationId';
                $params['locationId'] = $locationId;
            }

            $sql = "
                SELECT
                    user_to_box.user_debit_status_id
                FROM
                    `user_to_box`
                    INNER JOIN `box_facility` ON `box_facility`.`box_id` = `user_to_box`.`box_id`
                    INNER JOIN `users` ON `users`.`user_id` = `user_to_box`.`user_id`
                    INNER JOIN `user_to_facility` ON `user_to_facility`.`user_id` = `user_to_box`.`user_id` AND `user_to_facility`.`box_facility_id` = box_facility.box_facility_id
                WHERE
                    `user_to_box`.`user_status_id` = 2
                    AND `user_to_box`.`user_type_id` = 4
                    AND `user_to_box`.`deleted` = 0
                    AND `user_to_box`.`end_date` >= CURDATE()
                    AND `user_to_facility`.`end_date` >= CURDATE()
                    AND `users`.`deleted` = 0
                    AND `user_to_box`.`box_id` = :tenantId
                    AND `box_facility`.`is_active` = 1
                    $locationAndWhere
            ";

            $queryResults = DB::select($sql, $params);

            $results = [
                'cash_eft_card' => 0,
                'debit_order' => 0,
                'no_payment' => 0,
                'upfront_payment' => 0,
                'online_payment' => 0,
            ];

            foreach ($queryResults as $row) {
                $userDebitStatusId = (int) $row->user_debit_status_id;

                if ($userDebitStatusId === UserDebitStatus::CASH->value) {
                    $results['cash_eft_card']++;
                } elseif ($userDebitStatusId === UserDebitStatus::DEBIT_ORDER->value) {
                    $results['debit_order']++;
                } elseif ($userDebitStatusId === UserDebitStatus::NO_PAYMENT->value) {
                    $results['no_payment']++;
                } elseif ($userDebitStatusId === UserDebitStatus::UP_FRONT_PAYMENT->value) {
                    $results['upfront_payment']++;
                } elseif ($userDebitStatusId === UserDebitStatus::ONLINE_PAYMENT->value) {
                    $results['online_payment']++;
                }
            }

            return $results;
        });
    }

    public function getLeadsMetricsData(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $tenantId = $request->input('filter.tenant_id');
            $locationId = $request->input('filter.location_id');
            $year = $request->input('filter.year');
            $isMonthlyBreakdown = $request->input('filter.is_monthly_breakdown');
            $isCount = ! $isMonthlyBreakdown;

            return [
                'new' => $this->getLeadMembers($tenantId, $year, $locationId, false, $isCount, $isMonthlyBreakdown),
                'converted' => $this->getLeadMembers($tenantId, $year, $locationId, true, $isCount, $isMonthlyBreakdown),
            ];
        });
    }

    public function getDebtorsSummaryByPeriods(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $tenantId = $request->input('filter.tenant_id');
            $locationId = $request->input('filter.location_id');

            $boxFacilityAndWhere = '';

            if ($locationId) {
                $boxFacilityAndWhere = 'AND bf.box_facility_id = '.$locationId;
            }

            $sql =
                "
            SELECT SUM(outstanding) as amount, days
            FROM
            (
                SELECT SUM(i.amount - COALESCE(p.amount, 0)) AS outstanding,
                CASE
                    WHEN DATEDIFF(NOW(), i.due_on) <= 30 THEN '30'
                    WHEN DATEDIFF(NOW(), i.due_on) <= 60 THEN '60'
                    WHEN DATEDIFF(NOW(), i.due_on) <= 90 THEN '90'
                    ELSE '>90'
                END AS days
                FROM `finance_invoices` i
                LEFT JOIN (SELECT SUM(amount) AS amount, deleted, invoice_id, user_to_facility_id FROM `finance_payments` WHERE deleted = 0 GROUP BY (invoice_id)) p ON p.invoice_id = i.invoice_id
                INNER JOIN user_to_facility utf ON utf.user_to_facility_id = i.user_to_facility_id
                INNER JOIN box_facility bf ON bf.box_facility_id = utf.box_facility_id
                INNER JOIN users u ON u.user_id = utf.user_id
                LEFT JOIN user_to_batch utb ON utb.invoice_id = i.invoice_id AND utb.is_active = 1
                WHERE COALESCE(i.deleted,0) = 0 AND COALESCE(p.deleted, 0) = 0
                AND bf.box_id = $tenantId
                AND bf.is_active = 1
                AND i.type = 'invoice'
                AND (i.status = 'unpaid' OR i.status = 'paid' OR i.status = 'submitted' OR (i.status = 'pending' AND u.user_status_id != 4))
                AND i.due_on <= NOW()
                AND i.amount > COALESCE(p.amount, 0)
                AND u.user_id NOT IN (SELECT user_id FROM users_on_hold WHERE deleted_at IS NULL)
                $boxFacilityAndWhere
                GROUP BY days
                HAVING outstanding > 0
            ) a
            GROUP BY days
            ORDER BY days
        ";

            $results = DB::select($sql);

            $data = ['30' => 0, '60' => 0, '90' => 0, '>90' => 0];

            foreach ($results as $row) {
                $data[$row->days] = (float) $row->amount;
            }

            return $data;
        });
    }

    public function getMemberMovementData(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $tenantId = $request->input('filter.tenant_id');
            $locationId = $request->input('filter.location_id');

            $params = ['boxId' => $tenantId];

            $locationWhere = '';

            if ($locationId) {
                $locationWhere = 'AND bf.box_facility_id = :boxFacilityId';
                $params['boxFacilityId'] = $locationId;
            }

            $deactivatedSql = "
                SELECT CONCAT(YEAR(utf.end_date), '-', MONTH(utf.end_date)) AS `month`, COUNT(u.user_id) AS `count`
                FROM users u
                INNER JOIN user_to_facility utf ON utf.user_id = u.user_id
                INNER JOIN box_facility bf ON utf.box_facility_id = bf.box_facility_id $locationWhere
                WHERE utf.end_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                AND bf.box_id = :boxId
                AND bf.is_active = 1
                GROUP BY `month`
                ORDER BY `month` ASC;
            ";

            $activatedSql = "
                SELECT CONCAT(YEAR(utf.effective_date), '-', MONTH(utf.effective_date)) AS `month`, COUNT(u.user_id) AS `count`
                FROM users u
                INNER JOIN user_to_facility utf ON utf.user_id = u.user_id
                INNER JOIN box_facility bf ON utf.box_facility_id = bf.box_facility_id $locationWhere
                WHERE utf.effective_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                AND bf.box_id = :boxId
                AND bf.is_active = 1
                GROUP BY `month`
                ORDER BY `month` ASC;
            ";

            $deactivatedData = DB::select($deactivatedSql, $params);
            $activatedData = DB::select($activatedSql, $params);

            $extractCounts = function ($deactivatedLookup, $activatedLookup, $monthAndYear) {
                $deactivatedCount = 0;
                $activatedCount = 0;

                foreach ($deactivatedLookup as $index => $data) {
                    if ($data->month === $monthAndYear) {
                        $deactivatedCount = (int) $data->count;

                        unset($deactivatedLookup[$index]);
                    }
                }

                foreach ($activatedLookup as $index => $data) {
                    if ($data->month === $monthAndYear) {
                        $activatedCount = (int) $data->count;

                        unset($activatedLookup[$index]);
                    }
                }

                return [
                    'activated' => $activatedCount,
                    'deactivated' => $deactivatedCount,
                ];
            };

            $getTotalForDate = function ($params, $date, $locationWhere) {
                $totalSql = "
                    SELECT COUNT(*) AS `count`
                    FROM users u
                    INNER JOIN user_to_facility utf ON utf.user_id = u.user_id
                    INNER JOIN box_facility bf ON utf.box_facility_id = bf.box_facility_id $locationWhere
                    WHERE :date BETWEEN utf.effective_date AND utf.end_date
                    AND bf.box_id = :boxId
                    AND bf.is_active = 1
                    AND u.deleted = 0
                ";

                return (int) DB::select($totalSql, array_merge(['date' => $date->toDateString()], $params))[0]->count;
            };

            $workingDate = now()->subYear();
            $today = now();

            $data = [];

            while ($workingDate->format('Y-n') <= $today->format('Y-n')) {
                $counts = $extractCounts($deactivatedData, $activatedData, $workingDate->format('Y-n'));

                $data[] = [
                    'yearMonth' => $workingDate->format('Y-n'),
                    'activatedMembers' => $counts['activated'],
                    'deactivatedMembers' => $counts['deactivated'],
                    'totalMembers' => $getTotalForDate($params, $workingDate, $locationWhere),
                ];

                $workingDate->addMonth();
            }

            return $data;
        });
    }

    public function getInactiveMandatesCount(Request $request)
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $tenantId = $request->input('filter.tenant_id');
            $tenant = Tenant::find($request->input('filter.tenant_id'));
            $location = $request->input('filter.location_id') ? Location::find($request->input('filter.location_id')) : null;

            $locations = $location instanceof Location ? collect([$location]) : $tenant->locations()->get();

            $locationIds = $locations->filter(function ($facility) {
                return $facility->payment_gateway_id === PaymentGateway::GO_CARDLESS->value;
            })->pluck('box_facility_id');

            $sql = "
                SELECT COUNT(u.user_id) AS `count`
                FROM users u
                NATURAL LEFT JOIN finance_user_gocardless_credentials fugc
                INNER JOIN user_to_facility utf ON utf.user_id = u.user_id AND (CURDATE() BETWEEN utf.effective_date AND utf.end_date)
                INNER JOIN user_to_box utb ON utb.user_id = u.user_id
                WHERE u.user_status_id = 2
                AND u.user_debit_status_id = 2
                AND (fugc.status IS NULL OR fugc.status != 'active')
                AND utb.box_id = $tenantId
            ";

            if ($locationIds->isNotEmpty()) {
                $sql .= 'AND utf.box_facility_id IN ('.$locationIds->implode(',').')';
            }

            return DB::selectOne($sql);
        });
    }

    private function getActiveMembershipCount(int $tenantId, ?int $locationId = null): int|array
    {
        return TenantUser::query()
            ->withoutGlobalScopes()
            ->join('box_facility', 'box_facility.box_id', '=', 'user_to_box.box_id')
            ->join('users', 'users.user_id', '=', 'user_to_box.user_id')
            ->join('user_to_facility', 'user_to_facility.user_id', '=', 'user_to_box.user_id')
            ->where('user_to_box.box_id', '=', $tenantId)
            ->where('user_to_box.user_status_id', '=', 2)
            ->where('user_to_box.user_type_id', '=', 4)
            ->where('user_to_box.end_date', '>=', today()->format('Y-m-d'))
            ->where('user_to_box.deleted', '=', 0)
            ->when($locationId, function ($query) use ($locationId) {
                $query->where('box_facility.box_facility_id', '=', $locationId);
            })
            ->where('box_facility.is_active', '=', 1)
            ->where('user_to_facility.end_date', '>=', today()->format('Y-m-d'))
            ->where('user_to_facility.box_facility_id', '=', DB::raw('box_facility.box_facility_id'))
            ->where('users.deleted', '=', 0)
            ->count();
    }

    private function getNewMemberships(int $tenantId, int $year, ?int $locationId = null, ?bool $isCount = false, ?bool $isMonthlyBreakdown = false, ?int $month = null): Collection|array|int
    {
        $date = Carbon::parse("$year-01-01");

        if ($month) {
            $date->setMonth($month);
            $startDate = $date->startOfMonth()->toDateString();
            $endDate = $date->endOfMonth()->toDateString();
        } else {
            $startDate = $date->startOfYear()->toDateString();
            $endDate = $date->endOfYear()->toDateString();
        }

        $newMembers = TenantUser::query()
            ->withoutGlobalScopes()
            ->join('box_facility', 'box_facility.box_id', '=', 'user_to_box.box_id')
            ->join('users', 'users.user_id', '=', 'user_to_box.user_id')
            ->join('user_to_facility', 'user_to_facility.user_id', '=', 'user_to_box.user_id')
            ->where('user_to_box.box_id', '=', $tenantId)
            ->where('user_to_box.user_status_id', '=', UserStatus::ACTIVE)
            ->where('user_to_box.user_type_id', '=', UserType::GYM_MEMBER)
            ->where('user_to_box.end_date', '>=', today()->toDateString())
            ->where('user_to_box.deleted', '=', 0)
            ->when($locationId, function ($query) use ($locationId) {
                $query->where('box_facility.box_facility_id', '=', $locationId);
            })
            ->where('box_facility.is_active', '=', 1)
            ->where('user_to_facility.box_facility_id', '=', DB::raw('box_facility.box_facility_id'))
            ->where(DB::raw(today()->format('Y-m-d')), '<=', DB::raw('user_to_facility.end_date'))
            ->where('user_to_facility.effective_date', '>=', $startDate)
            ->where('user_to_facility.effective_date', '<=', $endDate)
            ->where('users.deleted', '=', 0)
            ->when(boolval($isCount), function ($query) {
                return $query->count();
            }, function (Builder $query) {
                return $query
                    ->select(
                        'users.name',
                        'users.surname',
                        'users.email',
                        'user_to_facility.effective_date')
                    ->get();
            });

        if ($isMonthlyBreakdown) {
            return $this->sortDataByMonthAndGetTotalCount('effective_date', $newMembers->toArray(), $year);
        }

        return $newMembers;
    }

    private function getDeactivatedMemberships(int $tenantId, int $year, ?int $locationId = null, ?bool $isCount = false, ?bool $isMonthlyBreakdown = false, ?int $month = null): Collection|array|int
    {
        $date = Carbon::parse("$year-01-01");

        if ($month) {
            $date->setMonth($month);
            $startDate = $date->startOfMonth()->toDateString();
            $endDate = $date->endOfMonth()->toDateString();
        } else {
            $startDate = $date->startOfYear()->toDateString();
            $endDate = $date->endOfYear()->toDateString();
        }

        $deactivatedMembers = TenantUser::query()
            ->withoutGlobalScopes()
            ->join('box_facility', 'box_facility.box_id', '=', 'user_to_box.box_id')
            ->join('users', 'users.user_id', '=', 'user_to_box.user_id')
            ->join('user_to_facility', 'user_to_facility.user_id', '=', 'user_to_box.user_id')
            ->where('user_to_box.box_id', '=', $tenantId)
            ->where('user_to_box.user_status_id', '=', UserStatus::DEACTIVATED)
            ->where('user_to_box.user_type_id', '=', UserType::GYM_MEMBER)
            ->where('user_to_box.end_date', '>=', today()->toDateString())
            ->where('user_to_box.deleted', '=', 0)
            ->when($locationId, function ($query) use ($locationId) {
                $query->where('box_facility.box_facility_id', '=', $locationId);
            })
            ->where('box_facility.is_active', '=', 1)
            ->where('user_to_facility.box_facility_id', '=', DB::raw('box_facility.box_facility_id'))
            ->where(DB::raw(today()->toDateString()), '<=', DB::raw('user_to_facility.end_date'))
            ->where('users.deleted', '=', 0)
            ->whereDate('user_to_box.deactivated_on', '>=', $startDate)
            ->whereDate('user_to_box.deactivated_on', '<=', $endDate)
            ->when(boolval($isCount), function ($query) {
                return $query->count();
            }, function (Builder $query) {
                return $query
                    ->select(
                        'users.user_id',
                        'user_to_box.user_to_box_id as user_tenant_id',
                        'user_to_box.user_type_id',
                        'users.name',
                        'users.surname',
                        'users.email',
                        'user_to_box.updated_on as date_modified_on',
                        'user_to_box.deactivated_on as deactivated_on'
                    )
                    ->get();
            });

        if ($isMonthlyBreakdown) {
            return $this->sortDataByMonthAndGetTotalCount('deactivated_on', $deactivatedMembers->toArray(), $year);
        }

        return $deactivatedMembers;
    }

    private function sortDataByMonthAndGetTotalCount(string $sortBy, $dataToBeSorted, int $year): array
    {
        $numberOfMonthsForYear = $year === (int) date('Y') ? (int) date('m') : 12;
        $monthlyBreakdown = array_fill_keys(range(1, $numberOfMonthsForYear), 0);

        foreach ($dataToBeSorted as $row) {
            $row = (array) $row;
            $monthlyBreakdown[date('n', strtotime($row[$sortBy]))]++;
        }

        return array_replace(array_fill_keys(range(1, 12), null), $monthlyBreakdown);
    }

    private function getTotalInvoiced(int $tenantId, ?int $locationId = null, ?int $year = null, ?bool $isMonthlyBreakdown = false)
    {
        $year = $year ?? date('Y');

        return UserInvoice::query()
            ->leftJoin('user_to_facility', 'finance_invoices.user_to_facility_id', '=', 'user_to_facility.user_to_facility_id')
            ->leftJoin('box_facility', 'user_to_facility.box_facility_id', '=', 'box_facility.box_facility_id')
            ->whereNested(function ($query) use ($year, $locationId, $tenantId) {
                $query->where('finance_invoices.type', '=', 'invoice');
                $query->where('finance_invoices.status', '!=', 'credited');
                $query->whereYear('finance_invoices.due_on', '=', $year);
                $query->where('finance_invoices.deleted', '=', 0);
                $query->where('box_facility.box_id', '=', $tenantId);
                $query->where('box_facility.is_active', '=', 1);
                $query->when($locationId, function ($query) use ($locationId) {
                    $query->where('user_to_facility.box_facility_id', '=', $locationId);
                });
            })
            ->orWhere(function ($query) use ($year, $tenantId) {
                $query->whereNull('user_to_facility.user_to_facility_id');
                $query->where('finance_invoices.type', '=', 'invoice');
                $query->where('finance_invoices.status', '!=', 'credited');
                $query->whereYear('finance_invoices.due_on', '=', $year);
                $query->where('finance_invoices.deleted', '=', 0);
                $query->where('box_facility.box_id', '=', $tenantId);
                $query->where('box_facility.is_active', '=', 1);
            })
            ->when($isMonthlyBreakdown, function ($query) {
                $query->select(DB::raw('MONTH(finance_invoices.due_on) AS month'), DB::raw('SUM(finance_invoices.amount) as total'));
                $query->groupBy('month');
                $data = $query->get();

                $data->map(function ($item) {
                    $item->total = (float) $item->total;
                });

                $monthlyBreakdown = array_fill_keys(range(1, 12), null);

                foreach ($data as $month) {
                    $monthlyBreakdown[$month->month] = $month->total;
                }

                return $monthlyBreakdown;

            }, function ($query) {
                $query->select(DB::raw('SUM(finance_invoices.amount) as totalInvoiced'));

                return $query->first();
            });
    }

    private function getTotalPaymentsReceived(int $tenantId, ?int $locationId = null, ?int $year = null, ?bool $isMonthlyBreakdown = false)
    {
        $year = $year ?? date('Y');

        return UserInvoicePayment::query()
            ->withoutGlobalScopes()
            ->join('finance_invoices', 'finance_invoices.invoice_id', '=', 'finance_payments.invoice_id')
            ->leftJoin('user_to_facility', 'finance_invoices.user_to_facility_id', '=', 'user_to_facility.user_to_facility_id')
            ->leftJoin('box_facility', 'user_to_facility.box_facility_id', '=', 'box_facility.box_facility_id')
            ->whereNested(function ($query) use ($tenantId, $locationId, $year) {
                $query->where('box_facility.box_id', '=', $tenantId);
                $query->when($locationId, function ($query) use ($locationId) {
                    $query->where('box_facility.box_facility_id', '=', $locationId);
                });
                $query->whereYear('finance_invoices.due_on', '=', $year);
                $query->where('finance_invoices.type', '=', 'invoice');
                $query->where('finance_invoices.status', '!=', 'credited');
                $query->whereDate('finance_invoices.due_on', '<=', today()->format('Y-m-d'));
                $query->where('box_facility.is_active', '=', 1);
                $query->where('finance_payments.deleted', '=', 0);
                $query->where('finance_invoices.deleted', '=', 0);

            })
            ->orWhere(function ($query) use ($year) {
                $query->whereNull('user_to_facility.user_to_facility_id');
                $query->whereYear('finance_invoices.due_on', '=', $year);
                $query->where('finance_invoices.type', '=', 'invoice');
                $query->where('finance_invoices.status', '!=', 'credited');
                $query->whereDate('finance_invoices.due_on', '<=', today()->format('Y-m-d'));
                $query->where('box_facility.is_active', '=', 1);
                $query->where('finance_payments.deleted', '=', 0);
                $query->where('finance_invoices.deleted', '=', 0);
            })
            ->when($isMonthlyBreakdown, function (Builder $query) {
                $query->select(DB::raw('MONTH(finance_payments.date_time) AS month'), DB::raw('SUM(finance_payments.amount) as total'));
                $query->groupBy('month');
                $data = $query->get();

                $data->map(function ($item) {
                    $item->total = (float) $item->total;
                });

                $monthlyBreakdown = array_fill_keys(range(1, 12), null);

                foreach ($data as $month) {
                    $monthlyBreakdown[$month->month] = $month->total;
                }

                return $monthlyBreakdown;

            }, function (Builder $query) {
                $query->select(DB::raw('SUM(finance_payments.amount) as totalReceived'));

                return $query->first();
            });

    }

    private function getAverageMemberLifespan($boxId, $boxFacilityId = null): array
    {
        $params = [
            'boxId' => $boxId,
        ];

        $boxFacilityAndWhere = '';

        if ($boxFacilityId) {
            $boxFacilityAndWhere = 'AND bf.box_facility_id = :boxFacilityId';
            $params['boxFacilityId'] = $boxFacilityId;
        }

        $sql =
            "
            SELECT utf.user_to_facility_id, utf.effective_date, utf.end_date, IF(utf.end_date >= CURDATE(), datediff(CURDATE(), utf.effective_date), datediff(utf.end_date, utf.effective_date)) AS days
            FROM user_to_facility utf
            LEFT JOIN box_facility bf ON bf.box_facility_id = utf.box_facility_id
            LEFT JOIN user_to_box utb ON utf.user_id = utb.user_id AND utb.box_id =  bf.box_id
            WHERE utb.user_status_id IN (2, 6)
            AND bf.box_id = :boxId
            AND bf.is_active = 1
            AND utf.effective_date < utf.end_date
            $boxFacilityAndWhere";

        $results = DB::select($sql, $params);

        $count = 0;
        $totalDays = 0;

        foreach ($results as $result) {
            $days = (int) $result->days;

            if ($days > 0) {
                $totalDays += $days;
                $count++;
            }
        }

        if ($count == 0) {
            return [
                'averageTotalDays' => 0,
                'averageYears' => 0,
                'averageMonths' => 0,
                'averageDays' => 0,
            ];
        }

        $averageTotalDays = round($totalDays / $count); // days you want to convert

        $years = ($averageTotalDays / 365); // days / 365 days
        $years = floor($years); // Remove all decimals

        $month = ($averageTotalDays % 365) / 30.5; // I choose 30.5 for Month (30,31) ;)
        $month = floor($month); // Remove all decimals

        $days = ($averageTotalDays % 365) % 30.5;

        return [
            'averageTotalDays' => $averageTotalDays,
            'averageYears' => $years,
            'averageMonths' => $month,
            'averageDays' => $days,
        ];
    }

    private function getAverageMemberRevenue($boxId, $boxFacilityId = null, $year = null): string
    {
        $params = [
            'boxId' => $boxId,
            'year' => $year,
        ];

        $boxFacilityAndWhere = '';

        if ($boxFacilityId) {
            $boxFacilityAndWhere = 'AND bf.box_facility_id = :boxFacilityId';
            $params['boxFacilityId'] = $boxFacilityId;
        }

        $sql = "
            SELECT MONTH(p.date_time) AS `month`, SUM(p.amount) AS amount, COUNT(u.user_id) AS count
            FROM `finance_payments` p
            INNER JOIN finance_invoices i ON p.invoice_id = i.invoice_id
            INNER JOIN user_to_facility utf ON utf.user_to_facility_id = p.user_to_facility_id
            INNER JOIN box_facility bf ON bf.box_facility_id = utf.box_facility_id
            INNER JOIN users u ON u.user_id = utf.user_id
            WHERE YEAR(p.date_time) = :year
            AND p.deleted = 0
            AND i.deleted = 0
            AND bf.box_id = :boxId
            AND bf.is_active = 1
            AND i.type = 'invoice'
            AND i.status != 'credited'
            AND DATE(p.date_time) < CURDATE()
            $boxFacilityAndWhere
        ";

        $averageRevenuePerMonth = 0;

        $results = DB::select($sql, $params);

        if ($results && $results[0]->count) {
            $averageRevenuePerMonth = $results[0]->amount / $results[0]->count;
        }

        return number_format($averageRevenuePerMonth, 2, '.', '');
    }

    private function getTotalSalesByTypeForYearByMonth(int $boxId, int $year, ?int $boxFacilityId = null): array
    {
        $boxFacilityAndWhere = '';

        if ($boxFacilityId) {
            $boxFacilityAndWhere = 'AND bf.box_facility_id ='.$boxFacilityId;
        }

        $sql = "
            SELECT `month`,
                   SUM(other) AS other,
                   SUM(debit_order) AS debit_order
            FROM (
                (
                   SELECT MONTH(i.due_on) AS `month`,
                          SUM(CASE
                                  WHEN utb.invoice_id IS NULL THEN i.amount
                                  ELSE 0
                              END) AS other,
                          SUM(CASE
                                  WHEN utb.invoice_id IS NOT NULL THEN i.amount
                                  ELSE 0
                              END) AS debit_order
                   FROM `finance_invoices` i
                   LEFT JOIN user_to_batch utb ON utb.invoice_id = i.invoice_id
                   LEFT JOIN user_to_facility utf ON utf.user_to_facility_id = i.user_to_facility_id
                   LEFT JOIN box_facility bf ON bf.box_facility_id = utf.box_facility_id
                   WHERE YEAR(i.due_on) = $year
                     AND bf.box_id = $boxId
                     AND bf.is_active = 1
                     AND i.type = 'invoice'
                     AND i.status != 'credited'
                     AND i.deleted = 0
                     $boxFacilityAndWhere
                   GROUP BY `month`)
                UNION
                (
                   SELECT MONTH(i.due_on) AS `month`,
                          SUM(CASE
                                  WHEN utb.invoice_id IS NULL THEN i.amount
                                  ELSE 0
                              END) AS other,
                          SUM(CASE
                                  WHEN utb.invoice_id IS NOT NULL THEN i.amount
                                  ELSE 0
                              END) AS debit_order
                   FROM `finance_invoices` i
                   LEFT JOIN user_to_batch utb ON utb.invoice_id = i.invoice_id
                   LEFT JOIN box_facility bf ON i.box_facility_id = bf.box_facility_id
                   WHERE YEAR(i.due_on) = $year
                     AND bf.box_id = $boxId
                     AND bf.is_active = 1
                     AND i.type = 'invoice'
                     AND i.status != 'credited'
                     AND i.deleted = 0
                     $boxFacilityAndWhere
                   GROUP BY `month`
                   )
            ) invoicedAmount
            GROUP BY `month`
        ";

        return DB::select($sql);
    }

    private function getTotalPaymentsByTypeForYearByMonth(int $boxId, int $year, ?int $boxFacilityId = null): array
    {
        $boxFacilityAndWhere = '';

        if ($boxFacilityId) {
            $boxFacilityAndWhere = 'AND bf.box_facility_id = '.$boxFacilityId;
        }

        $sql = "
            SELECT
                `month`,
                SUM(eft) AS eft,
                SUM(card) AS card,
                SUM(cash) AS cash,
                SUM(adhoc) AS adhoc,
                SUM(debit_order) AS debit_order
            FROM
                ((
                    SELECT
                        MONTH(p.date_time) AS `month`,
                        SUM(CASE
                            WHEN p.`type` = 'eft' THEN p.amount
                            ELSE 0
                        END) AS eft,
                        SUM(CASE
                            WHEN p.`type` = 'card' THEN p.amount
                            ELSE 0
                        END) AS card,
                        SUM(CASE
                            WHEN p.`type` = 'cash' THEN p.amount
                            ELSE 0
                        END) AS cash,
                        SUM(CASE
                            WHEN p.`type` = 'adhoc' THEN p.amount
                            ELSE 0
                        END) AS adhoc,
                        SUM(CASE
                            WHEN p.`type` = 'debit_order' THEN p.amount
                            ELSE 0
                        END) AS debit_order
                    FROM
                        `finance_payments` p
                    LEFT JOIN finance_invoices i ON p.invoice_id = i.invoice_id
                    LEFT JOIN user_to_facility utf ON utf.user_to_facility_id = i.user_to_facility_id
                    LEFT JOIN box_facility bf ON bf.box_facility_id = utf.box_facility_id
                    WHERE bf.box_id = $boxId
                        AND YEAR(p.date_time) = $year
                        AND i.type = 'invoice'
                        AND i.status != 'credited'
                        AND DATE(p.date_time) < CURDATE()
                        AND bf.is_active = 1
                        AND p.deleted = 0
                        AND i.deleted = 0
                        $boxFacilityAndWhere
                    GROUP BY `month`
                )
                UNION
                (
                    SELECT
                        MONTH(p.date_time) AS `month`,
                        SUM(CASE
                            WHEN p.`type` = 'eft' THEN p.amount
                            ELSE 0
                        END) AS eft,
                        SUM(CASE
                            WHEN p.`type` = 'card' THEN p.amount
                            ELSE 0
                        END) AS card,
                        SUM(CASE
                            WHEN p.`type` = 'cash' THEN p.amount
                            ELSE 0
                        END) AS cash,
                        SUM(CASE
                            WHEN p.`type` = 'adhoc' THEN p.amount
                            ELSE 0
                        END) AS adhoc,
                        SUM(CASE
                            WHEN p.`type` = 'debit_order' THEN p.amount
                            ELSE 0
                        END) AS debit_order
                    FROM
                        `finance_payments` p
                    LEFT JOIN finance_invoices i ON p.invoice_id = i.invoice_id
                    LEFT JOIN box_facility bf ON i.box_facility_id = bf.box_facility_id
                    WHERE bf.box_id = $boxId
                        AND YEAR(p.date_time) = $year
                        AND i.type = 'invoice'
                        AND i.status != 'credited'
                        AND DATE(p.date_time) < CURDATE()
                        AND bf.is_active = 1
                        AND p.deleted = 0
                        AND i.deleted = 0
                        $boxFacilityAndWhere
                    GROUP BY `month`
                )
            ) a
            GROUP BY `month`
        ";

        return DB::select($sql);
    }

    private function getTotalPaymentsByTagForYearByMonth(int $tenantId, int $year, int $tagId, ?int $locationId = null): array
    {
        $params = [
            'tenantId' => $tenantId,
            'year' => $year,
            'tagId' => $tagId,
        ];

        $boxFacilityAndWhere = '';

        if ($locationId) {
            $boxFacilityAndWhere = 'AND bf.box_facility_id = :locationId';
            $params['locationId'] = $locationId;
        }

        $morphable = (new UserInvoicePayment())->getTable();

        $sql = "
            SELECT MONTH(p.date_time) AS `month`, SUM(p.amount) AS total
            FROM `finance_payments` p
                     INNER JOIN finance_invoices i ON p.invoice_id = i.invoice_id
                     INNER JOIN user_to_facility utf ON utf.user_to_facility_id = i.user_to_facility_id
                     INNER JOIN box_facility bf ON bf.box_facility_id = utf.box_facility_id
                     INNER JOIN users u ON u.user_id = utf.user_id
                     LEFT JOIN `taggables` ON p.`payment_id` = taggables.`morphable_id` AND taggables.`morphable_model` = '$morphable'
                     LEFT JOIN `tags` ON taggables.`tag_id` = tags.`id` AND tags.`type` = 'payment_processor'
            WHERE p.deleted = 0
              AND i.deleted = 0
              AND bf.box_id = :tenantId
              AND bf.is_active = 1
              AND YEAR(p.date_time) = :year
              AND i.type = 'invoice'
              AND i.status != 'credited'
              AND DATE(p.date_time) < CURDATE()
              AND tags.id = :tagId
            $boxFacilityAndWhere
            GROUP BY `month`
            ORDER BY `month`
        ";

        return DB::select($sql, $params);
    }

    /////////////////////////////////////////////////
    /// Attendance report helper functions
    /////////////////////////////////////////////////

    public function attendanceOverview(Request $request): array
    {
        $keys = ['tenant_id', 'location_id', 'user_id', 'class_id'];
        $params = [];

        foreach ($keys as $key) {
            $params[$key] = Arr::get($request->filter, $key);
        }

        $isCheckedIn = false;
        $classBookingStatusId = $request->input('filter.booking_status_id');

        if ($classBookingStatusId == ClassBookingStatus::CHECKED_IN->value) {
            $isCheckedIn = true;
            $classBookingStatusId = ClassBookingStatus::BOOKED->value;
        }

        $query = ClassDate::query()
            ->join('classes', 'class_to_dates.class_id', '=', 'classes.class_id')
            ->join('class_bookings', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id')
            ->where('class_booking_status_id', '=', $classBookingStatusId)
            ->when($isCheckedIn, fn (Builder $query) => $query->where('class_bookings.is_checked_in', $isCheckedIn))
            ->when($params['user_id'], fn ($query) => $query->where('user_id', $params['user_id']))
            ->when($params['class_id'], fn ($query) => $query->where('class_to_dates.class_id', $params['class_id']))
            ->when($params['tenant_id'], fn ($query) => $query->where('classes.box_id', $params['tenant_id']))
            ->when($params['location_id'], fn ($query) => $query->where('classes.box_facility_id', $params['location_id']))
            ->when($request->input('filter.year'), function (Builder $query, string $year) {
                $year = Carbon::today()->setYear($year);
                $query->where('class_to_dates.class_date', '>=', $year->startOfYear()->toDateString())
                    ->where('class_to_dates.class_date', '<=', $year->endOfYear()->toDateString());
            })
            ->selectRaw('COUNT(class_bookings.class_booking_id)  AS `total`, MONTH(class_to_dates.class_date) AS `month`')
            ->groupBy(DB::raw('MONTH(class_to_dates.class_date), YEAR(class_to_dates.class_date)'));

        return $this->sortResultsByMonth($query->get());
    }

    public function classAttendance()
    {
        [$start, $end] = explode(',', request()->input('filter.class_dates_between'));

        return QueryBuilder::for(Classes::class)
            ->select('classes.*',
                DB::raw("COUNT(DISTINCT CASE WHEN class_to_dates.class_date BETWEEN '$start'
                                    AND '$end' THEN
                                    class_to_dates.class_to_date_id
                                END) AS 'class_date_count',
                            SUM(
                                CASE WHEN class_bookings.class_booking_status_id = 1 THEN
                                    1
                                ELSE
                                    0
                                END) AS 'booked',
                            SUM(
                                CASE WHEN class_bookings.class_booking_status_id = 2 THEN
                                    1
                                ELSE
                                    0
                                END) AS 'cancelled',
                            SUM(
                                CASE WHEN class_bookings.class_booking_status_id = 3 THEN
                                    1
                                ELSE
                                    0
                                END) AS 'cancelled_after_threshold',
                            SUM(
                                CASE WHEN class_bookings.class_booking_status_id = 4 THEN
                                    1
                                ELSE
                                    0
                                END) AS 'cancelled_by_coach',
                            SUM(
                                CASE WHEN class_bookings.class_booking_status_id IN(2, 3, 4) THEN
                                    1
                                ELSE
                                    0
                                END) AS 'all_cancellations',
                            SUM(
                                CASE WHEN class_bookings.class_booking_status_id = 5 THEN
                                    1
                                ELSE
                                    0
                                END) AS 'no_show',
                            SUM(
                                CASE WHEN class_bookings.class_booking_status_id = 6 THEN
                                    1
                                ELSE
                                    0
                                END) AS 'checked_in',
                            (
                                SELECT
                                    SUM(
                                        CASE WHEN cd.class_limit IS NOT NULL THEN
                                            cd.class_limit
                                        ELSE
                                            classes.class_limit
                                        END)
                                FROM
                                    class_to_dates cd
                                    JOIN classes ON cd.class_id = classes.class_id
                                WHERE
                                    cd.class_id = class_to_dates.class_id
                                    AND cd.class_date BETWEEN '$start'
                                    AND '$end'
                                    AND cd.is_active = 1) AS 'capacity'")
            )
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_id'),
                AllowedFilter::exact('location_id', 'box_facility_id'),
                AllowedFilter::exact('user_id', 'class_bookings.user_id'),
                AllowedFilter::exact('class_date_id', 'class_to_dates.class_to_date_id'),
                AllowedFilter::exact('class_id'),
                AllowedFilter::exact('is_session', 'is_session'),
                AllowedFilter::callback('class_dates_between', function (Builder $query) use ($start, $end) {
                    $query->whereBetween('class_to_dates.class_date', [$start, $end]);
                }),
                AllowedFilter::callback('is_class_active', function (Builder $query, $value) {
                    $query->where('classes.is_active', $value)->where('class_to_dates.is_active', $value);
                }),
            ])
            ->join('class_to_dates', 'class_to_dates.class_id', '=', 'classes.class_id')
            ->join('class_bookings', 'class_bookings.class_to_date_id', '=', 'class_to_dates.class_to_date_id')
            ->groupBy('classes.class_id')
            ->orderByDesc('booked')
            ->get()
            ->values();

    }

    public function classAttendanceDetails(ClassBookingStatus $status, Carbon $start, Carbon $end)
    {
        return QueryBuilder::for(ClassBooking::class)
            ->addSelect('class_bookings.*')
            ->allowedFilters([
                AllowedFilter::exact('class_id'),
            ])
            ->status($status)
            ->between($start, $end)
            ->allowedIncludes(
                'class',
                'classDate',
                'user',
                'leadMember'
            )
            ->get();
    }

    public function memberAttendance()
    {
        $tenantId = request()->input('filter.tenant_id');
        $locationId = request()->input('filter.location_id');
        $classId = request()->input('filter.class_id');
        $isSession = request()->input('filter.is_session');
        $isSession = is_null($isSession) ? null : filter_var($isSession, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        [$start, $end] = explode(',', request()->input('filter.between'));

        return QueryBuilder::for(TenantUser::class)
            ->select('user_to_box.*')
            ->join('users', 'user_to_box.user_id', '=', 'users.user_id')
            ->withCount([
                'wodCaptures' => function ($query) use ($tenantId, $start, $end) {

                    $query->join('wods', 'wods.wod_id', '=', 'wod_capture.wod_id')
                        ->when($tenantId, function ($query) use ($tenantId) {
                            $query->where('wods.box_id', $tenantId);
                        })
                        ->where(function ($query) use ($start, $end) {
                            $query->where('wods.wod_date', '>=', $start)
                                ->where('wods.wod_date', '<', $end);
                        });
                },
            ])
            ->active()
            ->memberships()
            ->whereHas('user')
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_id'),
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('location_id', 'locations.box_facility_id'),
            ])
            ->with(['bookings' => function ($query) use ($start, $end, $tenantId, $locationId, $classId, $isSession) {
                $query->whereBetween('class_to_dates.class_date', [$start, $end])
                    ->join('class_to_dates', 'class_bookings.class_to_date_id', '=', 'class_to_dates.class_to_date_id')
                    ->join(DB::raw('classes FORCE INDEX FOR JOIN (classes_box_id_box_facility_id_index)'), 'class_bookings.class_id', '=', 'classes.class_id')
                    ->when($tenantId, function ($query) use ($tenantId) {
                        $query->where('classes.box_id', $tenantId);
                    })
                    ->when($locationId, function ($query) use ($locationId) {
                        $query->where('classes.box_facility_id', $locationId);
                    })
                    ->when($classId, function ($query) use ($classId) {
                        $query->where('class_bookings.class_id', $classId);
                    })
                    ->when(is_bool($isSession), function ($query) use ($isSession) {
                        $query->where('classes.is_session', $isSession);
                    });
            }])
            ->with('user')
            ->with('locations', function ($query) use ($tenantId) {
                $query->where('box_id', $tenantId);
            })
            ->orderBy('users.name')
            ->_paginate()
            ->tap()
            ->transform(function ($userTenant) {
                $userTenant->user->setRelation('userTenant', $userTenant->withoutRelations());

                return [
                    'user' => new UserResource($userTenant->user),
                    'attendance' => [

                        'booked' => $userTenant->bookings
                            ->where('status', ClassBookingStatus::BOOKED)
                            ->count(),

                        'cancelled' => $cancelled = $userTenant->bookings
                            ->where('status', ClassBookingStatus::CANCELLED)
                            ->count(),

                        'cancelled_after_threshold' => $cancelledAfterThreshold = $userTenant->bookings
                            ->where('status', ClassBookingStatus::CANCELLED_AFTER_THRESHOLD)
                            ->count(),

                        'cancelled_by_coach' => $cancelledByCoach = $userTenant->bookings
                            ->where('status', ClassBookingStatus::CANCELLED_BY_COACH)
                            ->count(),

                        'all_cancellations' => $cancelled + $cancelledAfterThreshold + $cancelledByCoach,

                        'no_show' => $userTenant->bookings
                            ->where('status', ClassBookingStatus::NO_SHOW)
                            ->count(),

                        'checked_in' => $userTenant->bookings
                            ->where('status', ClassBookingStatus::BOOKED)
                            ->where('is_checked_in', 1)
                            ->count(),
                    ],
                    'workouts_logged' => $userTenant->wod_captures_count,
                ];
            });
    }

    public function memberAttendanceDetails(ClassBookingStatus $classBookingStatus, Carbon $start, Carbon $end)
    {
        return QueryBuilder::for(ClassBooking::class)
            ->select('class_bookings.*')
            ->allowedFilters([
                AllowedFilter::exact('class_id'),
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('location_id', 'class.box_facility_id'),
                AllowedFilter::exact('tenant_id', 'class.box_id'),
            ])
            ->status($classBookingStatus)
            ->between($start, $end)
            ->orderBy('class_to_dates.class_date')
            ->allowedIncludes(
                'class',
                'class.location',
                'class.headCoach.user',
                'classDate.headCoach'
            )
            ->get();
    }

    public function getNonAttendanceData(Request $request): LengthAwarePaginator
    {
        $boxFacility = Location::query()->find($request->input('filter.location_id'));

        if (! $boxFacility instanceof Location) {
            abort(404, 'Location could not be found');
        }

        // Get filters
        $now = now();
        [$start, $end] = explode(',', Arr::get($request->filter, 'between'));
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);

        // If start date is in the future then set it to today
        if ($startDate > $now) {
            $startDate = $now;
        }

        $lastClassAttendedDateSql = '(
            SELECT class_to_dates.class_date
            FROM class_bookings
            LEFT JOIN class_to_dates class_to_dates ON class_to_dates.class_to_date_id = class_bookings.class_to_date_id
            LEFT JOIN classes ON classes.class_id = class_to_dates.class_id
            LEFT JOIN boxes ON boxes.box_id = classes.box_id
            WHERE class_bookings.user_id = user_to_box.user_id
            AND classes.box_id = %s
            AND classes.box_facility_id = %s
            AND class_bookings.class_booking_status_id = 1
            ORDER BY class_to_dates.class_date DESC
            LIMIT 1
        ) as last_attended_on';

        $lastClassAttendedDateSql = sprintf(
            $lastClassAttendedDateSql,
            $boxFacility->box_id,
            $boxFacility->getKey()
        );

        // Get all Members for facility
        $userBoxMemberships = (new TenantUserService())
            ->getUserBoxMembershipsByTypesQueryBuilder(
                [UserType::GYM_MEMBER->value],
                $boxFacility->tenant,
                $boxFacility,
                UserStatus::ACTIVE
            )
            ->whereDoesntHave('bookings', function ($query) use ($startDate, $endDate, $boxFacility) {
                $query->join('class_to_dates', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id')
                    ->join('classes', 'classes.class_id', '=', 'class_to_dates.class_id')
                    ->whereBetween('class_to_dates.class_date', [$startDate, $endDate])
                    ->where('classes.box_id', '=', $boxFacility->box_id)
                    ->where('class_bookings.class_booking_status_id', '=', ClassBookingStatus::BOOKED->value);
            })
            ->addSelect(DB::raw($lastClassAttendedDateSql))
            ->with('user')
            ->_paginate();

        return $userBoxMemberships;
    }

    public function getNonAttendanceMembers(Location $boxFacility, $startDate, $endDate): array
    {
        $memberAttendance = [];
        $userBoxMemberships = (new TenantUserService())->getUserBoxMembershipsByTypesQueryBuilder([UserType::GYM_MEMBER->value], $boxFacility->tenant, $boxFacility)
            ->with('user')
            ->get();

        foreach ($userBoxMemberships as $userBoxMembership) {
            if (! $userBoxMembership->user) {
                continue;
            }

            if ($userBoxMembership->user_status_id !== UserStatus::ACTIVE) {
                continue;
            }

            $memberBookings = (new ClassBookingsService())->getMemberBookingsForDatesForBox($userBoxMembership->user, $userBoxMembership->tenant, $startDate, $endDate, [ClassBookingStatus::BOOKED->value, ClassBookingStatus::CHECKED_IN->value]);
            $isOnHold = (new UserOnHoldService())->isTenantUserOnHold($userBoxMembership);

            if ((count($memberBookings) == 0 || $memberBookings == null) && ! $isOnHold) {
                $memberAttendance[] = $userBoxMembership;
            }
        }

        return $memberAttendance;
    }

    public function firstBookings(Request $request): array
    {
        $tenantId = Arr::get($request->filter, 'tenant_id');
        $locationId = Arr::get($request->filter, 'location_id');
        [$start, $end] = explode(',', Arr::get($request->filter, 'between'));

        $tenant = Tenant::findOrFail($tenantId);
        $location = $locationId ? Location::findOrFail($locationId) : null;

        try {
            $firstClassBookings = [];
            $firstClassBookingsResults = [];
            $startDate = Carbon::parse($start);
            $endDate = Carbon::parse($end);

            $bookings = (new ClassBookingsService)->getClassBookingsForActiveUsers($tenant, $startDate, $endDate, $location);

            $bookings->loadMissing('user.tenantUser', 'classDate.class.tenant.timezone');

            /** @var ClassBooking $classBooking */
            foreach ($bookings as $classBooking) {
                if (! isset($firstClassBookings[$classBooking->user_id])) {
                    $firstClassBookings[$classBooking->user_id] = $classBooking;

                    continue;
                }

                // Check if the class date is before current date
                if ($classBooking->classDate->date->lessThan($firstClassBookings[$classBooking->user_id]->classDate->date)) {
                    $firstClassBookings[$classBooking->user_id] = $classBooking;
                }
            }

            $tenantUserService = (new TenantUserService());

            /** @var ClassBooking $firstClassBooking */
            foreach ($firstClassBookings as $firstClassBooking) {
                $user = $firstClassBooking->user;

                if (! $user) {
                    continue;
                }

                $userTenant = $tenantUserService->getCurrentUserTenantForTenant($user, $tenant);

                if (! $userTenant) {
                    continue;
                }

                $userTenant->setRelation('user', $user->withoutRelations());
                $classDate = $firstClassBooking->classDate;

                $firstClassBookingsResults[] = [
                    'user_tenant' => new UserTenantResource($userTenant),
                    'class_date' => [
                        'id' => $classDate->getKey(),
                        'name' => (new ClassService())->getClassDateName($classDate),
                        'description' => (new ClassService())->getClassDateDescription($classDate),
                        'date' => $classDate->date->format('Y-m-d'),
                        'startTime' => (new ClassService())->getClassDateStartDateTime($classDate)->format('H:i:s'),
                        'endTime' => (new ClassService())->getClassDateEndDateTime($classDate)->format('H:i:s'),
                    ],
                ];
            }

            usort($firstClassBookingsResults, fn ($a, $b) => $b['class_date']['date'] <=> $a['class_date']['date']);

            return $firstClassBookingsResults;
        } catch (Exception $e) {
            return [];
        }
    }

    public function classAttendanceOver24Hours(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, request()->toArray(), function () use ($request) {
            $data = [];

            $tenantId = Arr::get($request->filter, 'tenant_id');
            $locationId = Arr::get($request->filter, 'location_id');
            [$start, $end] = explode(',', request()->input('filter.between'));

            ClassDate::query()
                ->join('classes', 'class_to_dates.class_id', '=', 'classes.class_id')
                ->join('class_bookings', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id')
                ->where('class_booking_status_id', '=', ClassBookingStatus::BOOKED->value)
                ->addSelect(DB::raw('
                        HOUR(
                            CASE
                                WHEN class_to_dates.start_time IS NOT NULL THEN class_to_dates.start_time
                                ELSE classes.start_time
                            END
                        ) AS startTimeHour,
                        COUNT(class_bookings.class_booking_id) AS count
                    '))
                ->when($tenantId, function (Builder $query, string $tenantId) {
                    $query->where('classes.box_id', $tenantId);
                })
                ->when($locationId, function (Builder $query, string $locationId) {
                    $query->where('classes.box_facility_id', $locationId);
                })
                //->whereRaw('class_booking_status_id = ?', [1])
                ->between($start, $end)
                ->groupBy('startTimeHour')
                ->get()
                ->each(function ($booking) use (&$data) {
                    $key = now()->setHour($booking->startTimeHour)->startOfHour()->format('H:i');
                    $data[$key] = (int) $booking->count;
                });

            $period = CarbonPeriod::create(now()->startOfDay(), '1 hour', now()->endOfDay());

            $times = [];

            foreach ($period as $date) {
                $times[] = $date->format('H:i');
            }

            return array_replace(array_fill_keys($times, 0), $data);
        });
    }

    private function sortResultsByMonth(Collection $results): array
    {
        $breakdown = array_fill_keys(range(1, 12), null);

        $results->each(function ($result) use (&$breakdown) {
            $breakdown[(int) $result->month] = (float) $result->total;
        });

        return $breakdown;
    }

    /////////////////////////////////////////////////
    /// Finance report helper functions
    /////////////////////////////////////////////////

    public function getDebtorsDetailForPeriod(Request $request): LengthAwarePaginator|array
    {
        $locationId = $request->input('filter.location_id');
        $tenantId = Tenant::findOrFail($request->input('filter.tenant_id'))->getKey();
        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');

        $boxFacilityAndWhere = '';

        if ($locationId) {
            $boxFacilityAndWhere = "AND bf.box_facility_id = $locationId";
        }

        $dateSql = "AND i.due_on BETWEEN '$startDate' AND '$endDate'";

        $sql =
            "
            SELECT user_to_box.user_to_box_id, user_to_box.user_type_id, u.user_id as id, u.name, u.surname, u.email, u.mobile, i.invoice_id, SUM(i.amount) AS amountInvoiced, SUM(COALESCE(p.amount, 0)) AS amountPaid, SUM(i.amount - COALESCE(p.amount, 0)) AS amountOutstanding, DATEDIFF(NOW(), i.due_on) AS days, u.notes, uds.user_debit_status_descr as memberPaymentType
            FROM `finance_invoices` i
            LEFT JOIN (SELECT SUM(amount) AS amount, deleted, invoice_id, user_to_facility_id FROM `finance_payments` WHERE deleted = 0 GROUP BY (invoice_id)) p ON p.invoice_id = i.invoice_id
            INNER JOIN user_to_facility utf ON utf.user_to_facility_id = i.user_to_facility_id
            INNER JOIN box_facility bf ON bf.box_facility_id = utf.box_facility_id
            INNER JOIN users u ON u.user_id = utf.user_id
            INNER JOIN user_to_box ON user_to_box.user_id = u.user_id AND user_to_box.box_id = bf.box_id
            INNER JOIN user_debit_status uds ON user_to_box.user_debit_status_id = uds.user_debit_status_id
            LEFT JOIN user_to_batch utb ON utb.invoice_id = i.invoice_id AND utb.is_active = 1
            WHERE COALESCE(i.deleted,0) = 0 AND COALESCE(p.deleted, 0) = 0
            AND bf.box_id = $tenantId
            AND i.type = 'invoice'
            AND (i.status = 'unpaid' OR i.status = 'paid' OR i.status = 'submitted' OR (i.status = 'pending' AND u.user_status_id != 4))
            AND i.due_on <= CURDATE()
            $dateSql
            AND u.user_id NOT IN (SELECT user_id FROM users_on_hold WHERE deleted_at IS NULL)
            $boxFacilityAndWhere
            GROUP BY i.invoice_id
            HAVING amountOutstanding > 0
            ORDER BY `name` ASC
            ";

        $query = DB::select($sql);

        return collect($query)->transform(function ($item) {
            return [
                'user' => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'surname' => $item->surname,
                    'email' => $item->email,
                    'mobile' => $item->mobile,
                    'user_tenant' => [
                        'id' => $item->user_to_box_id,
                        'typeId' => $item->user_type_id,
                    ],
                ],
                'invoice_id' => $item->invoice_id,
                'amount_invoiced' => $item->amountInvoiced,
                'amount_paid' => $item->amountPaid,
                'amount_outstanding' => $item->amountOutstanding,
                'days' => $item->days,
                'notes' => $item->notes,
                'member_payment_type' => $item->memberPaymentType,
            ];
        })->paginate();
    }

    public function getPackagesSalesAndRevenueMetrics(Request $request): array
    {
        $data = [];
        $tenantId = $request->input('filter.tenant_id');
        $tenant = Tenant::find($tenantId);
        $locationId = $request->input('filter.location_id');
        $location = Location::find($locationId);
        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');
        $tagId = $request->input('filter.tag_id');
        $paymentTypeFilter = $request->input('filter.payment_type');

        $params = [
            'boxId' => $tenantId,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        $locationAndWhere = '';
        $tagIdAndWhere = '';
        $paymentTypeHaving = '';

        if ($locationId) {
            $locationAndWhere = 'AND bf.box_facility_id = :boxFacilityId';
            $params['boxFacilityId'] = $locationId;
        }

        if ($tagId) {
            $tagIdAndWhere = 'AND tags.id = :tagId';
            $params['tagId'] = $tagId;
        }

        if ($paymentTypeFilter) {
            $paymentTypeHaving = 'HAVING paymentType = :paymentType';
            $params['paymentType'] = $paymentTypeFilter === InvoicePaymentType::DEBIT_ORDER->value ? 'debitOrder' : $paymentTypeFilter;
        }

        // Get active packages
        $activePackages = Package::query()
            ->where('box_id', $tenantId)
            ->where('is_active', true)
            ->get();

        if ($activePackages->isEmpty()) {
            return [];
        }

        $activatePackageIds = $activePackages->pluck('package_id')->join(',');
        $morphable = (new UserInvoicePayment())->getTable();

        $sql = "
            SELECT DISTINCT i.invoice_id AS invoiceId,
                            i.amount     AS invoiceAmount,
                            p.amount     AS paymentAmount,
                            tags.`name`  AS tagName,
                            CASE
                                WHEN ((p.type = 'go_cardless' AND utb.`user_to_batch_id` IS NULL) OR p.`type` IN ('payNow', 'paystack', 'stripe')) THEN 'adhoc'
                                WHEN ((p.type = 'go_cardless' AND utb.`user_to_batch_id` IS NOT NULL) OR p.`type` = 'debit_order') THEN 'debitOrder'
                                ELSE p.type
                                END      AS paymentType,
                            CASE
                                WHEN i.discriminator = 'buyPackageInvoice' THEN 'inAppPurchase'
                                WHEN i.discriminator = 'signUpInvoice' THEN 'signUpWidget'
                                WHEN i.`created_by_id` IS NULL THEN 'recurring'
                                WHEN i.`created_by_id` IS NOT NULL THEN 'manuallyAdded'
                                ELSE i.discriminator
                                END      AS purchaseMethod,
                            IF(user_package_invoice.package_id IS NOT NULL, user_package_invoice.package_id, user_package_invoice_item.package_id ) AS 'packageId'
            FROM finance_invoices i
                     INNER JOIN finance_payments p ON i.`invoice_id` = p.`invoice_id`
                     INNER JOIN finance_invoice_items it ON i.invoice_id = it.invoice_id
                     LEFT OUTER JOIN user_to_package user_package_invoice ON i.user_to_package_id = user_package_invoice.user_to_package_id
                     LEFT OUTER JOIN user_to_package user_package_invoice_item ON it.user_to_package_id = user_package_invoice_item.user_to_package_id
                     INNER JOIN user_to_facility utf ON i.user_to_facility_id = utf.user_to_facility_id
                     INNER JOIN box_facility bf ON utf.box_facility_id = bf.box_facility_id
                     LEFT JOIN `user_to_batch` utb ON i.invoice_id = utb.invoice_id
                     LEFT JOIN `taggables` ON p.`payment_id` = taggables.`morphable_id` AND taggables.`morphable_model` = '$morphable'
                     LEFT JOIN `tags` ON taggables.`tag_id` = tags.`id` AND tags.`type` = 'payment_processor'
            WHERE  (user_package_invoice.package_id IN ($activatePackageIds) OR user_package_invoice_item.package_id IN ($activatePackageIds))
              AND bf.box_id = :boxId
              AND (i.due_on BETWEEN :startDate AND :endDate)
              AND it.discriminator IN ('membership', 'discount', 'prorate')
              AND i.discriminator != 'topupInvoice'
              AND i.deleted = 0
              AND it.deleted = 0
              AND p.deleted = 0
              $locationAndWhere
              $tagIdAndWhere
              $paymentTypeHaving
        ";

        $invoices = DB::select($sql, $params);

        $baseTotals = [
            'count' => 0,
            'total' => 0,
        ];

        $purchaseMethodsData = [
            'inAppPurchase' => $baseTotals,
            'signUpWidget' => $baseTotals,
            'recurring' => $baseTotals,
            'manuallyAdded' => $baseTotals,
        ];
        $packagePaymentTypeData = null;
        $packagePaymentTagData = null;

        $paymentTypes = $paymentTypeFilter ? [$paymentTypeFilter] : InvoicePaymentType::values();
        $tags = (new TagsService())->getTags($tenantId, TagType::PAYMENT->value, $tenantId, 'boxes', $tagId ?? null);

        foreach ($paymentTypes as $paymentType) {
            $paymentType = $paymentType === InvoicePaymentType::DEBIT_ORDER->value ? 'debitOrder' : $paymentType;
            $packagePaymentTypeData[$paymentType] = $baseTotals;
        }

        foreach ($tags as $tag) {
            $packagePaymentTagData[$tag->name] = $baseTotals;
        }

        $packagesData = [];

        foreach ($invoices as $invoice) {
            if (! isset($packagesData[$invoice->packageId])) {
                $package = $activePackages->where('package_id', $invoice->packageId)->first();

                if (! $package) {
                    continue;
                }

                $packagesData[$invoice->packageId] = [
                    'package' => [
                        'id' => $package->getKey(),
                        'name' => $package->name,
                        'description' => $package->description,
                        'price' => $package->price,
                    ],
                    'totalPackageRevenue' => 0,
                    'count' => 0,
                    'purchaseMethods' => $purchaseMethodsData,
                    'paymentTypes' => $packagePaymentTypeData,
                    'paymentTags' => $packagePaymentTagData,
                    'classAttended' => (new ClassBookingsService())->getClassBookingsForPackage($tenant, $package, Carbon::parse($startDate), Carbon::parse($endDate), $location, true),
                ];
            }

            $packagesData[$invoice->packageId]['totalPackageRevenue'] += (float) $invoice->invoiceAmount;
            $packagesData[$invoice->packageId]['count']++;

            $packagesData[$invoice->packageId]['purchaseMethods'][$invoice->purchaseMethod] = [
                'count' => ++$packagesData[$invoice->packageId]['purchaseMethods'][$invoice->purchaseMethod]['count'],
                'total' => $packagesData[$invoice->packageId]['purchaseMethods'][$invoice->purchaseMethod]['total'] += (float) $invoice->paymentAmount,
            ];

            $packagesData[$invoice->packageId]['paymentTypes'][$invoice->paymentType] = [
                'count' => ++$packagesData[$invoice->packageId]['paymentTypes'][$invoice->paymentType]['count'],
                'total' => $packagesData[$invoice->packageId]['paymentTypes'][$invoice->paymentType]['total'] += (float) $invoice->paymentAmount,
            ];

            if ($invoice->tagName !== null) {
                $packagesData[$invoice->packageId]['paymentTags'][$invoice->tagName] = [
                    'count' => ++$packagesData[$invoice->packageId]['paymentTags'][$invoice->tagName]['count'],
                    'total' => $packagesData[$invoice->packageId]['paymentTags'][$invoice->tagName]['total'] += (float) $invoice->paymentAmount,
                ];
            }
        }

        usort($data, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        return array_values($packagesData);
    }

    public function getDiscountsData(Request $request): array
    {
        $tenantId = $request->input('filter.tenant_id');
        $locationId = $request->input('filter.location_id');
        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');

        if ($locationId && $locationId !== '') {
            $invoiceItems = (new InvoiceService())->getMembershipInvoiceItemsByFacility($locationId, ['discount'], $startDate, $endDate);
        } else {
            $invoiceItems = (new InvoiceService())->getMembershipInvoiceItemsByBox($tenantId, ['discount'], $startDate, $endDate);
        }

        $data = [];
        $users = [];

        /** @var UserInvoiceItem $item */
        foreach ($invoiceItems as $item) {
            $invoice = $item->invoice;
            $userLocation = $invoice->userLocation;
            $user = $userLocation?->user;

            if (! $user instanceof User) {
                continue;
            }

            $userDiscount = (new LocationDiscountService())->getFacilityMembershipDiscountForUserByDate(
                $userLocation->getKey(),
                $invoice->created_on->toDateString()
            );

            if (! $userDiscount instanceof LocationUserDiscount) {
                continue;
            }

            $discount = $userDiscount->discount;

            // Add discount data if it does not exist yet
            if (! array_key_exists($discount->getKey(), $data)) {
                $data[$discount->getKey()] = [
                    'discount' => [
                        'id' => $discount->getKey(),
                        'name' => $discount->name,
                        'description' => $discount->description,
                        'amount' => $discount->amount,
                        'type' => $discount->type,
                    ],
                    'numberOfAthletes' => 0,
                    'totalDiscountedAmount' => 0,
                ];
            }

            // Get total discounted amount
            $data[$discount->getKey()]['totalDiscountedAmount'] = $data[$discount->getKey()]['totalDiscountedAmount'] + abs($item->amount);

            // Get total users
            if (! isset($users[$discount->getKey()]['athlete_'.$user->getKey()])) {
                $data[$discount->getKey()]['numberOfAthletes'] = ++$data[$discount->getKey()]['numberOfAthletes'];
                $users[$discount->getKey()]['athlete_'.$user->getKey()] = $user->getKey();
            }
        }

        arsort($data);

        return array_values($data);
    }

    public function getPaymentsData(Request $request): array
    {
        $tenantId = $request->input('filter.tenant_id');
        $locationId = $request->input('filter.location_id');
        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');
        $paymentType = $request->input('filter.payment_type');

        $users = [];
        $unPaginatedData = [];

        // Get all payments for this box or boxFacility
        $payments = (new InvoiceService())->getPaymentsForBox($tenantId, $locationId, $paymentType, $startDate, $endDate);

        foreach (InvoicePaymentType::values() as $type) {
            $numberOfAthletes = 0;
            $totalPaymentsAmount = 0;

            /** @var UserInvoicePayment $payment */
            foreach ($payments as $payment) {
                if ($payment->type->value !== $type) {
                    continue;
                }

                $user = $payment->invoice->userLocation?->user;
                if (! $user instanceof User) {
                    continue;
                }

                $totalPaymentsAmount = $totalPaymentsAmount + $payment->amount;

                $unPaginatedData[$type]['name'] = $payment->type->value;
                $unPaginatedData[$type]['totalPaymentsAmount'] = $totalPaymentsAmount;

                if (! isset($users[$type]['athlete_'.$user->getKey()])) {
                    $unPaginatedData[$type]['numberOfAthletes'] = ++$numberOfAthletes;
                    $users[$type]['athlete_'.$user->getKey()] = $user->getKey();
                }
            }
        }

        arsort($unPaginatedData);

        return array_values($unPaginatedData);
    }

    /////////////////////////////////////////////////
    /// Report helper functions
    /////////////////////////////////////////////////

    public function getCoachesSessions(Request $request): array
    {
        $coaches = [];
        $coachAggregatedData = [];

        // Get parameters
        $tenantId = $request->input('filter.tenant_id');
        $locationId = $request->input('filter.location_id');
        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');
        $coach = $request->input('filter.coach_id') ? User::query()->find($request->input('filter.coach_id')) : null;

        $authUser = (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $tenantId);

        if ($coach instanceof User) {
            $coaches[] = $coach;
        } elseif ($authUser instanceof TenantUser && ($authUser->isHeadCoach() || $authUser->isTenantAdmin())) {
            $coaches = User::query()
                ->select('users.*')
                ->join('user_to_box', 'users.user_id', '=', 'user_to_box.user_id')
                ->where('user_to_box.box_id', '=', $authUser->box_id)
                ->whereIn('user_to_box.user_type_id', UserType::staffUserTypeIds())
                ->where('user_to_box.user_status_id', '!=', UserStatus::DEACTIVATED->value)
                ->where('user_to_box.end_date', '>', today()->format('Y-m-d'))
                ->get();

        } else {
            $coaches[] = $request->user();
        }

        /** @var User $coach */
        foreach ($coaches as $coach) {
            // Get class and session for filters
            $classDates = $this->getCoachReportClassDates($tenantId, $startDate, $endDate, $locationId, $coach);
            $sessionDates = $this->getCoachReportClassDates($tenantId, $startDate, $endDate, $locationId, $coach, true);

            // Get aggregated data for coach
            $coachAggregatedData[] = [
                'user' => new UserMinimalResource($coach),
                'classes' => count($classDates),
                'amountEarnedForClasses' => $this->getAmountedEarnedForClasses($classDates, $coach->getKey(), true),
                'sessions' => count($sessionDates),
                'amountEarnedForSessions' => $this->getAmountedEarnedForPersonalSessions($sessionDates, $coach->getKey(), true),
            ];
        }

        return collect($coachAggregatedData)->paginate();
    }

    public function getCoachesClassesOrSessionsDetails(Request $request)
    {
        $coach = User::query()->find($request->input('filter.user_id'));
        $tenantId = $request->input('filter.tenant_id');
        $locationId = $request->input('filter.location_id');
        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');
        $isSession = filter_var(request()->input('filter.is_session'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if (! $coach instanceof User) {
            abort(400, 'User could not be found.');
        }

        $classDates = $this->getCoachReportClassDates($tenantId, $startDate, $endDate, $locationId, $coach, $isSession);

        if ($isSession) {
            $classesWithFees = $this->getAmountedEarnedForPersonalSessions($classDates, $coach->getKey());
        } else {
            $classesWithFees = $this->getAmountedEarnedForClasses($classDates, $coach->getKey());
        }

        return JsonResource::collection(collect($classesWithFees)->paginate());
    }

    public function getPerformanceData(Request $request): array
    {
        $exercise = Exercise::query()->find($request->input('filter.exercise_id'));
        $user = $request->input('filter.user_id') ? User::query()->find($request->input('filter.user_id')) : null;
        $gender = $request->input('filter.gender_id');

        if (! $exercise instanceof Exercise) {
            abort(400, 'Exercise could not be found.');
        }

        $dataToPaginate = [
            'exercise' => [
                'id' => $exercise->getKey(),
                'name' => $exercise->name,
                'description' => $exercise->description,
                'measurement' => $exercise->measureUnit->name,
            ],
            'users' => [],
        ];

        if ($user instanceof User) {
            // Check if the user belongs to this box
            $authUser = (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $request->input('filter.tenant_id'));
            $currentUser = (new TenantUserService())->getCurrentUserTenantForTenant($user, $request->input('filter.tenant_id'));

            if ($authUser?->box_id !== $currentUser?->box_id) {
                abort(400, 'User does not belong to this box.');
            }

            $dataToPaginate['users'][] = $this->getUserBenchmarkWorkoutResults($exercise, $user);
        } else {
            // Get a list of all members for this box
            $boxMembersQueryBuilder = TenantUser::query()
                ->where('box_id', $request->input('filter.tenant_id'))
                ->when($gender, function ($query) use ($gender) {
                    $query->whereRelation('user', 'gender_id', '=', $gender);
                });

            /** @var User $member */
            foreach ($boxMembersQueryBuilder->get() as $member) {
                $data = $this->getUserBenchmarkWorkoutResults($exercise, $member->user);

                if ($data) {
                    $dataToPaginate['users'][] = $data;
                }
            }
        }

        return collect($dataToPaginate)->paginate();
    }

    public function getPerformanceDetailsData(Request $request): array
    {
        $exercise = Exercise::query()->find($request->input('filter.exercise_id'));
        $user = User::query()->find($request->input('filter.user_id'));

        if (! $exercise instanceof Exercise) {
            abort(400, 'Exercise could not be found.');
        }

        if (! $user instanceof User) {
            abort(400, 'User could not be found.');
        }

        $authUser = (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $request->input('filter.tenant_id'));
        $filteredUser = (new TenantUserService())->getCurrentUserTenantForTenant($user, $request->input('filter.tenant_id'));

        // Check if the user belongs to this box
        if ($authUser?->box_id !== $filteredUser?->box_id) {
            abort(400, 'User does not belong to this box.');
        }

        $userWodCapturesForExercise = (new WODExerciseService())->getWodCaptureExercisesForExercise($exercise, $user);

        $data = [
            'user' => new UserMinimalResource($user),
            'exercise' => [
                'id' => $exercise->getKey(),
                'name' => $exercise->name,
                'description' => $exercise->description,
                'measurement' => $exercise->measureUnit->name,
            ],
            'wods' => [],
        ];

        /** @var WodCaptureExercise $wodCaptureExercise */
        foreach ($userWodCapturesForExercise as $wodCaptureExercise) {
            $data['wods'][] = [
                'wodDate' => $wodCaptureExercise->capture->wod->wod_date,
                'score' => $wodCaptureExercise->score,
            ];
        }

        return $data;
    }

    public function getAllVitalityLocations(): array
    {
        $sql = 'SELECT bf.box_facility_id as id, bf.box_facility_name as name
                    FROM `box_facilities_to_health_providers` bthp
                    INNER JOIN `box_facility` bf ON bthp.`box_facility_id` = bf.box_facility_id
                    INNER JOIN boxes b ON bf.box_id = b.box_id
                    WHERE bthp.health_provider_id = 1 -- Vitality health provider
                    AND bf.`is_active` = 1
                    AND b.`box_status_id` = 1';

        return DB::select($sql);
    }

    public function getTotalLinkedMembersForLocation(int $boxFacilityId)
    {
        $sql = 'SELECT COUNT(DISTINCT u.user_id) as count
                    FROM users u
                    INNER JOIN user_to_box utb ON utb.user_id = u.user_id
                    INNER JOIN user_to_facility utf ON utf.user_id = u.user_id
                    WHERE u.`health_provider_id` = 1
                    AND u.`user_status_id` = 2 -- Active users
                    AND u.`deleted` = 0 -- User not deleted
                    AND utb.end_date >= NOW() -- Have an active box membership
                    AND utf.end_date >= NOW() -- Have an active boxFacility membership
                    AND utf.box_facility_id = :boxFacilityId';

        return DB::selectOne($sql, ['boxFacilityId' => $boxFacilityId]);
    }

    public function getTotalCheckInsForLocation(int $boxFacilityId)
    {
        $sql = 'SELECT COUNT(*)
                    FROM attendance_records ar
                    WHERE ar.health_provider_id = 1
                    AND ar.box_facility_id = :boxFacilityId';

        return DB::selectOne($sql, ['boxFacilityId' => $boxFacilityId]);
    }

    public function getUserMetrics(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $tenant = $request->input('filter.tenant_id') ? Tenant::query()->find($request->input('filter.tenant_id')) : null;
            $region = $request->input('filter.region_id') ? Region::query()->find($request->input('filter.region_id')) : null;

            return [
                'staffCount' => $staffCount = (new UserService())->getUsersCount([UserStatus::ACTIVE->value], $tenant, null, UserType::staffUserTypeIds(), $region),
                'membersCount' => $membersCount = (new UserService())->getUsersCount([UserStatus::ACTIVE->value], $tenant, null, [UserType::GYM_MEMBER], $region, true),
                'usersCount' => $staffCount + $membersCount,
            ];
        }, self::LONG_CACHE_EXPIRY_IN_SECONDS);
    }

    public function getTenantAndLocationMetrics(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $year = $request->input('filter.year');
            $tenant = $request->input('filter.tenant_id') ? Tenant::query()->find($request->input('filter.tenant_id')) : null;
            $region = $request->input('filter.region_id') ? Region::query()->find($request->input('filter.region_id')) : null;
            $locationCategory = $request->input('filter.location_category_id') ? LocationCategory::query()->find($request->input('filter.location_category_id')) : null;
            $isMonthlyBreakdown = $request->input('filter.is_monthly_breakdown', false);

            return $this->getTenantsAndLocationsCount($year, $tenant, $region, $locationCategory, $isMonthlyBreakdown);
        }, self::LONG_CACHE_EXPIRY_IN_SECONDS);
    }

    public function getMetricsForRegions(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $regionId = $request->input('filter.region_id');
            $regionFilter = $regionId ? Region::query()->find($regionId) : null;

            if ($regionId && ! $regionFilter instanceof Region) {
                abort(400, 'Region could not be found');
            }

            $data = [];
            $regions = $regionFilter instanceof Region ? [$regionFilter] : Region::all();

            foreach ($regions as $region) {
                [
                    'tenantsCount' => $tenantsCount,
                    'locationsCount' => $locationsCount,
                ] = $this->getTenantsAndLocationsCount(date('Y'), null, $region);

                $data[] = [
                    'region' => [
                        'id' => $region->getKey(),
                        'name' => $region->name,
                    ],
                    'tenantsCount' => $tenantsCount ?? 0,
                    'locationsCount' => $locationsCount ?? 0,
                    'staffCount' => $staffCount = (new UserService())->getUsersCount([UserStatus::ACTIVE->value], null, null, UserType::staffUserTypeIds(), $region),
                    'membersCount' => $membersCount = (new UserService())->getUsersCount([UserStatus::ACTIVE->value], null, null, [UserType::GYM_MEMBER->value], $region, true),
                    'usersCount' => $staffCount + $membersCount,
                ];
            }

            usort($data, function ($a, $b) {
                return $a['tenantsCount'] < $b['tenantsCount'];
            });

            return $data;
        }, self::LONG_CACHE_EXPIRY_IN_SECONDS);
    }

    public function getMetricsForLocationCategories(Request $request): array
    {
        return $this->cacheResults(__FUNCTION__, $request->toArray(), function () use ($request) {
            $regionId = $request->input('filter.region_id');
            $regionFilter = $regionId ? Region::query()->find($regionId) : null;

            if ($regionId && ! $regionFilter instanceof Region) {
                abort(400, 'Region could not be found');
            }

            $locationCategoryId = $request->input('filter.location_category_id');
            $locationCategoryFilter = $locationCategoryId ? LocationCategory::query()->find($locationCategoryId) : null;

            if ($locationCategoryId && ! $locationCategoryFilter instanceof LocationCategory) {
                abort(400, 'Location category could not be found');
            }

            $data = [];
            $locationCategories = $locationCategoryFilter instanceof LocationCategory ? [$locationCategoryFilter] : LocationCategory::all();

            foreach ($locationCategories as $locationCategory) {
                [
                    'tenantsCount' => $tenantsCount,
                    'locationsCount' => $locationsCount,
                ] = $this->getTenantsAndLocationsCount(date('Y'), null, $regionFilter, $locationCategory);

                $data[] = [
                    'locationCategory' => [
                        'id' => $locationCategory->getKey(),
                        'name' => $locationCategory->name,
                    ],
                    'tenantsCount' => $tenantsCount ?? 0,
                    'locationsCount' => $locationsCount ?? 0,
                ];
            }

            return $data;
        }, self::LONG_CACHE_EXPIRY_IN_SECONDS);
    }

    private function getAmountedEarnedForClasses(array $classDates, int $coachId, bool $getTotal = false)
    {
        foreach ($classDates as $id => $classDate) {
            $coachesFee = 0;
            $bookingsCountForClass = (int) $classDate['attendeesCheckedInCount'];

            // Get coaches rates
            $coachClassFlatRates = $this->getCoachRatesByStrategyAndType($coachId, ['flat_rate'], ['classes'], $classDate['class']['boxFacility']['id']);
            $coachClassMemberBasedRates = $this->getCoachRatesByStrategyAndType($coachId, ['member_based_rate'], ['classes'], $classDate['class']['boxFacility']['id']);

            // If no attendees or no member based rate is set up
            if (count($coachClassFlatRates) > 0 && (count($coachClassMemberBasedRates) === 0 || $bookingsCountForClass === 0)) {
                // Get the first-rate. Rates are ordered from highest to lowest amount.
                $coachClassRate = $coachClassFlatRates[0];
                $coachesFee = (float) $coachClassRate['amount'];
            } elseif (count($coachClassMemberBasedRates) > 0) {
                $memberBasedRate = 0;

                foreach ($coachClassMemberBasedRates as $coachClassMemberBasedRate) {
                    if (($bookingsCountForClass >= (int) $coachClassMemberBasedRate['min_members']) && ($bookingsCountForClass <= (int) $coachClassMemberBasedRate['max_members'] || $bookingsCountForClass > (int) $coachClassMemberBasedRate['max_members'])) {
                        if ((float) $coachClassMemberBasedRate['amount'] > $memberBasedRate) {
                            $memberBasedRate = (float) $coachClassMemberBasedRate['amount'];
                        }
                    }
                }

                $coachesFee = (float) $memberBasedRate;
            }

            $classDates[$id]['coachesClassFee'] = $coachesFee;
        }

        $result = $classDates;

        // If the total should be returned
        if ($getTotal) {
            $totalEarned = 0;

            foreach ($classDates as $classDate) {
                $totalEarned += $classDate['coachesClassFee'];
            }

            $result = $totalEarned;
        }

        return $result;
    }

    private function getCoachRatesByStrategyAndType(int $coachId, array $strategies, array $rateTypes, ?int $boxFacilityId = null): array
    {
        return CoachRate::query()
            ->where('user_id', '=', $coachId)
            ->whereIn('strategy', $strategies)
            ->whereIn('type', $rateTypes)
            ->where('deleted', '=', false)
            ->when($boxFacilityId, function ($query) use ($boxFacilityId) {
                return $query->where('box_facility_id', '=', $boxFacilityId);
            })
            ->get()
            ->toArray();
    }

    private function getAmountedEarnedForPersonalSessions(array $sessionDates, int $coachId, bool $getTotal = false)
    {
        foreach ($sessionDates as $id => $sessionDate) {
            $coachesFee = 0;
            $attendeesCheckedInForClass = (int) $sessionDate['attendeesCheckedInCount'];

            // Get coaches rates
            $coachPtSessionFlatRates = $this->getCoachRatesByStrategyAndType($coachId, ['flat_rate'], ['pt_sessions'], $sessionDate['class']['boxFacility']['id']);
            $coachPtSessionMemberBasedRates = $this->getCoachRatesByStrategyAndType($coachId, ['member_based_rate'], ['pt_sessions'], $sessionDate['class']['boxFacility']['id']);

            // If no attendees or no member based rate is set up
            if (count($coachPtSessionFlatRates) > 0 && (count($coachPtSessionMemberBasedRates) === 0 || $attendeesCheckedInForClass === 0)) {
                // Get the first-rate. Rates are ordered from highest to lowest amount.
                $coachClassRate = $coachPtSessionFlatRates[0];
                $coachesFee = (float) $coachClassRate['amount'];
            } elseif (count($coachPtSessionMemberBasedRates) > 0) {
                $memberBasedRate = 0;

                foreach ($coachPtSessionMemberBasedRates as $coachClassMemberBasedRate) {
                    if (($attendeesCheckedInForClass >= (int) $coachClassMemberBasedRate['min_members']) && ($attendeesCheckedInForClass <= (int) $coachClassMemberBasedRate['max_members'] || $attendeesCheckedInForClass > (int) $coachClassMemberBasedRate['max_members'])) {
                        if ((float) $coachClassMemberBasedRate['amount'] > $memberBasedRate) {
                            $memberBasedRate = (float) $coachClassMemberBasedRate['amount'];
                        }
                    }
                }

                $coachesFee = (float) $memberBasedRate;
            }

            $sessionDates[$id]['coachesSessionFee'] = $coachesFee;
        }

        $result = $sessionDates;

        // If the total should be returned
        if ($getTotal) {
            $totalEarned = 0;

            foreach ($sessionDates as $class) {
                $totalEarned += $class['coachesSessionFee'];
            }

            $result = $totalEarned;
        }

        return $result;
    }

    private function getCoachReportClassDates(int $tenantId, $startDate, $endDate, ?int $locationId = null, ?User $coach = null, ?bool $isSession = false): array
    {
        $classService = (new ClassService());
        $filterByCoachId = $coach instanceof User ? $coach->getKey() : null;

        $classDates = $this->getClassDatesForCoachReport($tenantId, $startDate, $endDate, $isSession, $locationId, null, $filterByCoachId, true);
        $classDateResults = [];

        foreach ($classDates as $classDateData) {
            /** @var ClassDate $classDate */
            $classDate = ClassDate::query()->findOrFail($classDateData->id);
            $class = $classDate->class;

            // Bug to stop duplicates on the trainer report
            // If filtering by coach make sure that correct classDates are assigned to the correct coaches
            if ($coach instanceof User) {
                // Check if classDate head coach is set and NO supporting coach is set
                if ($classDate->headCoach instanceof User && ! $classDate->supportingCoach instanceof User) {
                    // Make sure that ctd head coach and filterByCoachId is the same
                    if ($classDate->headCoach->getKey() !== $filterByCoachId) {
                        continue;
                    }
                }

                // Check if classDate supporting coach is set and NO head coach is set
                if ($classDate->supportingCoach instanceof User && ! $classDate->headCoach instanceof User) {
                    // Make sure that ctd supporting coach and filterByCoachId is the same
                    if ($classDate->supportingCoach->getKey() !== $filterByCoachId) {
                        continue;
                    }
                }

                // Check if classDate both coaches are set
                if ($classDate->headCoach instanceof User && $classDate->supportingCoach instanceof User) {
                    // Make sure that ctd head coach and filterByCoachId is the same
                    if ($classDate->headCoach->getKey() !== $filterByCoachId && $classDate->supportingCoach->getKey() !== $filterByCoachId) {
                        continue;
                    }
                }
            }

            $classDateResults[] = [
                'id' => $classDate->getKey(),
                'name' => $classService->getClassDateName($classDate),
                'description' => $classDate->description(),
                'date' => $classDate->class_date->format('Y-m-d'),
                'startTime' => $classService->getClassDateStartDateTime($classDate)->format('H:i:s'),
                'endTime' => $classService->getClassDateEndDateTime($classDate)->format('H:i:s'),
                'class' => [
                    'id' => $class->getKey(),
                    'name' => $class->name,
                    'description' => $class->description,
                    'boxFacility' => [
                        'id' => $class->box_facility_id,
                        'name' => $class->location->name,
                    ],
                ],
                'attendanceCount' => (int) $classDateData->attendanceCount,
                'attendeesCheckedInCount' => (int) $classDateData->attendeesCheckedInCount,
            ];
        }

        return $classDateResults;
    }

    private function getClassDatesForCoachReport($boxId, $startDate, $endDate, ?bool $isSession, $boxFacilityId = null, ?string $classId = '', ?int $coachId = null, ?bool $isSelectCheckInCount = false): array
    {
        $boxFacilityWhere = '';
        $isSessionWhere = '';
        $classWhere = '';
        $coachWhere = '';
        $classStatusWhere = 'AND ctd.is_active = 1';
        $selectCheckInCountSql = '';

        if ($boxFacilityId) {
            $boxFacilityWhere = 'AND c.box_facility_id = '.$boxFacilityId;
        }

        if (is_bool($isSession)) {
            $isSessionWhere = 'AND c.is_session = '.(int) $isSession;
        }

        if ($classId) {
            $classWhere = 'AND c.class_id = '.$classId;
        }

        if ($coachId) {
            $coachWhere = 'AND (cc.`coach_id` = '.$coachId.' OR cc2.`coach_id` = '.$coachId.' OR ctd.`coach_id` = '.$coachId.' OR ctd.`supporting_coach_id` = '.$coachId.')';
        }

        if ($isSelectCheckInCount) {
            $selectCheckInCountSql = ', (SELECT COUNT(cb.class_booking_id) FROM class_bookings cb WHERE cb.class_to_date_id = ctd.class_to_date_id AND cb.class_booking_status_id IN (1,5) AND cb.is_checked_in = true) AS `attendeesCheckedInCount`';
        }

        $sql = "
            SELECT DISTINCT
            ctd.class_to_date_id AS id,
            c.box_facility_id AS boxFacilityId,
            IF (uctd.user_id IS NULL, ucc.user_id, uctd.user_id) AS coachId,
            IF (uctd2.user_id IS NULL, ucc2.user_id, uctd2.user_id) AS supportingCoachId,
            (SELECT COUNT(cb.class_booking_id) FROM class_bookings cb WHERE cb.class_to_date_id = ctd.class_to_date_id AND cb.class_booking_status_id IN (1,5)) AS `attendanceCount`
            $selectCheckInCountSql
            FROM class_to_dates ctd
            INNER JOIN classes c ON ctd.class_id = c.class_id
            INNER JOIN class_coaches cc ON cc.class_id = c.class_id AND cc.coach_type_id = 1  AND ((cc.is_active = 1 AND ctd.class_date >= DATE(cc.dt_added)) OR (cc.is_active = 0 AND ctd.class_date >= DATE(cc.dt_added) AND ctd.class_date < DATE(cc.dt_modified)))
            LEFT JOIN class_coaches cc2 ON cc2.class_id = c.class_id AND cc2.coach_type_id = 2  AND ((cc2.is_active = 1 AND ctd.class_date >= DATE(cc2.dt_added)) OR (cc2.is_active = 0 AND ctd.class_date >= DATE(cc2.dt_added) AND ctd.class_date < DATE(cc2.dt_modified)))
            LEFT JOIN users uctd ON ctd.coach_id = uctd.user_id
            LEFT JOIN users uctd2 ON ctd.supporting_coach_id = uctd2.user_id
            INNER JOIN users ucc ON cc.coach_id = ucc.user_id
            LEFT JOIN users ucc2 ON cc2.coach_id = ucc2.user_id
            INNER JOIN box_facility bf ON c.box_facility_id = bf.box_facility_id
            INNER JOIN boxes b ON b.box_id = bf.box_id
            WHERE c.box_id = $boxId
            AND ctd.class_date BETWEEN '$startDate' AND '$endDate'
            and(c.recurring_end_date BETWEEN '$startDate' AND '$endDate' OR c.recurring_end_date IS NULL or c.recurring_end_date > now())
            $boxFacilityWhere
            $isSessionWhere
            $classStatusWhere
            $classWhere
            $coachWhere
            ORDER BY ctd.class_date, IFNULL(ctd.start_time, c.start_time);
        ";

        return DB::select($sql);
    }

    public function getUserBenchmarkWorkoutResults(Exercise $exercise, User $user): array
    {
        $sql =
            'SELECT * FROM (
                SELECT e.exercise_id AS exerciseId, e.exercise_name AS exerciseName, w.wod_date AS wodDate, wce.score, wce.is_rx AS isRx, mu.measuring_unit_desc AS measuringUnit, mu.measuring_unit_short AS measuringUnitShort, wc.dt_added AS benchmarkCaptureDate
                FROM wod_capture_exercises wce
                INNER JOIN exercise e ON e.exercise_id = wce.exercise_id
                INNER JOIN wod_capture wc ON wc.wod_capture_id = wce.wod_capture_id
                INNER JOIN measuring_units mu ON mu.measuring_unit_id = e.measuring_unit_id
                INNER JOIN wods w ON wc.wod_id = w.wod_id
                WHERE wc.user_id = :userId
                AND e.exercise_id = :exerciseId
                AND e.is_benchmark = 1
                AND wce.is_active = 1
                UNION ALL
                SELECT e.exercise_id AS exerciseId, e.exercise_name AS exerciseName, ob.own_benchmark_date AS wodDate, ob.score, ob.is_rx AS isRx, mu.measuring_unit_desc AS measuringUnit, mu.measuring_unit_short AS measuringUnitShort, ob.created_on as benchmarkCaptureDate
                FROM own_benchmarks ob
                INNER JOIN exercise e ON e.exercise_id = ob.exercise_id
                INNER JOIN measuring_units mu ON mu.measuring_unit_id = e.measuring_unit_id
                WHERE ob.user_id = :userId
                AND e.exercise_id = :exerciseId
                AND ob.is_active = 1
            ) a ORDER BY a.score DESC';

        $queryResults = DB::select($sql, [
            'userId' => $user->getKey(),
            'exerciseId' => $exercise->getKey(),
        ]);

        $data = [];

        foreach ($queryResults as $queryResult) {
            $score = $queryResult->score;
            $wodDate = Carbon::parse($queryResult->benchmarkCaptureDate);

            // if already there
            if (empty($data)) {
                // Else create entry into array for this user
                $data = [
                    'user' => new UserMinimalResource($user),
                    'wodDate' => $wodDate,
                    'latestScore' => $score,
                    'personalBestScore' => $score,
                ];
            } else {
                $measuringUnit = $queryResult->measuringUnit;

                // Check if this score is a PB
                if ($measuringUnit === 'For Time - min') {
                    $timedScore = Carbon::parse('00:'.$score);
                    $currentPB = Carbon::parse('00:'.$data['personalBestScore']);

                    if ($timedScore < $currentPB) {
                        $data['personalBestScore'] = $score;
                    }
                } elseif ($measuringUnit === 'For Time - max') {
                    $timedScore = Carbon::parse('00:'.$score);
                    $currentPB = Carbon::parse('00:'.$data['personalBestScore']);

                    if ($timedScore > $currentPB) {
                        $data['personalBestScore'] = $score;
                    }
                } elseif ($score > $data['personalBestScore']) {
                    $data['personalBestScore'] = $score;
                }

                // Check if this latest score
                if ($wodDate > $data['wodDate']) {
                    $data['wodDate'] = $wodDate;
                    $data['latestScore'] = $score;
                }
            }
        }

        return $data;
    }

    private function getTenantsAndLocationsCount(int $year, ?Tenant $tenant = null, ?Region $region = null, ?LocationCategory $locationCategory = null, ?bool $isMonthlyBreakdown = false): array|int
    {
        $boxAndWhere = '';
        $regionAndWhere = '';
        $boxFacilityJoin = '';
        $boxFacilityCategoryAndWhere = '';

        $params = ['year' => $year];

        if ($tenant instanceof Tenant) {
            $boxAndWhere = 'AND b.box_id = :boxId';
            $params['boxId'] = $tenant->getKey();
        }

        if ($region instanceof Region) {
            $regionAndWhere = 'AND b.region_id = :regionId';
            $params['regionId'] = $region->getKey();
        }

        if ($locationCategory instanceof LocationCategory) {
            $boxFacilityJoin = 'LEFT JOIN box_facility bf ON b.box_id = bf.box_id';
            $boxFacilityCategoryAndWhere = 'AND bf.box_facility_category_id = :boxFacilityCategoryId';
            $params['boxFacilityCategoryId'] = $locationCategory->getKey();
        }

        $sql = "
            SELECT DISTINCT b.box_id, b.dt_added, b.deactivated_on
            FROM boxes b
            $boxFacilityJoin
            WHERE YEAR(b.dt_added) <= :year
            AND (b.deactivated_on IS NULL OR YEAR(b.deactivated_on) >= :year)
            $boxAndWhere
            $regionAndWhere
            $boxFacilityCategoryAndWhere
            GROUP BY b.box_id
        ";

        try {
            $boxes = DB::select($sql, $params);

            $whereIn = implode('","', array_column($boxes, 'box_id'));

            $boxFacilitiesSql = '
                SELECT bf.box_facility_id, bf.box_id, bf.dt_added, bf.deactivated_on
                FROM box_facility bf
                WHERE bf.box_id IN ("'.$whereIn.'")
                AND YEAR(bf.dt_added) <= :year
                AND (bf.deactivated_on IS NULL OR YEAR(bf.deactivated_on) >= :year)
                '.$boxFacilityCategoryAndWhere;

            $boxFacilitiesParams = ['year' => $year];

            if ($locationCategory instanceof LocationCategory) {
                $boxFacilitiesParams['boxFacilityCategoryId'] = $locationCategory->getKey();
            }

            $boxFacilities = DB::select($boxFacilitiesSql, $boxFacilitiesParams);

            $numberOfMonthsForYear = $year === (int) date('Y') ? (int) date('m') : 12;

            if ($isMonthlyBreakdown) {
                $boxMonths = array_fill_keys(range(1, $numberOfMonthsForYear), null);
                $boxFacilityMonths = array_fill_keys(range(1, $numberOfMonthsForYear), null);
            } else {
                // If total count just get the count for the last month of that year
                $boxMonths = [$numberOfMonthsForYear => 0];
                $boxFacilityMonths = [$numberOfMonthsForYear => 0];
            }

            foreach ($boxes as $box) {
                $createdOn = \DateTime::createFromFormat('Y-m-d H:i:s', $box->dt_added);
                $createdOnYear = (int) $createdOn->format('Y');
                $createdOnMonth = (int) $createdOn->format('m');
                $deactivatedOn = \DateTime::createFromFormat('Y-m-d H:i:s', $box->deactivated_on);
                $deactivatedOnYear = $deactivatedOn instanceof \DateTime ? (int) $deactivatedOn->format('Y') : null;
                $deactivatedOnMonth = $deactivatedOn instanceof \DateTime ? $deactivatedOn->format('m') : null;

                foreach ($boxMonths as $month => $count) {
                    if (($createdOnYear < $year || ($createdOnYear === $year && $createdOnMonth <= $month)) && (! $deactivatedOnYear || ($deactivatedOnYear > $year || $deactivatedOnMonth >= $month))) {
                        $boxMonths[$month] = $count + 1;

                        $boxFacilitiesForBox = array_filter($boxFacilities, function ($boxFacility) use ($box) {
                            return (int) $boxFacility->box_id === (int) $box->box_id;
                        });

                        foreach ($boxFacilitiesForBox as $boxFacility) {
                            $boxFacilityCreatedOn = \DateTime::createFromFormat('Y-m-d H:i:s', $boxFacility->dt_added);
                            $boxFacilityCreatedOnYear = (int) $boxFacilityCreatedOn->format('Y');
                            $boxFacilityCreatedOnMonth = (int) $boxFacilityCreatedOn->format('m');
                            $boxFacilityDeactivatedOn = \DateTime::createFromFormat('Y-m-d H:i:s', $boxFacility->deactivated_on);
                            $boxFacilityDeactivatedOnYear = $boxFacilityDeactivatedOn instanceof \DateTime ? (int) $boxFacilityDeactivatedOn->format('Y') : null;
                            $boxFacilityDeactivatedOnMonth = $boxFacilityDeactivatedOn instanceof \DateTime ? $boxFacilityDeactivatedOn->format('m') : null;

                            if (($boxFacilityCreatedOnYear < $year || ($boxFacilityCreatedOnYear === $year && $boxFacilityCreatedOnMonth <= $month)) && (! $boxFacilityDeactivatedOnYear || ($boxFacilityDeactivatedOnYear > $year || $boxFacilityDeactivatedOnMonth >= $month))) {
                                $boxFacilityMonths[$month] = $boxFacilityMonths[$month] + 1;
                            }
                        }
                    }
                }
            }

            $boxesPerMonthCount = array_replace(array_fill_keys(range(1, 12), null), $boxMonths);
            $boxFacilitiesPerMonthCount = array_replace(array_fill_keys(range(1, 12), null), $boxFacilityMonths);

            return [
                'tenantsCount' => $isMonthlyBreakdown ? $boxesPerMonthCount : $boxesPerMonthCount[$numberOfMonthsForYear],
                'locationsCount' => $isMonthlyBreakdown ? $boxFacilitiesPerMonthCount : $boxFacilitiesPerMonthCount[$numberOfMonthsForYear],
            ];
        } catch (Exception $e) {
            return $isMonthlyBreakdown ? [] : 0;
        }
    }

    public function export(string|int $boxId, string $reportType, string|int $locationId, ?Carbon $start = null, ?Carbon $end = null): string
    {
        $box = Tenant::findOrFail($boxId);
        $location = Location::findOrFail($locationId);

        return match ($reportType) {
            'members' => $this->uploadExport(
                $box,
                $location,
                $this->getMemberData($box->getKey(), $location->getKey()),
                'member-export'
            ),
            'finances' => $this->uploadExport(
                $box,
                $location,
                $this->getFinanceData($box->getKey(), $location->getKey(), $start, $end),
                'finances-export'
            ),
            'bookings' => $this->uploadExport(
                $box,
                $location,
                $this->getBookingData($box->getKey(), $location->getKey(), $start, $end),
                'bookings-export'
            ),
            'leads' => $this->uploadExport(
                $box,
                $location,
                $this->getLeadData($box->getKey(), $location->getKey(), $start, $end),
                'leads-export'
            ),
            default => throw new RuntimeException('Report type not known.'),
        };
    }

    public function uploadExport(Tenant $box, ?Location $facility, array $data, string $baseFilename): string
    {
        $safeName = preg_replace('/\W+/', '-', strtolower(
            $facility ? $facility->name : $box->name
        ));

        $filename = str($baseFilename)
            ->append(today()->format('Y-m-d'))
            ->append($safeName)
            ->append('.csv')
            ->toString();

        $filePath = 'report-exports/'.$filename;

        (new ReportsExport($data))
            ->store($filePath, 's3', Excel::CSV, ['visibility' => 'private']);

        return $filePath;
    }

    private function getMemberData($boxId, $boxFacilityId = null): array
    {
        $boxFacilityWhere = $boxFacilityId !== null ? "AND `box_facility`.box_facility_id = $boxFacilityId" : '';

        $sql = "
                 SELECT
                `user_to_box`.user_id AS `User ID`,
                TRIM(TRIM(CHAR(9)
                        FROM `user`.name)) AS `First Name`,
                TRIM(TRIM(CHAR(9)
                        FROM `user`.surname)) AS `Last Name`,
                `user`.email AS `Email`,
                TRIM(TRIM(CHAR(9)
                        FROM `user`.mobile)) AS `Mobile`,
                `gender`.gender_desc AS `Gender`,
                `user`.dob AS `DOB`,
                `user_status`.user_status_desc AS `Status`,
                `user_debit_status`.user_debit_status_descr AS `Debit Status`,
                `user_to_box`.auto_invoicing_day AS `User-specific Cash Invoice Day`,
                `user_to_box`.auto_invoicing_due_day AS `User-specific Cash Invoice Due Day`,
                REPLACE(FORMAT(COALESCE(`invoice`.amount, 0), 2), ',', '') AS `Invoiced`,
                REPLACE(FORMAT(COALESCE(`creditNote`.amount, 0), 2), ',', '') AS `Credited`,
                REPLACE(FORMAT(COALESCE(`payment`.amount, 0), 2), ',', '') AS `Paid`,
                REPLACE(FORMAT((COALESCE(`invoice`.amount, 0) - COALESCE(`creditNote`.amount, 0) - COALESCE(`payment`.amount, 0)), 2), ',', '') AS `Balance`,
                `programme`.name AS `Programme`,
                `region`.region_desc AS `Region`,
                `box`.box_desc AS `Facility`,
                `box_facility`.box_facility_name AS `Location`,
                COALESCE(`booked_bookings`.quantity, 0) AS `Booked Sessions`,
                COALESCE(`cancelled_bookings`.quantity, 0) AS `Cancelled Sessions`,
                COALESCE(`late_cancelled_bookings`.quantity, 0) AS `Late Cancelled Sessions`,
                COALESCE(`no_show_bookings`.quantity, 0) AS `No-Shows`,
                (
                    SELECT
                        p.package_name
                    FROM
                        user_to_package utp1_package
                        INNER JOIN packages p ON utp1_package.package_id = p.package_id
                            AND p.box_id = $boxId
                    WHERE
                        user_to_package.deleted = 0
                        AND utp1_package.user_id = user_to_package.user_id
                    ORDER BY
                        utp1_package.effective_date DESC
                    LIMIT 0,
                    1) AS `Package 1`,
                (
                    SELECT
                        utp1_start.effective_date
                    FROM
                        user_to_package utp1_start
                        INNER JOIN packages p ON utp1_start.package_id = p.package_id
                            AND p.box_id = $boxId
                    WHERE
                        user_to_package.deleted = 0
                        AND utp1_start.user_id = user_to_package.user_id
                    ORDER BY
                        utp1_start.effective_date DESC
                    LIMIT 0,
                    1) AS `P1: Start`,
                (
                    SELECT
                        utp1_end.end_date
                    FROM
                        user_to_package utp1_end
                        INNER JOIN packages p ON utp1_end.package_id = p.package_id
                            AND p.box_id = $boxId
                    WHERE
                        user_to_package.deleted = 0
                        AND utp1_end.user_id = user_to_package.user_id
                    ORDER BY
                        utp1_end.effective_date DESC
                    LIMIT 0,
                    1) AS `P1: End`,
                (
                    SELECT
                        REPLACE(FORMAT(p.package_price, 2), ',', '')
                    FROM
                        user_to_package utp
                        INNER JOIN packages p ON utp.package_id = p.package_id
                            AND p.box_id = $boxId
                    WHERE
                        user_to_package.deleted = 0
                        AND utp.user_id = user_to_package.user_id
                    ORDER BY
                        utp.effective_date DESC
                    LIMIT 0,
                    1) AS `P1: Price`,
                (
                    SELECT
                        IF(p.package_limit = 0, 'Unlimited', CONCAT(p.package_limit, ' - ', plt.package_limit_type_descr))
                        FROM
                            user_to_package utp
                            INNER JOIN packages p ON utp.package_id = p.package_id AND p.box_id = $boxId
                            INNER JOIN package_limit_types plt ON plt.package_limit_type_id = p.package_limit_type_id
                        WHERE
                            user_to_package.deleted = 0 AND utp.user_id = user_to_package.user_id
                        ORDER BY
                            utp.effective_date DESC
                        LIMIT 0,
                        1) AS `P1: Limit`,
                    (
                    SELECT
                        p.package_name
                    FROM
                        user_to_package utp1_package
                        INNER JOIN packages p ON utp1_package.package_id = p.package_id AND p.box_id = $boxId
                    WHERE
                        user_to_package.deleted = 0 AND utp1_package.user_id = user_to_package.user_id
                    ORDER BY
                        utp1_package.effective_date DESC
                    LIMIT 1,
                    1) AS `P2: Package 2`,
                (
                SELECT
                    utp1_start.effective_date
                FROM
                    user_to_package utp1_start
                    INNER JOIN packages p ON utp1_start.package_id = p.package_id AND p.box_id = $boxId
                WHERE
                    user_to_package.deleted = 0 AND utp1_start.user_id = user_to_package.user_id
                ORDER BY
                    utp1_start.effective_date DESC
                LIMIT 1,
                1) AS `P2: Start`,
            (
            SELECT
                utp1_end.end_date
            FROM
                user_to_package utp1_end
                INNER JOIN packages p ON utp1_end.package_id = p.package_id AND p.box_id = $boxId
            WHERE
                user_to_package.deleted = 0 AND utp1_end.user_id = user_to_package.user_id
            ORDER BY
                utp1_end.effective_date DESC
            LIMIT 1,
            1) AS `P2: End`,
            (
            SELECT
                REPLACE(FORMAT(p.package_price, 2), ',', '')
            FROM
                user_to_package utp
                INNER JOIN packages p ON utp.package_id = p.package_id AND p.box_id = $boxId
            WHERE
                user_to_package.deleted = 0 AND utp.user_id = user_to_package.user_id
            ORDER BY
                utp.effective_date DESC
            LIMIT 1,
            1) AS `P2: Price`,
            (
            SELECT
                IF(p.package_limit = 0, 'Unlimited', CONCAT(p.package_limit, ' - ', plt.package_limit_type_descr))
                FROM
                    user_to_package utp
                    INNER JOIN packages p ON utp.package_id = p.package_id AND p.box_id = $boxId
                    INNER JOIN package_limit_types plt ON plt.package_limit_type_id = p.package_limit_type_id
                WHERE
                    user_to_package.deleted = 0 AND utp.user_id = user_to_package.user_id
                ORDER BY
                    utp.effective_date DESC
                LIMIT 1,
                1) AS `P2: Limit`,
            REPLACE(FORMAT(`special_rate`.amount, 2), ',', '') AS `Special Rate`,
            `user_to_box`.created_on AS `Profile Created`,
            `user_to_box`.activated_on AS `Profile Activated`,
            `user`.last_login_on AS `Last Login`,
            `user`.terms_and_conditions_accepted_on AS `TCs Accepted`,
            `user_to_box`.`updated_on` AS 'Last modified On',
            IF(`user_status`.`user_status_id` = 4, `user_to_box`.`deactivated_on`, '') AS 'Deactivated On'
            FROM
                `user_to_box` FORCE INDEX (IDX_3BC79B09D8177B3F)
                    INNER JOIN `users` `user` ON `user_to_box`.user_id = `user`.user_id AND CURDATE() BETWEEN `user_to_box`.effective_date AND `user_to_box`.end_date
                LEFT JOIN `gender` ON `user`.gender_id = `gender`.gender_id
                INNER JOIN `user_status` ON `user_status`.user_status_id = `user_to_box`.user_status_id
                INNER JOIN `user_debit_status` ON `user_to_box`.user_debit_status_id = `user_debit_status`.user_debit_status_id
                INNER JOIN `user_to_facility` ON `user_to_facility`.user_id = `user_to_box`.user_id AND CURDATE() BETWEEN `user_to_facility`.effective_date AND `user_to_facility`.end_date
                INNER JOIN `box_facility` ON `user_to_facility`.box_facility_id = `box_facility`.box_facility_id
                INNER JOIN `boxes` AS `box` ON `user_to_box`.box_id = `box`.box_id
                INNER JOIN `regions` `region` ON `box`.region_id = `region`.region_id
                INNER JOIN `programmes` `programme` ON `user_to_box`.programme_id = `programme`.id
                INNER JOIN `user_to_package` ON `user_to_package`.user_id = `user_to_box`.user_id AND `user_to_package`.deleted = 0
                LEFT JOIN `special_rates` `special_rate` ON `special_rate`.user_id = `user_to_box`.user_id AND `special_rate`.is_active = 1
                LEFT JOIN (
                SELECT
                    fi.user_to_facility_id,
                    SUM(amount) AS amount
                FROM
                    `finance_invoices` fi
                    INNER JOIN user_to_facility utf ON fi.user_to_facility_id = utf.user_to_facility_id
                    INNER JOIN box_facility bf ON utf.box_facility_id = bf.box_facility_id
                WHERE
                    bf.box_id = $boxId AND fi.deleted = 0 AND fi.type = 'invoice'
                GROUP BY
                    user_to_facility_id) `invoice` ON `invoice`.user_to_facility_id = `user_to_facility`.user_to_facility_id
                LEFT JOIN (
                SELECT
                    fi.user_to_facility_id,
                    SUM(amount) AS amount
                FROM
                    `finance_invoices` fi
                    INNER JOIN user_to_facility utf ON fi.user_to_facility_id = utf.user_to_facility_id
                    INNER JOIN box_facility bf ON utf.box_facility_id = bf.box_facility_id
                WHERE
                    bf.box_id = $boxId AND fi.deleted = 0 AND fi.type = 'creditNote'
                GROUP BY
                    user_to_facility_id) `creditNote` ON `creditNote`.user_to_facility_id = `user_to_facility`.user_to_facility_id
                LEFT JOIN (
                SELECT
                    fp.user_to_facility_id,
                    SUM(amount) AS amount
                FROM
                    `finance_payments` fp
                    INNER JOIN user_to_facility utf ON fp.user_to_facility_id = utf.user_to_facility_id
                    INNER JOIN box_facility bf ON utf.box_facility_id = bf.box_facility_id
                WHERE
                    bf.box_id = $boxId AND fp.deleted = 0
                GROUP BY
                    user_to_facility_id) `payment` ON `payment`.user_to_facility_id = `user_to_facility`.user_to_facility_id
                LEFT JOIN (
                SELECT
                    b.user_id,
                    COUNT(b.class_booking_id) AS quantity
                FROM
                    `class_bookings` b
                    INNER JOIN user_to_box utb ON utb.user_id = b.user_id AND utb.box_id = $boxId
                WHERE
                    b.class_booking_status_id = 1
                GROUP BY
                    b.user_id) `booked_bookings` ON `booked_bookings`.user_id = `user_to_box`.user_id
                LEFT JOIN (
                SELECT
                    b.user_id,
                    COUNT(b.class_booking_id) AS quantity
                FROM
                    `class_bookings` b
                    INNER JOIN user_to_box utb ON utb.user_id = b.user_id AND utb.box_id = $boxId
                WHERE
                    b.class_booking_status_id = 2
                GROUP BY
                    b.user_id) `cancelled_bookings` ON `cancelled_bookings`.user_id = `user_to_box`.user_id
                LEFT JOIN (
                SELECT
                    b.user_id,
                    COUNT(b.class_booking_id) AS quantity
                FROM
                    `class_bookings` b
                    INNER JOIN user_to_box utb ON utb.user_id = b.user_id AND utb.box_id = $boxId
                WHERE
                    b.class_booking_status_id = 3
                GROUP BY
                    b.user_id) `late_cancelled_bookings` ON `late_cancelled_bookings`.user_id = `user_to_box`.user_id
                LEFT JOIN (
                SELECT
                    b.user_id,
                    COUNT(b.class_booking_id) AS quantity
                FROM
                    `class_bookings` b
                    INNER JOIN user_to_box utb ON utb.user_id = b.user_id AND utb.box_id = $boxId
                WHERE
                    b.class_booking_status_id = 5
                GROUP BY
                    b.user_id) `no_show_bookings` ON `no_show_bookings`.user_id = `user_to_box`.user_id
            WHERE
                `user_to_box`.user_type_id = 4
                AND `user_to_box`.box_id = $boxId
                AND box_facility.box_id = $boxId
                AND `user_to_box`.deleted = FALSE
                AND user.deleted = FALSE
                $boxFacilityWhere
            GROUP BY
                `user_to_package`.user_id
            ORDER BY
                `user`.name ASC,
                `user`.surname ASC
                    ";

        return DB::select($sql);
    }

    private function getLeadMembers(int $boxId, int $year, ?int $boxFacilityId = null, ?bool $isConverted = false, ?bool $isCount = null, ?bool $isMonthlyBreakdown = null)
    {
        $boxFacilityAndWhere = '';
        $date = new \DateTime("$year-01-01");
        $startDate = date('Y-m-d', $date->getTimestamp());
        $endDate = date('Y-m-d', strtotime('12/31', $date->getTimestamp()));

        if ($boxFacilityId) {
            $boxFacilityAndWhere = 'AND bf.box_facility_id = '.$boxFacilityId;
        }

        if ($isConverted) {
            $andWhere = "AND m.status = 'converted' AND m.converted_on >= '$startDate' AND m.converted_on <= '$endDate'";
        } else {
            $andWhere = "AND m.created_on >= '$startDate' AND m.created_on <= '$endDate'";
        }

        if ($isCount) {
            $select = 'SELECT COUNT(DISTINCT m.member_id) AS `count`';
        } else {
            $select = 'SELECT DISTINCT m.member_id AS id, m.first_name AS `name`, m.last_name AS `surname`, m.email_address AS `email`, m.status, m.created_on AS createdOnDate, m.converted_on AS convertedOn';
        }

        $sql = "
            $select
            FROM lead_members m
            INNER JOIN `box_facility` bf ON m.`box_facility_id` = bf.`box_facility_id`
            WHERE bf.box_id = $boxId
            AND m.deleted = 0
            $andWhere
            $boxFacilityAndWhere
        ";

        if ($isMonthlyBreakdown) {
            $sortBy = $isConverted ? 'convertedOn' : 'createdOnDate';
            $results = $this->sortDataByMonthAndGetTotalCount($sortBy, DB::select($sql), $year);
        } else {
            $results = $isCount ? DB::select($sql.' LIMIT 1') : DB::select($sql);
            $results = $results[0]->count;
        }

        return $results;
    }

    public function exportMemberData(Tenant $box, ?Location $boxFacility): string
    {
        $boxFacilityId = $boxFacility instanceof Location ? $boxFacility->getKey() : null;

        return $this->uploadExportedData($box, $boxFacility, $this->getMemberData($box->getKey(), $boxFacilityId), 'members-export');
    }

    private function uploadExportedData(Tenant $box, ?Location $boxFacility, $data, $exportName): string
    {
        $date = date('Y-m-d');

        if (! $boxFacility instanceof Location) {
            $safeName = preg_replace('/\W+/', '-', strtolower($box->name));
        } else {
            $safeName = preg_replace('/\W+/', '-', strtolower($boxFacility->name));
        }

        // Convert the array to csv data
        $filename = "$exportName-$date-$safeName.csv";

        $csv = fopen('php://temp/maxmemory:'.(5 * 1024 * 1024), 'r+');

        // Set headers for the export
        if (Arr::first($data)) {
            fputcsv($csv, array_keys((array) Arr::first($data)));
        }

        foreach ($data as $row) {
            fputcsv($csv, (array) $row);
        }

        rewind($csv);
        $output = stream_get_contents($csv);

        // Put the content directly in file into the disk
        Storage::disk('tmp')->put('report-exports/'.$filename, $output);

        return Storage::disk('tmp')->temporaryUrl('report-exports/'.$filename, now()->addMinutes(5));
    }

    public function exportFinanceData(Tenant $box, ?Location $boxFacility, $startDate = null, $endDate = null): string
    {
        $boxFacilityId = $boxFacility instanceof Location ? $boxFacility->getKey() : null;

        return $this->uploadExportedData($box, $boxFacility, $this->getFinanceData($box->getKey(), $boxFacilityId, $startDate, $endDate), 'finances-export');
    }

    public function exportBookingData(Tenant $box, ?Location $boxFacility, $startDate = null, $endDate = null): string
    {
        $boxFacilityId = $boxFacility instanceof Location ? $boxFacility->getKey() : null;

        return $this->uploadExportedData($box, $boxFacility, $this->getBookingData($box->getKey(), $boxFacilityId, $startDate, $endDate), 'bookings-export');
    }

    private function getFinanceData($tenantId, $locationId, $startDate = null, $endDate = null): array
    {
        $locationWhere = $locationId !== null ? "AND `box_facility`.box_facility_id = $locationId" : '';
        $dateWhere = '';

        if ($startDate && $endDate) {
            $dateWhere = " AND `invoice`.due_on BETWEEN '$startDate' AND '$endDate'";
        } elseif ($startDate) {
            $dateWhere = " AND `invoice`.due_on >= '$startDate'";
        } elseif ($endDate) {
            $dateWhere = " AND `invoice`.due_on <= '$endDate'";
        }

        $sql = "
            SELECT
                `invoice`.invoice_id AS `ID`,
                `invoice`.code AS `Code`,
                `invoice`.description AS `Description`,
                `invoice`.status AS `Invoice Status`,
                `debit_batch`.debit_batch_id AS `Debit Batch ID`,
                `debit_day_date`.debit_day_date AS `Debit Batch Date`,

                IF (`invoice`.user_to_package_id,
                (
                    SELECT GROUP_CONCAT(`package`.package_name SEPARATOR ', ')
                    FROM user_to_package `user_to_package`
                    INNER JOIN `packages` `package` ON `package`.package_id = `user_to_package`.package_id AND `package`.`box_id` = {$tenantId}
                    WHERE `user_to_package`.`user_id` = `user`.user_id
                    AND ((`user_to_package`.`effective_date` <= NOW() AND `user_to_package`.`end_date` > NOW()) OR `user_to_package`.`end_date` IS NULL)
                    AND `user_to_package`.`deleted` = 0
                    AND `package`.`is_active` = 1
                ), '') AS `Billed Packages`,

                IF (`invoice`.type = 'creditNote', -(`invoice`.amount), ABS(`invoice`.amount)) AS `Amount`,
                IF (`invoice`.type = 'creditNote', -(ABS((`items`.`amount` * CASE WHEN box_facility.vat_percent IS NOT NULL
	AND items.vat IS NULL THEN
	box_facility.vat_percent
WHEN box_facility.vat_percent IS NOT NULL
	AND items.vat IS NOT NULL THEN
	items.vat
WHEN box_facility.vat_percent IS NULL
	AND items.vat IS NOT NULL THEN
	items.vat
ELSE
	0
END) / 100)), ABS(SUM((`items`.`amount` * CASE WHEN box_facility.vat_percent IS NOT NULL
	AND items.vat IS NULL THEN
	box_facility.vat_percent
WHEN box_facility.vat_percent IS NOT NULL
	AND items.vat IS NOT NULL THEN
	items.vat
WHEN box_facility.vat_percent IS NULL
	AND items.vat IS NOT NULL THEN
	items.vat
ELSE
	0
END) / 100))) AS `VAT`,

                `invoice`.type AS `Type`,
                SUM(`payment`.amount) AS `Total Paid`,
                GROUP_CONCAT(`payment`.type) AS `Payment Method(s)`,
                COUNT(`payment`.payment_id) AS `Number Of Payments`,
                `invoice`.created_on AS `Created`,
                IF (`invoice`.updated_on != `invoice`.created_on, `invoice`.updated_on, NULL) AS `Updated`,
                `invoice`.due_on AS `Due`,
                MAX(`payment`.date_time) AS `Last Paid`,
                `invoice`.sent_on AS `Last Sent`,
                IF (`invoice`.created_by_id != `user`.user_id, CONCAT(`created_by_user`.name, ' ', `created_by_user`.surname), 'Octiv') AS `Creator`,

                `box_facility`.box_facility_name AS `Location`,
                `user`.user_id AS `User ID`,
                `user_status`.user_status_desc AS `User Status`,
                TRIM(TRIM(CHAR(9) from `user`.name)) AS `First Name`,
                TRIM(TRIM(CHAR(9) from `user`.surname)) AS `Last Name`,
                `user`.email AS `Email`,
                TRIM(TRIM(CHAR(9) from `user`.mobile)) AS `Mobile`,
                `gender`.gender_desc AS `Gender`,
                `user`.dob AS `DOB`,
                `user`.auto_invoicing_day AS `User-specific Cash Invoice Day`,
                `user`.auto_invoicing_due_day AS `User-specific Cash Invoice Due Day`,
                `programme`.name AS `Programme`

                FROM `finance_invoices` `invoice`
                LEFT JOIN `users` `created_by_user` ON `created_by_user`.user_id = `invoice`.created_by_id
                INNER JOIN `user_to_facility` ON `user_to_facility`.user_to_facility_id = `invoice`.user_to_facility_id
                INNER JOIN `box_facility` ON `box_facility`.box_facility_id = `user_to_facility`.box_facility_id
                INNER JOIN `users` `user` ON `user_to_facility`.user_id = `user`.user_id
                LEFT JOIN `gender` ON `user`.gender_id = `gender`.gender_id
                LEFT JOIN `programmes` `programme` ON `user`.programme_id = `programme`.id
                INNER JOIN `user_status` ON `user_status`.user_status_id = `user`.user_status_id
                LEFT JOIN `finance_payments` `payment` ON `invoice`.invoice_id = `payment`.invoice_id AND `payment`.deleted = 0
                LEFT JOIN `finance_invoice_items` `items` ON `invoice`.invoice_id = `items`.invoice_id AND `items`.deleted = 0

                LEFT JOIN `user_to_batch` ON `user_to_batch`.invoice_id = `invoice`.invoice_id
                LEFT JOIN `debit_batches` `debit_batch` ON `debit_batch`.debit_batch_id = `user_to_batch`.debit_batch_id
                LEFT JOIN `debit_day_dates` `debit_day_date` ON `debit_day_date`.debit_day_date_id = `debit_batch`.debit_day_date_id

                WHERE `box_facility`.box_id = {$tenantId} {$locationWhere} {$dateWhere}
                AND `invoice`.deleted = 0
                GROUP BY `invoice`.invoice_id, `invoice`.due_on
                ORDER BY `invoice`.due_on
        ";

        return DB::select($sql);
    }

    private function getBookingData($boxId, $boxFacilityId, $startDate = null, $endDate = null): array
    {
        $boxFacilityWhere = $boxFacilityId !== null ? "AND `class`.box_facility_id = $boxFacilityId" : '';

        if ($startDate && $endDate) {
            $dateWhere = " AND `class_to_date`.class_date BETWEEN '$startDate' AND '$endDate'";
        } elseif ($startDate) {
            $dateWhere = " AND `class_to_date`.class_date >= '$startDate'";
        } elseif ($endDate) {
            $dateWhere = " AND `class_to_date`.class_date <= '$endDate'";
        } else {
            $dateWhere = '';
        }

        $sql = "
            SELECT
                `booking`.class_booking_id AS `Booking ID`,
                `class`.class_id AS `Class ID`,
                `class`.class_name AS `Class`,
                `class_to_date`.class_date AS `Date`,
                `package`.package_name AS `Package`,
                `class_booking_status`.class_booking_status_descr AS `Status`,
                IF (`booking`.is_checked_in, 'Yes', 'No') AS `Checked In`,
                IF (`class_to_date`.start_time IS NOT NULL, `class_to_date`.start_time, `class`.start_time) AS `Start`,
                IF (`class_to_date`.end_time IS NOT NULL, `class_to_date`.end_time, `class`.end_time) AS `End`,
                IF (`custom_head_class_coach`.class_coach_id IS NOT NULL, CONCAT(`custom_head_coach_user`.name, ' ', `custom_head_coach_user`.surname, ' [TEMP]'), CONCAT(`head_coach_user`.name, ' ', `head_coach_user`.surname)) AS `Head Coach`,
                IF (`custom_supporting_class_coach`.class_coach_id IS NOT NULL, CONCAT(`custom_supporting_coach_user`.name, ' ', `custom_supporting_coach_user`.surname, ' [TEMP]'), CONCAT(`head_coach_user`.name, ' ', `head_coach_user`.surname)) AS `Supporting Coach`,
                `booking`.top_up_used AS `Used Topup`,

                `booking`.dt_added AS `Booked`,
                CONCAT(`created_by_user`.name, ' ', `created_by_user`.surname) AS `Booked By`,

                `user`.user_id AS `User ID`,
                `user_status`.user_status_desc AS `Current Status`,
                TRIM(TRIM(CHAR(9) from `user`.name)) AS `First Name`,
                TRIM(TRIM(CHAR(9) from `user`.surname)) AS `Last Name`,
                `user`.email AS `Email`,
                TRIM(TRIM(CHAR(9) from `user`.mobile)) AS `Mobile`,
                `gender`.gender_desc AS `Gender`,
                `user`.dob AS `DOB`,
                `programme`.name AS `Program`,

                `box_facility`.box_facility_id AS `Location ID`,
                `box_facility`.box_facility_name AS `Location Name`,
                `user`.created_on AS `Profile Created`,
                `user`.activated_on AS `Profile Activated`,
                `user`.last_login_on AS `Last Login`,
                `user`.terms_and_conditions_accepted_on AS `TCs Accepted`,
                `user_contract`.starting_on AS `Contract Start Date`,
                `user_contract`.accepted_on AS `Contract Accepted On`

                FROM `class_bookings` `booking` FORCE INDEX (class_bookings_class_to_date_id_class_booking_status_id_index)
                INNER JOIN `class_booking_status` ON `class_booking_status`.class_booking_status_id = `booking`.class_booking_status_id
                INNER JOIN `class_to_dates` `class_to_date` ON `class_to_date`.class_to_date_id = `booking`.class_to_date_id
                INNER JOIN `classes` `class` ON `class`.class_id = `class_to_date`.class_id

                LEFT JOIN `class_coaches` `custom_head_class_coach` ON `class_to_date`.coach_id = `custom_head_class_coach`.class_coach_id AND `custom_head_class_coach`.is_active = 1
                LEFT JOIN `users` `custom_head_coach_user` ON `custom_head_class_coach`.coach_id = `custom_head_coach_user`.user_id

                LEFT JOIN `class_coaches` `custom_supporting_class_coach` ON `class_to_date`.supporting_coach_id = `custom_supporting_class_coach`.class_coach_id AND `custom_supporting_class_coach`.is_active = 1
                LEFT JOIN `users` `custom_supporting_coach_user` ON `custom_supporting_class_coach`.coach_id = `custom_supporting_coach_user`.user_id

                LEFT JOIN `class_coaches` `class_head_coach` ON `class`.class_id = `class_head_coach`.class_id AND `class_head_coach`.is_active = 1 AND `class_head_coach`.coach_type_id = 1
                LEFT JOIN `users` `head_coach_user` ON `class_head_coach`.coach_id = `head_coach_user`.user_id

                LEFT JOIN `class_coaches` `class_supporting_coach` ON `class`.class_id = `class_supporting_coach`.class_id AND `class_supporting_coach`.is_active = 1 AND `class_supporting_coach`.coach_type_id = 2
                LEFT JOIN `users` `supporting_coach_user` ON `class_supporting_coach`.coach_id = `supporting_coach_user`.user_id

                LEFT JOIN `user_to_package` ON `user_to_package`.user_to_package_id = `booking`.user_package_id
                LEFT JOIN `packages` `package` ON `package`.package_id = `user_to_package`.package_id

                INNER JOIN `users` `user` ON `user`.user_id = `booking`.user_id
                LEFT JOIN `user_contracts` `user_contract` ON `user`.user_id = user_contract.user_id AND CURDATE() BETWEEN `user_contract`.starting_on AND `user_contract`.ending_on
                LEFT JOIN `gender` ON `user`.gender_id = `gender`.gender_id
                LEFT JOIN `programmes` `programme` ON `user`.programme_id = `programme`.id
                INNER JOIN `user_status` ON `user_status`.user_status_id = `user`.user_status_id
                INNER JOIN `box_facility` ON `box_facility`.box_facility_id = `class`.box_facility_id
                INNER JOIN `boxes` `box` ON `box`.box_id = `class`.box_id
                LEFT JOIN `users` `created_by_user` ON `created_by_user`.user_id = `booking`.created_by_id

                WHERE `box`.box_id = $boxId $boxFacilityWhere $dateWhere
                LIMIT 100000
        ";

        return DB::select($sql);
    }

    public function exportLeadData(Tenant $box, ?Location $boxFacility, $startDate = null, $endDate = null): string
    {
        $boxFacilityId = $boxFacility instanceof Location ? $boxFacility->getKey() : null;

        return $this->uploadExportedData($box, $boxFacility, $this->getLeadData($box->getKey(), $boxFacilityId, $startDate, $endDate), 'leads-export');
    }

    private function getLeadData($boxId, $boxFacilityId, $startDate = null, $endDate = null): array
    {
        $boxFacilityWhere = $boxFacilityId !== null ? "AND `lead`.box_facility_id = $boxFacilityId" : '';

        if ($startDate && $endDate) {
            $dateWhere = " AND `lead`.created_on BETWEEN '$startDate' AND '$endDate'";
        } elseif ($startDate) {
            $dateWhere = " AND `lead`.created_on >= '$startDate'";
        } elseif ($endDate) {
            $dateWhere = " AND `lead`.created_on <= '$endDate'";
        } else {
            $dateWhere = '';
        }

        $sql = "
            SELECT
            `lead`.member_id AS `ID`,
            TRIM(TRIM(CHAR(9) from `users`.name)) AS `First Name`,
            TRIM(TRIM(CHAR(9) from `users`.surname)) AS `Last Name`,
            `users`.email AS `Email`,
            TRIM(TRIM(CHAR(9) from `users`.mobile)) AS `Mobile`,
            `gender`.gender_desc AS `Gender`,
            `users`.dob AS `DOB`,
            `facility`.box_facility_name AS `Location`,
            `lead`.status AS `Status`,
            `lead`.created_on AS `Created On`,
            `lead`.last_contacted_date AS `Last Contacted On`,
            `lead`.next_follow_up_date AS `Next Follow Up On`,
            `waiver`.signed_on AS `Signed On`,
            `lead`.source AS `Source`,
            `lead`.type AS `Type`,
            `lead`.notes AS `Notes`
            FROM lead_members `lead`
            INNER JOIN `users` ON `lead`.user_id = `users`.user_id
            LEFT JOIN `gender` ON `users`.gender_id = `gender`.gender_id
            LEFT JOIN `users` `capturer` ON `lead`.captured_by_id = `capturer`.user_id
            LEFT JOIN `users` `referrer` ON `lead`.referred_by_id = `referrer`.user_id
            LEFT JOIN `lead_waivers` `waiver` ON `lead`.waiver_id = `waiver`.waiver_id
            INNER JOIN `box_facility` `facility` ON `lead`.box_facility_id = `facility`.box_facility_id
            WHERE
            `lead`.deleted = 0 AND
            `facility`.box_id = $boxId $boxFacilityWhere $dateWhere
            ORDER BY `lead`.created_on
        ";

        return DB::select($sql);
    }
}
