<?php

/*
 * This file is part of the Laravel Paystack package.
 *
 * (c) Prosper Otemuyiwa <prosperotemuyiwa@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    /**
     * Public Key From Stripe Dashboard
     */
    'publicKey' => env('STRIPE_CONNECT_PUBLIC_KEY'),

    /**
     * Secret Key From Stripe Dashboard
     */
    'secretKey' => env('STRIPE_CONNECT_SECRET_KEY'),

    /**
     * Webhook Secret Key From Stripe Dashboard
     */
    'webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET_KEY'),

    /**
     * Connected Webhook Secret Key From Stripe Dashboard
     */
    'connected_webhook_secret' => env('STRIPE_CONNECT_CONNECTED_WEBHOOK_SECRET_KEY'),
];
