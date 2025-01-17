<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\SendQueuedMailable;

class SendDispatchableMail extends SendQueuedMailable implements ShouldQueue
{
    use Dispatchable, Queueable;
}
