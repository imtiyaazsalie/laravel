<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PrivacyPolicy\CreatePrivacyPolicyRequest;
use App\Http\Requests\PrivacyPolicy\ReadPrivacyPolicyRequest;
use App\Http\Resources\TermsConditionsResource;
use App\Models\TermsConditions;
use App\Models\User;
use Spatie\QueryBuilder\QueryBuilder;

class PrivacyPolicyController extends Controller
{
    public function show(ReadPrivacyPolicyRequest $request): TermsConditionsResource
    {
        return new TermsConditionsResource(QueryBuilder::for(TermsConditions::class)->latest()->first());
    }

    public function store(CreatePrivacyPolicyRequest $request): TermsConditionsResource
    {
        $termsConditions = new TermsConditions();

        $termsConditions->fill([
            'content' => $request->get('content'),
            'released_on' => now(),
        ]);

        $termsConditions->save();

        if ($request->should_users_accept) {
            User::where('deleted', '=', 0)->update([
                'terms_and_conditions_accepted' => 0,
                'terms_and_conditions_accepted_on' => null,
            ]);
        }

        return new TermsConditionsResource($termsConditions);
    }
}
