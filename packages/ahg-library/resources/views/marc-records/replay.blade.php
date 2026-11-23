@extends('theme::layouts.1col')
@section('title', __('Replay field mapping'))
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('library.marc-source', $libraryItemId) }}" class="btn btn-outline-secondary btn-sm me-3" aria-label="{{ __('Back to source record') }}"><i class="fas fa-arrow-left"></i></a>
        <h2 class="mb-0">{{ __('Replay field mapping') }} <small class="text-muted">#{{ $libraryItemId }}</small></h2>
    </div>
    @error('columns')<div class="alert alert-danger">{{ $message }}</div>@enderror

    @if(! $source)
        <div class="alert alert-info">{{ __('No imported source record is stored for this item, so there is nothing to replay.') }}</div>
    @elseif(! $replayable)
        <div class="alert alert-warning">{{ __('The stored source record could not be read as MARC, so it cannot be replayed. It is kept unchanged and can still be downloaded from the history page.') }}</div>
    @elseif(empty($fields))
        <div class="alert alert-info">{{ __('The mapping produces no values from this source record.') }}</div>
    @else
    @php
        $statusLabels = [
            'same' => __('Same'),
            'fill' => __('Empty, can be filled'),
            'conflict' => __('Differs from catalogue'),
            'unusable' => __('Cannot be stored'),
        ];
    @endphp
    <p>{{ __('Values below come from imported version :v under the current mapping. Nothing changes until you apply. Fields that differ are unticked so that a cataloguer\'s correction is kept unless you choose otherwise.', ['v' => $source->version]) }}</p>
    <form method="POST" action="{{ route('library.marc-source-replay-apply', $libraryItemId) }}">
        @csrf
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <caption>{{ __('Mapped values compared with the catalogue') }}</caption>
                <thead><tr><th scope="col">{{ __('Apply') }}</th><th scope="col">{{ __('Field') }}</th><th scope="col">{{ __('Catalogue') }}</th><th scope="col">{{ __('From source') }}</th><th scope="col">{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach($fields as $f)
                    <tr>
                        <td>@if(in_array($f['status'], ['fill', 'conflict']))<input class="form-check-input" type="checkbox" name="columns[]" value="{{ $f['column'] }}" aria-label="{{ __('Apply :field', ['field' => $f['column']]) }}" @checked($f['status'] === 'fill')>@endif</td>
                        <td><code>{{ $f['column'] }}</code></td>
                        <td>{{ $f['current'] }}</td>
                        <td>{{ $f['mapped'] }}</td>
                        <td>{{ $statusLabels[$f['status']] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn btn-success">{{ __('Apply selected') }}</button>
    </form>
    @endif
</div>
@endsection
