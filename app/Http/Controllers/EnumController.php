<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowEnumRequest;
use App\Services\EnumService;
use Exception;

class EnumController extends Controller
{
    public function __construct(
        public EnumService $enums
    ) {
        //
    }

    public function list()
    {
        return response()->json(
            $this->enums->list()->paginate()
        );
    }

    public function show(ShowEnumRequest $request)
    {
        abort_unless(
            $this->enums->list()->contains($request->enum),
            404,
            'Not found.'
        );

        $class = $this->enums::NAMESPACE.$request->enum;

        try {
            return response()->json(call_user_func($class.'::asArray'));
        } catch (Exception $ex) {
            report($ex);
            abort(400, 'Enum not yet available.');
        }

    }
}
