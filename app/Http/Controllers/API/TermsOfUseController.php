<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TermsOfUse\CreateTermsOfUseRequest;
use App\Http\Requests\TermsOfUse\ReadTermsOfUseRequest;
use App\Http\Resources\TermsConditionsResource;
use App\Models\TermsOfUse;
use App\Models\User;

class TermsOfUseController extends Controller
{
    public function show(ReadTermsOfUseRequest $request)
    {
        return new TermsConditionsResource(TermsOfUse::latest('created_on')->first());
    }

    public function store(CreateTermsOfUseRequest $request)
    {
        $termsOfUse = new TermsOfUse();
        $termsOfUse->fill([
            'content' => $request->get('content'),
            'released_on' => now()->toDateTimeString(),
        ]);
        $termsOfUse->save();

        if ($request->get('should_users_accept', true)) {
            User::where('deleted', '=', 0)->update([
                'terms_and_conditions_accepted' => 0,
                'terms_and_conditions_accepted_on' => null, ]
            );
        }

        return new TermsConditionsResource($termsOfUse);
    }
}
