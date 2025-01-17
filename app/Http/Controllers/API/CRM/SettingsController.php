<?php

namespace App\Http\Controllers\API\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\Settings\ListSettingsRequest;
use App\Http\Requests\CRM\Settings\UpdateSettingsRequest;
use App\Http\Resources\CRM\SettingsResource;
use App\Models\CrmSetting;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SettingsController extends Controller
{
    #[QueryParam('filter[location_id]', 'string', '', true)]
    public function show(ListSettingsRequest $request): SettingsResource
    {
        $settings = QueryBuilder::for(CrmSetting::class)
            ->allowedFilters([
                AllowedFilter::exact('location_id', 'box_facility_id'),
            ])
            ->first();

        if (! $settings) {
            $settings = CrmSetting::create([
                'box_facility_id' => $request->input('filter.location_id'),
                'sms_status' => 'disabled',
                'email_status' => 'enabled',
            ]);
        }

        return new SettingsResource($settings);
    }

    public function update(UpdateSettingsRequest $request, CrmSetting $setting): SettingsResource
    {
        $image = [];

        if ($request->has('logo')) {

            if (empty($request->logo)) {
                $image = [
                    'file_name' => null,
                    'file_absolute_path' => null,
                    'file_relative_path' => null,
                    'file_mime' => null,
                ];
            } else {

                $filename = md5($setting->location_id.time()).'.'.$request->file('logo')->getClientOriginalExtension();

                $path = 'crm/settings/box-logos/'.$setting->location_id.'/';

                $request->file('logo')->storePubliclyAs($path, $filename, ['disk' => 'public']);

                $setting->location->logofile = $path.$filename;
                $setting->location->save();

                $image = [
                    'file_name' => $filename,
                    'file_absolute_path' => Storage::disk('public')->url($path.$filename),
                    'file_relative_path' => $path.$filename,
                    'file_mime' => $request->file('logo')->getMimeType(),
                ];

            }
        }

        $setting->update(
            array_merge(
                $image,
                $request->safe()->only([
                    'sms_status',
                    'email_status',
                    'email_signature',
                    'reply_to',
                    'sender_name',
                ]),
            )
        );

        return new SettingsResource($setting);
    }
}
