<?php

return [
    'web_app_url' => env('WEB_APP_URL', 'http://localhost:3000'),
    'encryption_key' => env('ENCRYPTION_KEY', \Illuminate\Support\Str::random()),
    'gym_super_admin_role' => 'Head Coach',

    'stripe' => [
        'secret_key' => env('OCTIV_STRIPE_SK', 'sk_test_51KLmYZD0S5LgP7fWX3xPVLoomcG0Uj9CnzqnainbYJ4L3UHlvywdLYZaQK18QJ9cpbLjmjdTWnWfZiLMx8q42tPJ009TMo50NF'),
        'public_key' => env('OCTIV_STRIPE_PK', 'pk_test_51KLmYZD0S5LgP7fWFqljWZ5fQZyxoEP1iZp0qILX2ht4xr5iuDeSJqzS71sOlCTheqAZG6krizsFofOTBcnl3JZj00WEOwRxgT'),
    ],

    'emails' => [
        'noreply' => 'noreply@octivfitness.com',
        'tech' => 'tech@octivfitness.com',
        'support' => 'support@octivfitness.com',
    ],

    'vpn_ips' => explode(',', env('VPN_IPS', '')),
];
