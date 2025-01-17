<?php

namespace App\Jobs;

use ExpoSDK\Exceptions\ExpoException;
use ExpoSDK\Exceptions\InvalidTokensException;
use ExpoSDK\Expo;
use ExpoSDK\ExpoMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?string $title, public string $content, public int $badge, public array $tokens)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @throws ExpoException
     * @throws InvalidTokensException
     */
    public function handle(): void
    {

        if (empty($this->tokens)) {
            return;
        }

        if (! app()->environment('production')) {
            $this->tokens = ['ExponentPushToken[]'];
        }

        $message = (new ExpoMessage())
            ->setTitle($this->title)
            ->setBody($this->content)
            ->playSound();

        if ($this->badge) {
            $message->setBadge($this->badge);
        }

        (new Expo())->send($message)->to($this->tokens)->push();
    }
}
