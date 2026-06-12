@extends('theme::layouts.1col')
@section('title', 'Web archive capture #' . $capture->id)

@section('content')
<h1>{{ __('Capture') }} #{{ $capture->id }}</h1>

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/admin') }}">{{ __('Admin') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('web-archive.index') }}">{{ __('Web archive') }}</a></li>
        <li class="breadcrumb-item active">#{{ $capture->id }}</li>
    </ol>
</nav>

@if(session('notice'))
    <div class="alert alert-success">{{ session('notice') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Capture') }}</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">{{ __('URL') }}</dt>
                    <dd class="col-sm-9"><a href="{{ $capture->url }}" target="_blank" rel="noopener noreferrer">{{ $capture->url }}</a></dd>

                    <dt class="col-sm-3">{{ __('Title') }}</dt>
                    <dd class="col-sm-9">{{ $capture->title ?: '' }}</dd>

                    <dt class="col-sm-3">{{ __('Status') }}</dt>
                    <dd class="col-sm-9">
                        @php $colors = ['captured'=>'success','failed'=>'danger','pending'=>'secondary']; @endphp
                        <span class="badge bg-{{ $colors[$capture->status] ?? 'secondary' }}">{{ $capture->status }}</span>
                    </dd>

                    <dt class="col-sm-3">{{ __('HTTP status') }}</dt>
                    <dd class="col-sm-9">{{ $capture->http_status ?? '' }}</dd>

                    <dt class="col-sm-3">{{ __('Content type') }}</dt>
                    <dd class="col-sm-9">{{ $capture->content_type ?: '' }}</dd>

                    <dt class="col-sm-3">{{ __('Size') }}</dt>
                    <dd class="col-sm-9">{{ $capture->byte_size !== null ? number_format((int) $capture->byte_size) . ' ' . __('bytes') : '' }}</dd>

                    <dt class="col-sm-3">{{ __('WARC path') }}</dt>
                    <dd class="col-sm-9"><small><code>{{ $capture->warc_path ?: '' }}</code></small></dd>

                    <dt class="col-sm-3">{{ __('Captured at') }}</dt>
                    <dd class="col-sm-9">{{ $capture->captured_at ?? '' }}</dd>

                    <dt class="col-sm-3">{{ __('Created at') }}</dt>
                    <dd class="col-sm-9">{{ $capture->created_at }}</dd>
                </dl>
            </div>
        </div>

        @if($capture->status === 'failed' && $capture->error)
            <div class="alert alert-danger">
                <strong>{{ __('Error') }}:</strong> {{ $capture->error }}
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('WARC records') }}</strong></div>
            <div class="card-body">
                @if(! $warcExists)
                    <p class="text-muted mb-0">{{ __('No WARC file on disk for this capture.') }}</p>
                @elseif(empty($warcHeaders))
                    <p class="text-muted mb-0">{{ __('WARC file present but no record headers could be read.') }}</p>
                @else
                    @foreach($warcHeaders as $i => $rec)
                        <h6 class="text-muted">{{ __('Record') }} {{ $i + 1 }}: {{ $rec['WARC-Type'] ?? '' }}</h6>
                        <table class="table table-sm mb-3">
                            <tbody>
                                @foreach($rec as $name => $value)
                                <tr>
                                    <th class="text-nowrap" style="width: 30%;"><small>{{ $name }}</small></th>
                                    <td><small><code>{{ \Illuminate\Support\Str::limit($value, 120) }}</code></small></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Actions') }}</strong></div>
            <div class="card-body">
                @if($warcExists && $capture->status === 'captured')
                    <a href="{{ route('web-archive.replay', $capture->id) }}" target="_blank" rel="noopener"
                       class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-clock-rotate-left me-1"></i>{{ __('Replay snapshot') }}
                    </a>
                @endif
                @if($warcExists)
                    <a href="{{ route('web-archive.download', $capture->id) }}" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fas fa-download me-1"></i>{{ __('Download WARC') }}
                    </a>
                @endif
                <a href="{{ route('web-archive.index') }}" class="btn btn-outline-secondary w-100">
                    {{ __('Back to list') }}
                </a>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-0"><small>
                    {{ __('Replay serves the archived page document back from its stored WARC, with a clear archived-snapshot banner. It is a single-document replay: embedded resources (images, CSS, scripts) and links are not replayed, and nothing live is fetched. Multi-resource replay is planned. The WARC file remains a standards-conformant capture you can open in any WARC-aware tool.') }}
                </small></p>
            </div>
        </div>
    </div>
</div>
@endsection
