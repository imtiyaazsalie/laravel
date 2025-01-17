<?php

return [
    'partner_entity_number' => env('DISCOVERY_PARTNER_ENTITY_NUMBER', 1),

    'directories' => [
        'internal' => [
            'workout' => 'Discovery-batch-files/workout/',
            'servicing' => 'Discovery-batch-files/servicing-workout/',
            'recon' => 'Discovery-batch-files/monthly-recon/',
        ],
        'external' => [
            'workout' => env('DISCOVERY_SFTP_SSH_WORKOUT_FOLDER', 'Discovery-batch-files/workout/'),
            'servicing' => env('DISCOVERY_SFTP_SSH_SERVICING_FOLDER', 'Discovery-batch-files/servicing-workout/'),
            'recon' => env('DISCOVERY_SFTP_SSH_RECON_FOLDER', 'Discovery-batch-files/monthly-recon/'),
        ],
    ],
];
