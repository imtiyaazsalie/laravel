<?php

namespace App\Http\Controllers\API\CRM;

use App\Enums\MailerSchedule;
use App\Enums\MailerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\Mailer\CopyMailerRequest;
use App\Http\Requests\CRM\Mailer\CreateMailerRequest;
use App\Http\Requests\CRM\Mailer\DeleteMailerRequest;
use App\Http\Requests\CRM\Mailer\ListMailersRequest;
use App\Http\Requests\CRM\Mailer\ReadMailerRequest;
use App\Http\Requests\CRM\Mailer\UpdateMailerRequest;
use App\Http\Resources\CRM\MailerRecipientResource;
use App\Http\Resources\CRM\MailerResource;
use App\Models\Mailer;
use App\Models\MailerAttachment;
use App\Models\MailerRecipient;
use App\Models\TenantUser;
use App\Services\CRM\MailerService;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MailerController extends Controller
{
    public function list(ListMailersRequest $request)
    {
        $mailers = QueryBuilder::for(Mailer::class)
            ->allowedFilters([
                AllowedFilter::exact('type'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('tenant_id', 'box_id'),
                AllowedFilter::exact('location_id', 'box_facility_id'),
                AllowedFilter::exact('created_by_id'),
            ])
            ->whereIn('frequency', [
                MailerSchedule::NOW,
                MailerSchedule::ONCEOFF,
            ])
            ->allowedSorts([
                'name',
                'created_on',
                'type',
            ])
            ->defaultSort('-created_on')
            ->_paginate();

        return MailerResource::collection($mailers);
    }

    public function recipients(ReadMailerRequest $request, Mailer $mailer)
    {
        return MailerRecipientResource::collection(
            MailerRecipient::query()->with('user')->where('mailer_id', $mailer->getKey())->_paginate()
        );
    }

    public function show(ReadMailerRequest $request, Mailer $mailer)
    {
        if (str($request->input('include'))->contains('recipients')) {
            $mailer->loadMissing('recipients.user');
        }

        return new MailerResource(
            $mailer->loadMissing('attachments')
        );
    }

    public function create(CreateMailerRequest $request)
    {
        $type = MailerType::from($request->type);

        $imageHeader = null;
        $imageFooter = null;
        $attachment = null;

        /**
         * Handle file uploads
         */
        if ($type === MailerType::EMAIL && $request->has('image_header') && $request->image_header) {
            $file = uniqid(rand(), true).'.'.$request->file('image_header')->getClientOriginalExtension();

            $path = 'crm/mailer/';

            $request->file('image_header')->storePubliclyAs(
                $path,
                $file,
                [
                    'disk' => 'public',
                    'visibility' => 'public',
                ]
            );

            $imageHeader = $path.$file;
        }

        if ($type === MailerType::EMAIL && $request->has('image_footer') && $request->image_footer) {
            $file = uniqid(rand(), true).'.'.$request->file('image_footer')->getClientOriginalExtension();

            $path = 'crm/mailer/';

            $request->file('image_footer')->storePubliclyAs(
                $path,
                $file,
                [
                    'disk' => 'public',
                    'visibility' => 'public',
                ]
            );

            $imageFooter = $path.$file;
        }

        if ($type === MailerType::EMAIL && $request->has('attachment') && $request->attachment) {
            $file = uniqid(rand(), true).'.'.$request->file('attachment')->getClientOriginalExtension();

            $path = 'crm/mailer/';

            $request->file('attachment')->storeAs(
                $path,
                $file,
                [
                    'disk' => 'private',
                    'visibility' => 'private',
                ]
            );

            $attachment = [
                'attachment_path' => $path.$file,
                'attachment_mime' => $request->file('attachment')->getMimeType(),
                'attachment_name' => $request->file('attachment')->getClientOriginalName(),
            ];
        }

        $data = match ($type) {
            MailerType::EMAIL => [
                'name',
                'subject',
                'content',
                'reply_to',
                'sender_name',
            ],
            MailerType::PUSH => [
                'name',
                'title',
                'content',
            ],
            MailerType::SMS => [
                'name',
                'content',
            ],
            default => throw new RuntimeException('Mailer type unknown.'),
        };

        $mailer = new Mailer([
            ...$request->safe()->only($data),
            'tenant_id' => $request->tenant_id,
            'location_id' => $request->location_id,
            'type' => $type,
            'frequency' => $request->frequency,
            'status' => 'upcoming',
            'image_header' => $imageHeader,
            'image_footer' => $imageFooter,
        ]);

        if ($request->input('frequency') === MailerSchedule::NOW->value) {
            $mailer->next_scheduled_for = $mailer->getNextScheduledForDate();
        } elseif ($request->input('frequency') === MailerSchedule::ONCEOFF->value) {
            $mailer->next_scheduled_for = Carbon::parse($request->send_at, $mailer->getTimezone()->zone)->setTimezone(new DateTimeZone('UTC'));
        }

        /**
         * Store mailer and recipients in transaction to avoid timing problems on dispatch cron.
         */
        $mailer = DB::transaction(function () use ($mailer, $attachment, $request) {
            $mailer->save();

            (new MailerService)->addMailerRecipients($mailer, $request->recipients);

            if ($attachment) {
                $mailer->attachments()->create($attachment);
            }

            return $mailer;
        });

        return new MailerResource($mailer->loadMissing('attachments'));
    }

    public function update(UpdateMailerRequest $request, Mailer $mailer)
    {
        $attachment = null;

        /**
         * Handle file uploads
         */
        if ($mailer->type === MailerType::EMAIL && $request->has('image_header')) {
            // Delete the file from the server
            if ($mailer->image_header && Mailer::query()->whereKeyNot($mailer)->where('image_header', $mailer->image_header_url)->doesntExist()) {
                Storage::disk('public')->delete($mailer->image_header_url);
            }

            if (is_null($request->file('image_header'))) {
                // Set the image to null
                $mailer->update(['image_header' => null]);
            } else {
                $fileName = uniqid(rand(), true).'.'.$request->file('image_header')->getClientOriginalExtension();
                $filePath = 'crm/mailer/';

                $request->file('image_header')->storePubliclyAs(
                    $filePath,
                    $fileName,
                    [
                        'disk' => 'public',
                        'visibility' => 'public',
                    ]
                );

                $mailer->update(['image_header' => $filePath.$fileName]);
            }
        }

        if ($mailer->type === MailerType::EMAIL && $request->has('image_footer')) {
            // Delete the file from the server
            if ($mailer->image_footer && Mailer::query()->whereKeyNot($mailer)->where('image_footer', $mailer->image_footer_url)->doesntExist()) {
                Storage::disk('public')->delete($mailer->image_footer_url);
            }

            if (is_null($request->file('image_footer'))) {
                // Set the image to null
                $mailer->update(['image_footer' => null]);
            } else {
                $fileName = uniqid(rand(), true).'.'.$request->file('image_footer')->getClientOriginalExtension();
                $filePath = 'crm/mailer/';

                $request->file('image_footer')->storePubliclyAs(
                    $filePath,
                    $fileName,
                    [
                        'disk' => 'public',
                        'visibility' => 'public',
                    ]
                );

                $mailer->update(['image_footer' => $filePath.$fileName]);
            }
        }

        if ($mailer->type === MailerType::EMAIL && $request->has('attachment')) {
            if (is_null($request->file('attachment'))) {
                $mailer->attachments()->delete();
            } else {
                $fileName = uniqid(rand(), true).'.'.$request->file('attachment')->getClientOriginalExtension();
                $filePath = 'crm/mailer/';

                $request->file('attachment')->storeAs(
                    $filePath,
                    $fileName,
                    [
                        'disk' => 'private',
                        'visibility' => 'private',
                    ]
                );

                $attachment = [
                    'attachment_path' => $filePath.$fileName,
                    'attachment_mime' => $request->file('attachment')->getMimeType(),
                    'attachment_name' => $request->file('attachment')->getClientOriginalName(),
                ];
            }
        }

        $data = match ($mailer->type) {
            MailerType::EMAIL => [
                'name',
                'subject',
                'content',
                'reply_to',
                'sender_name',
            ],
            MailerType::PUSH => [
                'name',
                'title',
                'content',
            ],
            MailerType::SMS => [
                'name',
                'content',
            ],
            default => throw new RuntimeException('Mailer type unknown.'),
        };

        $mailer->forceFill([
            ...$request->safe()->only([
                ...$data,
                'frequency',
            ]),
            'status' => 'upcoming',
        ]);

        if ($request->input('frequency') === MailerSchedule::NOW->value) {
            $mailer->next_scheduled_for = $mailer->getNextScheduledForDate();
        } elseif ($request->input('frequency') === MailerSchedule::ONCEOFF->value) {
            $mailer->next_scheduled_for = Carbon::parse($request->send_at, $mailer->getTimezone()->zone)->setTimezone(new DateTimeZone('UTC'));
        }

        /**
         * Store mailer and recipients in transaction to avoid timing problems on dispatch cron.
         */
        DB::transaction(function () use ($mailer, $attachment, $request) {
            $mailer->save();

            if ($request->has('recipients')) {
                (new MailerService)->addMailerRecipients($mailer, $request->recipients);
            }

            if ($attachment) {
                $mailer->attachments()->create($attachment);
            }
        });
    }

    public function delete(DeleteMailerRequest $request, Mailer $mailer)
    {
        $mailer->delete();

        // TODO: (Symphony TODO) Permanent delete mailer and clean up mailer data(recipients, attachments and scheduled mailers)

        return response()->noContent();
    }

    public function copy(CopyMailerRequest $request, Mailer $mailer)
    {
        TenantUser::query()
            ->where('user_id', auth()->user()->getAuthIdentifier())
            ->where('box_id', $mailer->tenant_id)
            ->firstOrFail();

        $mailer->loadMissing('attachments');

        $copy = $mailer->replicate();
        $copy->created_on = Carbon::now();
        $copy->status = 'draft';

        if ($mailer->image_header) {

            $contents = Storage::disk('public')->get($mailer->image_header);

            if ($contents !== null) {
                $filename = str($mailer->image_header)->beforeLast('.')->append('-copy.'.str($mailer->image_header)->afterLast('.'));

                Storage::disk('public')->put($filename, $contents, [
                    'visibility' => 'public',
                ]);

                $copy->image_header = $filename;
            }
        }

        if ($mailer->image_footer) {

            $contents = Storage::disk('public')->get($mailer->image_footer);

            if ($contents !== null) {
                $filename = str($mailer->image_footer)->beforeLast('.')->append('-copy.'.str($mailer->image_header)->afterLast('.'));

                Storage::disk('public')->put($filename, $contents, [
                    'visibility' => 'public',
                ]);

                $copy->image_footer = $filename;
            }
        }

        $copy->save();

        foreach ($mailer->recipients as $recipient) {
            $replicatedMailerRecipient = $recipient->replicate();
            $replicatedMailerRecipient->mailer_id = $copy->getKey();
            $replicatedMailerRecipient->save();
        }

        foreach ($mailer->attachments as $attachment) {

            $contents = Storage::disk('private')->get($attachment->attachment_path);

            if ($contents !== null) {
                $filename = str($attachment->attachment_path)->beforeLast('.')->append('-copy.'.str($attachment->attachment_path)->afterLast('.'));

                Storage::disk('private')->put($filename, $contents, [
                    'visibility' => 'private',
                ]);

                MailerAttachment::create([
                    'mailer_id' => $copy->getKey(),
                    'attachment_path' => $filename->toString(),
                    'attachment_mime' => $attachment->attachment_mime,
                    'attachment_name' => $attachment->attachment_name,
                ]);
            }
        }

        $copy->loadMissing(['attachments']);

        return new MailerResource($copy);
    }
}
