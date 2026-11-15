<x-mail::layout>
{{-- En-tête : logo de l'entreprise (URL publique par UUID), sinon son nom --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
@if (! empty($companyLogoUrl))
<img src="{{ $companyLogoUrl }}" alt="{{ $companyName }}" style="max-height: 64px; max-width: 200px; border: 0;">
@else
{{ $companyName }}
@endif
</x-mail::header>
</x-slot:header>

@if (! empty($greeting))
# {{ $greeting }}
@endif

@foreach ($introLines as $line)
{{ $line }}

@endforeach

@isset($actionText)
<x-mail::button :url="$actionUrl" color="primary">
{{ $actionText }}
</x-mail::button>
@endisset

@foreach ($outroLines as $line)
{{ $line }}

@endforeach

@if (! empty($salutation))
{{ $salutation }}
@endif

@isset($actionText)
<x-slot:subcopy>
<x-mail::subcopy>
Si le bouton « {{ $actionText }} » ne fonctionne pas, copiez ce lien dans votre navigateur : <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} DocPilot — e-mail envoyé pour le compte de {{ $companyName }}.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
