<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordForgetRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Services\CrmService;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordsController extends Controller
{
    public function forget(PasswordForgetRequest $request)
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
                    ? response()->noContent()
                    : response()->errorMessage(__($status));
    }

    public function reset(PasswordResetRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'locked' => 0,
                ]);

                $user->save();

                $content = Markdown::parse(
                    view('emails.password-changed', [
                        'memberName' => $user->name,
                        'memberSurname' => $user->surname,
                    ])
                );

                (new CrmService())->createScheduledEmail(
                    content: $content,
                    subject: 'Password Updated',
                    to: $user->email,
                );
            }
        );

        return $status === Password::PASSWORD_RESET
                    ? response()->noContent()
                    : response()->errorMessage(__($status));
    }
}
