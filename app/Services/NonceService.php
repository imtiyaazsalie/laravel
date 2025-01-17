<?php

namespace App\Services;

use Hidehalo\Nanoid\Client;

class NonceService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function generateNonce($invoiceId): string
    {
        return $invoiceId.'-'.time().'-'.$this->client->formattedId('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 21);
    }

    public function verifyNonce($nonce): bool
    {
        // Split the nonce using our delimiter - and check if the count equals 4
        $split = explode('-', $nonce);

        if (count($split) !== 3) {
            return false;
        }

        // Wonderful, Nonce has proven to be valid
        return true;
    }

    public function getInvoiceIdFromNonce($nonce): ?int
    {
        if (! $this->verifyNonce($nonce)) {
            return null;
        }

        $split = explode('-', $nonce);

        return (int) $split[0];
    }
}
