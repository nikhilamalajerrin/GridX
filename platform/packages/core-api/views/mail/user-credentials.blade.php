<x-mail-layout>
<h2 style="font-size: 18px; font-weight: 600;">
@if($currentHour < 12)
    Good Morning, {{ \GridX\Support\Utils::delinkify($user->name) }}!
@elseif($currentHour < 18)
    Good Afternoon, {{ \GridX\Support\Utils::delinkify($user->name) }}!
@else
    Good Evening, {{ \GridX\Support\Utils::delinkify($user->name) }}!
@endif
</h2>

Your login credentials:
<br />
<br />
Your Email: {{ $user->email }}
<br />
Your Password: {{ $plaintextPassword }}
<br />
Console URL: {{ \GridX\Support\Utils::consoleUrl() }}
</x-mail-layout>
