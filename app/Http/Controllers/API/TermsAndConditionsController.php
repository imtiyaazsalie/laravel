<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TermsOfUse\CreateTermsOfUseRequest;
use App\Http\Requests\TermsOfUse\ReadTermsOfUseRequest;
use App\Http\Requests\User\AcceptTermsAndConditionsRequest;
use App\Http\Resources\TermsConditionsResource;
use App\Models\TermsConditions;
use App\Models\User;
use App\Services\UserService;

class TermsAndConditionsController extends Controller
{
    public function __construct(
        private UserService $user,
    ) {
        //
    }

    public function show(ReadTermsOfUseRequest $request)
    {
        return new TermsConditionsResource(TermsConditions::latest('created_on')->first());
    }

    public function accept(AcceptTermsAndConditionsRequest $request)
    {
        $this->user->acceptTermsConditions(auth()->user());

        return response()->noContent();
    }

    public function storeTermsAndConditions(CreateTermsOfUseRequest $request)
    {
        $termsOfUse = new TermsConditions();
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
