<?php

namespace App\Services\CRM;

use App\Enums\MailerRecipientType;
use App\Models\LocationUser;
use App\Models\Mailer;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Arr;
use RuntimeException;

class MailerService
{
    public function addMailerRecipients(Mailer $mailer, array $groupedRecipients): void
    {
        // remove existing recipients.
        $mailer->recipients()->delete();

        // data to create.
        $data = [];

        // add new recipients
        foreach ($groupedRecipients as $category => $recipients) {

            switch ($category) {
                case 'members':
                    $data = array_merge($data, array_map(fn ($id) => [
                        'ref_entity_id' => $id,
                        'ref_entity_name' => (new User())->getTable(),
                        'type' => MailerRecipientType::MEMBER,
                        'name' => null,
                        'email' => null,
                        'mobile' => null,
                    ], $recipients));

                    break;
                case 'staff':
                    $data = array_merge($data, array_map(fn ($id) => [
                        'ref_entity_id' => $id,
                        'ref_entity_name' => (new User())->getTable(),
                        'type' => MailerRecipientType::COACH,
                        'name' => null,
                        'email' => null,
                        'mobile' => null,
                    ], $recipients));

                    break;
                case 'leads':
                    $data = array_merge($data, array_map(fn ($id) => [
                        'ref_entity_id' => $id,
                        'ref_entity_name' => (new User())->getTable(),
                        'type' => MailerRecipientType::LEAD_MEMBER,
                        'name' => null,
                        'email' => null,
                        'mobile' => null,
                    ], $recipients));
                    break;
                case 'non_members':
                    $data = array_merge($data, array_map(fn ($recipient) => [
                        'ref_entity_id' => null,
                        'ref_entity_name' => null,
                        'type' => MailerRecipientType::NON_MEMBER,
                        'name' => Arr::get($recipient, 'name'),
                        'email' => Arr::get($recipient, 'email'),
                        'mobile' => Arr::get($recipient, 'mobile'),
                    ], $recipients));
                    break;
                default:
                    throw new RuntimeException('Invalid category for mailer recipients.');
            }
        }

        // get member data for cleanup
        $memberIds = collect($data)->where('ref_entity_name', (new User())->getTable())->pluck('ref_entity_id')->toArray();

        $cleanUsers = $this->cleanupRecipients($mailer, $memberIds);

        $cleanUserSet = array_flip($cleanUsers);

        // filter Users against cleanUserSet and ignore non_members
        $updatedData = collect($data)->filter(function ($item) use ($cleanUserSet) {
            if (! isset($item['ref_entity_name'])) {
                return true;
            }

            if ($item['ref_entity_name'] === (new User())->getTable()) {
                return isset($cleanUserSet[$item['ref_entity_id']]);
            }
        })->toArray();

        $mailer->recipients()->createMany($updatedData);
    }

    public function cleanupRecipients(Mailer $mailer, ?array $recipientUserIds = null): array
    {
        if (! $recipientUserIds) {
            $recipientUserIds = $mailer->recipients->pluck('user.user_id')->filter()->unique()->toArray();
        }

        $locationUsers = [];
        if ($mailer->location_id) {
            $locationUsers = LocationUser::query()
                ->where('box_facility_id', $mailer->location_id)
                ->whereIn('user_id', $recipientUserIds)
                ->pluck('user_id')
                ->toArray();
        }

        $cleanUsers = TenantUser::query()
            ->where('box_id', $mailer->box_id)
            ->whereIn('user_to_box.user_id', $locationUsers ?: $recipientUserIds)
            ->pluck('user_to_box.user_id')
            ->unique()
            ->toArray();

        return $cleanUsers;
    }
}
