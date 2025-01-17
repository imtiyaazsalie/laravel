<?php

namespace App\Notifications;

use Carbon\CarbonInterval;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class ResetPassword extends \Illuminate\Auth\Notifications\ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        $interval = CarbonInterval::minutes(config('auth.passwords.'.config('auth.defaults.passwords').'.expire'))->cascade();

        if ($interval->d == 1 && $interval->hours == 0 && $interval->minutes == 0) {
            $expiryHumanReadableTime = $interval->totalHours.' hours';
        } else {
            $expiryHumanReadableTime = $interval->forHumans(['parts' => 2, 'join' => true]);
        }

        return (new MailMessage)
            ->subject(Lang::get('Reset Password Notification'))
            ->line(Lang::get('You are receiving this email because we received a password reset request for your account.'))
            ->action(Lang::get('Reset Password'), $url)
            ->line(Lang::get('This password reset link will expire in :expiryHumanReadableTime.', ['expiryHumanReadableTime' => $expiryHumanReadableTime]))
            ->line(Lang::get('If you did not request a password reset, no further action is required.'));
    }
}
