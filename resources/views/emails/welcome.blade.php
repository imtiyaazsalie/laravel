<strong> Hi {{ $memberName }},</strong>

Your account has been created on Octiv for {{ $locationName }} who uses Octiv to manage their facility.

@if($isMember)

To log in to your account:

@if($isSignUp)
- Download the Octiv app for [iOS](https://apps.apple.com/za/app/octiv/id1533669918), [Android](https://play.google.com/store/apps/details?id=com.octiv.mobile), [Huawei](https://appgallery.huawei.com/#/app/C103840907)
- Log in by using the password you created when signing up and the email address {{ $email }}.
@else
- Create a password for your account by following [this link*]({{ $passwordResetLink }}) using the email address {{ $email }}
    - *Please note that this link will be valid for 24 hours - after which you can use the forgot password link on the [login page]({{ config('octiv.web_app_url') }})
- Then, download the Octiv app for [iOS](https://apps.apple.com/za/app/octiv/id1533669918), [Android](https://play.google.com/store/apps/details?id=com.octiv.mobile), [Huawei](https://appgallery.huawei.com/#/app/C103840907)
@endif

Once logged in you will be able to:

- View your facility's schedule and book your classes.
- Track your fitness and see when you're making progress.
- Track injuries, payments, interact with peers and so much more.

@else
To log in to your account create a password by following [this link*]({{ $passwordResetLink }}) using the email address {{ $email }}

*Please note that this link will be valid for 24 hours - after which you can use the forgot password link on the [login page]({{ config('octiv.web_app_url') }})
@endif
