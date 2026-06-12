{{--
  Web archive capture detail (admin). heratio#1244.

  Per-capture detail over the single warc_capture table: the row metadata (mode, target
  URI, on-disk .warc path + fixity sha256, http status, outcome), the parsed WARC record
  headers, and the replay / download actions. Replay serves the archived page back entirely
  from the stored WARC (never the live site). Bootstrap 5 + central theme.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Web archive capture') . ' #' . $capture->id)

@section('content')
@php
  $mode = $capture->mode ?? 'record';
@endphp
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
                    <dt class="col-sm-3">{{ __('Mode') }}</dt>
                    <dd class="col-sm-9">
                        @if($mode === 'url')
                            <span class="badge bg-light text-dark border"><i class="fas fa-link me-1"></i>{{ __('Submitted URL') }}</span>
                        @else
                            <span class="badge bg-light text-dark border"><i class="fas fa-file-lines me-1"></i>{{ __('Record page') }}</span>
                        @endif
                    </dd>

                    @if($mode === 'record' && $capture->slug)
                        <dt class="col-sm-3">{{ __('Record') }}</dt>
                        <dd class="col-sm-9"><a href="{{ url('/'.ltrim($capture->slug, '/')) }}" target="_blank" rel="noopener">{{ $capture->slug }}</a></dd>
                    @endif

                    <dt class="col-sm-3">{{ __('Target URI') }}</dt>
                    <dd class="col-sm-9"><a href="{{ $capture->target_uri }}" target="_blank" rel="noopener noreferrer">{{ $capture->target_uri }}</a></dd>

                    <dt class="col-sm-3">{{ __('Status') }}</dt>
                    <dd class="col-sm-9">
                        @php $colors = ['captured'=>'success','failed'=>'danger']; @endphp
                        <span class="badge bg-{{ $colors[$capture->status] ?? 'secondary' }}">{{ __(ucfirst($capture->status)) }}</span>
                    </dd>

                    <dt class="col-sm-3">{{ __('HTTP status') }}</dt>
                    <dd class="col-sm-9">{{ $capture->http_status ?? '' }}</dd>

                    <dt class="col-sm-3">{{ __('Size') }}</dt>
                    <dd class="col-sm-9">{{ $capture->byte_size !== null ? number_format((int) $capture->byte_size) . ' ' . __('bytes') : '' }}</dd>

                    <dt class="col-sm-3">{{ __('Fixity (sha256)') }}</dt>
                    <dd class="col-sm-9"><small><code>{{ $capture->sha256 ?: '' }}</code></small></dd>

                    <dt class="col-sm-3">{{ __('WARC path') }}</dt>
                    <dd class="col-sm-9"><small><code>{{ $capture->file_path ?: '' }}</code></small></dd>

                    <dt class="col-sm-3">{{ __('Captured at') }}</dt>
                    <dd class="col-sm-9">{{ $capture->captured_at ?? '' }}</dd>
                </dl>
            </div>
        </div>

        @if($capture->status === 'failed' && $capture->error_message)
            <div class="alert alert-danger">
                <strong>{{ __('Error') }}:</strong> {{ $capture->error_message }}
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
                    {{ __('Replay serves the archived page back entirely from its stored WARC, with a clear archived-snapshot banner and a restrictive content-security policy. Same-host subresources captured into the WARC are rewritten to load from the archive; off-host resources were never captured and do not load. Nothing live is fetched. The WARC file remains a standards-conformant capture you can open in any WARC-aware tool.') }}
                </small></p>
            </div>
        </div>
    </div>
</div>
@endsection
