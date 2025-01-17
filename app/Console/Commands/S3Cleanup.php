<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

use function Livewire\invade;

class S3Cleanup extends Command
{
    protected $signature = 'app:configure-s3';

    protected $description = 'Configure S3 to automatically cleanup files older than 24hrs';

    public function handle()
    {

        $driver = Storage::disk('tmp')->getDriver();

        // Flysystem V2+ doesn't allow direct access to adapter, so we need to invade instead.
        $adapter = invade($driver)->adapter;

        // Flysystem V2+ doesn't allow direct access to client, so we need to invade instead.
        $client = invade($adapter)->client;

        // Flysystem V2+ doesn't allow direct access to bucket, so we need to invade instead.
        $bucket = invade($adapter)->bucket;

        $client->putBucketLifecycleConfiguration([
            'Bucket' => $bucket,
            'LifecycleConfiguration' => [
                'Rules' => [
                    [
                        'Prefix' => 'tmp/',
                        'Expiration' => [
                            'Days' => 1,
                        ],
                        'Status' => 'Enabled',
                    ],
                ],
            ],
        ]);

        $this->info('Temporary S3 upload directory [tmp/] set to automatically cleanup files older than 24hrs!');
    }
}
