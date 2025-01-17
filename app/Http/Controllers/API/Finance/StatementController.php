<?php

namespace App\Http\Controllers\API\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Statements\BulkSendStatementsRequest;
use App\Http\Requests\Statements\DownloadStatementRequest;
use App\Http\Requests\Statements\PrintStatementRequest;
use App\Http\Requests\Statements\SendStatementRequest;
use App\Http\Requests\Statements\ViewStatementRequest;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\TenantUserService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\QueryParam;

class StatementController extends Controller
{
    #[QueryParam('filter[user_id]', 'integer', true)]
    #[QueryParam('filter[tenant_id]', 'integer', true)]
    #[QueryParam('filter[between]', 'string', true)]
    public function list(ViewStatementRequest $request): JsonResponse
    {
        $user = User::query()->find($request->input('filter.user_id'));
        $tenant = Tenant::query()->find($request->input('filter.tenant_id'));

        $startDate = explode(',', $request->input('filter.between'))[0];
        $endDate = explode(',', $request->input('filter.between'))[1];

        $statement = (new InvoiceService())->getUserStatement($user, $tenant, $startDate, $endDate);

        return response()->json($statement);
    }

    public function print(PrintStatementRequest $request): string
    {
        $tenant = Tenant::query()->find($request->input('tenant_id'));
        $user = User::query()->find($request->input('user_id'));
        $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($user, $tenant);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $location = (new TenantUserService())->getLocationUserByTenant($user, $tenant)?->location;
        $locationLogo = $location instanceof Location ? $location->logo_url : null;

        $statement = (new InvoiceService())->getUserStatement($user, $tenant, $request->input('start_date'), $request->input('end_date'));
        $statement = json_decode(json_encode((object) $statement), false);

        $view = view('finance.print-statement', compact('userTenant', 'statement', 'user', 'startDate', 'endDate', 'locationLogo', 'location'))->render();
        header('Content-type: text/html');
        header('Content-Disposition: attachment; filename=view.html');

        return $view;
    }

    public function download(DownloadStatementRequest $request): JsonResponse
    {
        $tenant = Tenant::query()->find($request->input('tenant_id'));
        $user = User::query()->find($request->input('user_id'));
        $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($user, $tenant);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $location = (new TenantUserService())->getLocationUserByTenant($user, $tenant)?->location;
        $locationLogo = $location instanceof Location ? $location->logo_url : null;

        $statement = (new InvoiceService())->getUserStatement($user, $tenant, $startDate, $endDate);
        $statement = json_decode(json_encode((object) $statement), false);

        $path = 'user-statements/'.$user->name.'_'.$user->surname.'_'.$startDate.'_'.$endDate.'_statement.pdf';

        Pdf::loadView('finance.print-statement', [
            'statement' => $statement,
            'user' => $user,
            'userTenant' => $userTenant,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'locationLogo' => $locationLogo,
            'location' => $location,
            'isDownload' => true,
        ])->save($path, 'tmp');

        $fileUrl = Storage::disk('tmp')->temporaryUrl($path, now()->addMinutes(10));

        return response()->json($fileUrl);
    }

    public function send(SendStatementRequest $request)
    {
        $user = User::query()->find($request->input('user_id'));
        $tenant = Tenant::query()->find($request->input('tenant_id'));

        (new InvoiceService())->sendUserStatement($user, $tenant, $request->input('start_date'), $request->input('end_date'), $request->input('message'));

        return response()->noContent();
    }

    public function bulkSend(BulkSendStatementsRequest $request): Response
    {
        $tenant = Tenant::findOrFail($request->tenant_id);

        foreach ($request->input('user_ids') as $userId) {
            $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($userId, $tenant);

            if (! $userTenant instanceof TenantUser) {
                continue;
            }

            (new InvoiceService())->sendUserStatement($userTenant->user, $tenant, $request->input('start_date'), $request->input('end_date'), $request->input('message'));
        }

        return response()->noContent();
    }
}
