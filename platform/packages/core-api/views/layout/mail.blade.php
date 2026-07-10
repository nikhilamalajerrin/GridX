<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <tr>
            <td class="header">
                <a href="{{ \GridX\Support\Utils::consoleUrl() }}" style="display: inline-block;">
                    <img src="{{ \GridX\Models\Setting::getBrandingLogoUrl() }}" alt="{{ config('app.name') }} Logo" class="logo" style="height: 35px; width: 200px; max-height: 60px; max-width: 250px;">
                </a>
            </td>
        </tr>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
    <x-slot:subcopy>
        <x-mail::subcopy>
            {{ $subcopy }}
        </x-mail::subcopy>
    </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ config('app.name') }}. @lang('All Rights Reserved.')
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>