<?php

namespace App\Traits;

use App\Models\Notifications;

trait SeedsNotifications
{
    private function upsertNotification(
        string $context,
        string $name,
        string $content,
        string $description,
        string $subject,
        string $status = 'disabled',
        string $type = 'editable',
    ) {
        Notifications::updateOrCreate(
            [
                'box_id' => null,
                'system_context' => $context,
            ],
            [
                'name' => $name,
                'description' => $description,
                'system_type' => $type,
                'status' => $status,
                'emailSubject' => $subject,
                'emailContent' => $content,
            ]
        );
    }
}
