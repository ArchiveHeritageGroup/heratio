@extends('theme::layouts.1col')
@section('title', 'Web archive')

@section('content')
<h1>{{ __('Web archive') }}</h1>

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/admin') }}">{{ __('Admin') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Web archive') }}</li>
    </ol>
</nav>

@if(session('notice'))
    <div class="alert alert-success">{{ session('notice') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<p class="text-muted">
    {{ __('Single-page web capture to a WARC 1.1 file (ISO 28500). This captures one page, not a whole site: no crawl and no replay yet.') }}
</p>

@unless($installed)
    <div class="alert alert-warning">
        {{ __('The web-archive store is initialising. Reload this page in a moment to finish setup.') }}
    </div>
@endunless

<div class="card mb-4">
    <div class="card-header"><strong>{{ __('Capture a page') }}</strong></div>
    <div class="card-body">
        <form method="POST" action="{{ route('web-archive.store') }}" class="row g-2 align-items-start">
            @csrf
            <div class="col-md-9">
                <input type="url" name="url" class="form-control @error('url') is-invalid @enderror"
                       placeholder="https://example.org/page" value="{{ old('url') }}"
                       required {{ $installed ? '' : 'disabled' }}>
                @error('url')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('Only http/https URLs. Responses over 50 MB are skipped.') }}</small>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100" {{ $installed ? '' : 'disabled' }}>
                    <i class="fas fa-spider me-1"></i>{{ __('Capture') }}
                </button>
            </div>
        </form>
    </div>
</div>

<h4 class="mt-4">{{ __('Captures') }}</h4>
@if($captures->isEmpty())
    <p class="text-muted">{{ __('No captures yet. Submit a URL above to create the first one.') }}</p>
    <p class="text-muted"><small>{{ __('WARC files are written under') }} <code>{{ $storageHint }}</code>.</small></p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('URL') }}</th>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('HTTP') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Size') }}</th>
                    <th>{{ __('Captured') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($captures as $c)
                <tr>
                    <td><a href="{{ route('web-archive.show', $c->id) }}">{{ $c->id }}</a></td>
                    <td><small>{{ \Illuminate\Support\Str::limit($c->url, 60) }}</small></td>
                    <td><small>{{ $c->title ? \Illuminate\Support\Str::limit($c->title, 50) : '' }}</small></td>
                    <td>
                        @php $colors = ['captured'=>'success','failed'=>'danger','pending'=>'secondary']; @endphp
                        <span class="badge bg-{{ $colors[$c->status] ?? 'secondary' }}">{{ $c->status }}</span>
                    </td>
                    <td>{{ $c->http_status ?? '' }}</td>
                    <td><small class="text-muted">{{ $c->content_type ? \Illuminate\Support\Str::limit($c->content_type, 24) : '' }}</small></td>
                    <td><small class="text-muted">{{ $c->byte_size !== null ? number_format((int) $c->byte_size) : '' }}</small></td>
                    <td><small class="text-muted">{{ $c->captured_at ?? $c->created_at }}</small></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
