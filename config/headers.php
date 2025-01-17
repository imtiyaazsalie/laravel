<?php

declare(strict_types=1);

return [
    'remove' => [
        'X-Powered-By',
        'x-powered-by',
        'Server',
        'server',
    ],

    'referrer-policy' => 'no-referrer-when-downgrade',

    'strict-transport-security' => 'max-age=31536000; includeSubDomains',

    'certificate-transparency' => 'enforce, max-age=30',

    'permissions-policy' => 'autoplay=(), camera=(), encrypted-media=(), fullscreen=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), usb=()',

    'content-type-options' => 'nosniff',
];
