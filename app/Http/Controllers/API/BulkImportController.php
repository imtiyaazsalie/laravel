<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkImport\ImportUserBankingDetailsRequest;
use App\Http\Requests\Admin\BulkImport\ImportUsersRequest;
use App\Imports\UsersBankingDetailsImport;
use App\Imports\UsersImport;
use Maatwebsite\Excel\Excel as ExcelExcel;
use Maatwebsite\Excel\Facades\Excel;

class BulkImportController extends Controller
{
    public function userBankingDetails(ImportUserBankingDetailsRequest $request)
    {
        $isUpload = $request->input('is_upload', false);

        $import = new UsersBankingDetailsImport($request->get('tenant_id'), ! $isUpload);

        Excel::import(
            $import,
            $request->file('csv_file'),
            'tmp',
            ExcelExcel::CSV
        );

        return response()->json($import->getResults());
    }

    public function users(ImportUsersRequest $request)
    {
        $import = new UsersImport($request->get('tenant_id'));

        Excel::import(
            $import,
            $request->file('csv_file'),
            'tmp',
        );

        return response()->json(
            $import->getResults()
        );
    }
}
