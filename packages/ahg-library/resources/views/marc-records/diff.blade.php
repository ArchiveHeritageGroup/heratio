@extends('theme::layouts.1col')
@section('title', __('Compare MARC record'))
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('library.marc-source', $libraryItemId) }}" class="btn btn-outline-secondary btn-sm me-3" aria-label="{{ __('Back to source record') }}"><i class="fas fa-arrow-left"></i></a>
        <h2 class="mb-0">{{ __('Compare MARC record') }} <small class="text-muted">#{{ $libraryItemId }}</small></h2>
    </div>

    @if(! $comparable)
        <div class="alert alert-warning">{{ __('These versions cannot be compared. Either no source is stored, or the source is a MARC-8 binary record that is kept as received and has no MARCXML form.') }}</div>
    @else
        <p>{{ __('Version :from compared with :to.', ['from' => $from->version, 'to' => $to ? __('version :v', ['v' => $to->version]) : __('the current catalogue record')]) }}
           {{ __(':count field(s) unchanged.', ['count' => $result['unchanged']]) }}</p>

        <h3 class="h5">{{ __('Only in version :v', ['v' => $from->version]) }} ({{ count($result['removed']) }})</h3>
        <pre class="border rounded p-2 small" style="white-space:pre-wrap">@forelse($result['removed'] as $line)- {{ $line }}
@empty{{ __('None') }}@endforelse</pre>

        <h3 class="h5">{{ $to ? __('Only in version :v', ['v' => $to->version]) : __('Only in the current record') }} ({{ count($result['added']) }})</h3>
        <pre class="border rounded p-2 small" style="white-space:pre-wrap">@forelse($result['added'] as $line)+ {{ $line }}
@empty{{ __('None') }}@endforelse</pre>
    @endif
</div>
@endsection
