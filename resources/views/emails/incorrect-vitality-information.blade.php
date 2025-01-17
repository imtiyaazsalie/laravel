# Hi {{ $user->full_name }},

We noticed that your Discovery Vitality information on Octiv may be incorrect. Please ensure that your RSA ID/Passport Number and date of birth are
correct as you will not earn Vitality points until you do so. If your information is correct please email us at support@octivfitness.com so that
we can look into it further.

RSA ID/Passport Number: {{ $user->id_number ?? 'N/A' }}<br>
Date of Birth: {{ $user->date_of_birth ? $user->date_of_birth->format('Y-m-d') : 'N/A' }}
