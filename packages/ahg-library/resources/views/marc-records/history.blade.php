@extends('theme::layouts.1col')
@section('title', __('Source MARC record'))
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('library.marc-edit', $libraryItemId) }}" class="btn btn-outline-secondary btn-sm me-3" aria-label="{{ __('Back to MARC Editor') }}"><i class="fas fa-arrow-left"></i></a>
        <h2 class="mb-0">{{ __('Source MARC record') }} <small class="text-muted">#{{ $libraryItemId }}</small></h2>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="mb-3">
        <a class="btn btn-sm btn-outline-primary" href="{{ route('library.marc-source-diff', $libraryItemId) }}">{{ __('Compare source with current record') }}</a>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('library.marc-source-replay', $libraryItemId) }}">{{ __('Replay field mapping') }}</a>
    </div>

    @if(empty($versions))
        <div class="alert alert-info">{{ __('No source record is stored for this item. It was catalogued before source records were kept, or has not been edited since.') }}</div>
    @else
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <caption>{{ __('Stored versions, newest first') }}</caption>
            <thead><tr><th scope="col">{{ __('Version') }}</th><th scope="col">{{ __('Event') }}</th><th scope="col">{{ __('Format') }}</th><th scope="col">{{ __('Exact source') }}</th><th scope="col">{{ __('Bytes') }}</th><th scope="col">{{ __('Date') }}</th><th scope="col">{{ __('Export') }}</th></tr></thead>
            <tbody>
            @foreach($versions as $v)
                <tr>
                    <td>{{ $v->version }}</td>
                    <td>{{ $eventLabels[$v->event] ?? $v->event }}</td>
                    <td>{{ $formatLabels[$v->source_format] ?? $v->source_format }}</td>
                    <td>{{ $v->is_verbatim ? __('Yes') : __('No') }}</td>
                    <td>{{ number_format($v->byte_length) }}</td>
                    <td>{{ $v->created_at }}</td>
                    <td class="text-nowrap">
                        @if($v->has_marcxml)<a href="{{ route('library.marc-source-download', [$libraryItemId, $v->version, 'xml']) }}">{{ __('MARCXML') }}</a>@endif
                        @if($v->has_binary)<a class="ms-2" href="{{ route('library.marc-source-download', [$libraryItemId, $v->version, 'binary']) }}">{{ __('Original bytes') }}</a>@endif
                        @if($v->has_marcxml)<a class="ms-2" href="{{ route('library.marc-source-diff', [$libraryItemId, 'from' => $v->version]) }}">{{ __('Compare') }}</a>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
