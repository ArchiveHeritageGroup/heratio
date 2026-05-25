@extends('emails._layout', ['subject' => __('DOI minted')])

@section('content')
    <h2 style="margin-top:0;">{{ __('DOI minted successfully') }}</h2>

    <p>{{ __('A DOI has been minted for:') }}</p>

    <p><strong>{{ $ctx['title'] ?? '' }}</strong></p>

    <ul>
        <li><strong>DOI:</strong> <code>{{ $ctx['doi'] ?? '' }}</code></li>
        @if (! empty($ctx['resolver_url']))
            <li><strong>{{ __('Resolver') }}:</strong> <a href="{{ $ctx['resolver_url'] }}">{{ $ctx['resolver_url'] }}</a></li>
        @endif
    </ul>

    @if (! empty($ctx['object_url']))
        <p style="text-align: center; margin: 24px 0;">
            <a href="{{ $ctx['object_url'] }}" class="btn">{{ __('Open record') }}</a>
        </p>
    @endif

    <p class="muted">{{ __('The DOI is now publicly resolvable and can be cited in scholarly works.') }}</p>
@endsection
