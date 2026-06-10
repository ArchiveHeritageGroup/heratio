@extends('theme::layouts.1col')
@section('title', 'Scan dashboard')

@section('content')
<h1>{{ __('Scan') }}</h1>

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Admin</a></li>
        <li class="breadcrumb-item active">Scan</li>
    </ol>
</nav>

@if(session('notice'))
    <div class="alert alert-success">{{ session('notice') }}</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Live view of watched folders and the ingest pipeline.</p>
    <div>
        <a href="{{ route('scan.folders.index') }}" class="btn btn-outline-secondary me-2">
            <i class="fas fa-folder-open me-1"></i>{{ __('Watched folders') }}
        </a>
        <a href="{{ route('scan.inbox.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-inbox me-1"></i>{{ __('Inbox') }}
        </a>
    </div>
</div>

<div class="row mb-4">
    @php $cards = [
        ['pending', 'Pending', '#6c757d', 'clock'],
        ['processing', 'Processing', '#0d6efd', 'spinner'],
        ['done', 'Done', '#198754', 'check-circle'],
        ['failed', 'Failed', '#dc3545', 'exclamation-triangle'],
        ['duplicate', 'Duplicate', '#ffc107', 'clone'],
        ['quarantined', 'Quarantined', '#fd7e14', 'shield-virus'],
        ['awaiting_rights', 'Awaiting rights', '#6f42c1', 'user-shield'],
    ]; @endphp
    @foreach($cards as $c)
        <div class="col-md-2 mb-3">
            <a href="{{ route('scan.inbox.index', ['status' => $c[0]]) }}" class="text-decoration-none">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-{{ $c[3] }}" style="color: {{ $c[2] }}; font-size: 1.5rem;"></i>
                        <h3 class="mt-2 mb-0">{{ number_format($counts[$c[0]] ?? 0) }}</h3>
                        <small class="text-muted">{{ $c[1] }}</small>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted mb-1">{{ __('Last 24 hours') }}</h6>
                <h3 class="mb-0">{{ number_format($last24h) }} arrival(s)</h3>
                <small class="text-muted">{{ number_format($doneLast24h) }} completed</small>
            </div>
        </div>
    </div>
</div>

<h4 class="mt-4">{{ __('Per-folder throughput') }}</h4>
@if($folders->isEmpty())
    <p class="text-muted">No watched folders yet. <a href="{{ route('scan.folders.create') }}">Add one</a>.</p>
@else
    <div class="table-responsive mb-4">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Label') }}</th>
                    <th>{{ __('Enabled') }}</th>
                    <th>{{ __('Pending') }}</th>
                    <th>{{ __('Failed') }}</th>
                    <th>{{ __('Done') }}</th>
                    <th>{{ __('Last successful ingest') }}</th>
                    <th>{{ __('Last scan') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($folders as $f)
                <tr>
                    <td><code>{{ $f->code }}</code></td>
                    <td><a href="{{ route('scan.inbox.index', ['folder' => $f->code]) }}">{{ $f->label }}</a></td>
                    <td>@if($f->enabled)<span class="badge bg-success">On</span>@else<span class="badge bg-secondary">{{ __('Off') }}</span>@endif</td>
                    <td>{{ number_format($f->pending ?? 0) }}</td>
                    <td>{{ number_format($f->failed ?? 0) }}</td>
                    <td>{{ number_format($f->done ?? 0) }}</td>
                    <td><small class="text-muted">{{ $f->last_done ?? '—' }}</small></td>
                    <td><small class="text-muted">{{ $f->last_scanned_at ?? '—' }}</small></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<h4 class="mt-4">{{ __('Recent activity') }}</h4>
@if($recent->isEmpty())
    <p class="text-muted">No files in the pipeline yet.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Folder') }}</th>
                    <th>{{ __('File') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Stage') }}</th>
                    <th>{{ __('IO') }}</th>
                    <th>{{ __('Created') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recent as $r)
                <tr>
                    <td><a href="{{ route('scan.inbox.show', $r->id) }}">{{ $r->id }}</a></td>
                    <td><small><code>{{ $r->folder_code ?? '—' }}</code></small></td>
                    <td><small>{{ $r->original_name }}</small></td>
                    <td>
                        @php $colors = ['pending'=>'secondary','processing'=>'primary','done'=>'success','failed'=>'danger','duplicate'=>'warning','quarantined'=>'warning']; @endphp
                        <span class="badge bg-{{ $colors[$r->status] ?? 'secondary' }}">{{ $r->status }}</span>
                    </td>
                    <td><small class="text-muted">{{ $r->stage ?? '—' }}</small></td>
                    <td>
                        @if($r->resolved_io_id)
                            <a href="{{ url('/informationobject/' . $r->resolved_io_id) }}">IO #{{ $r->resolved_io_id }}</a>
                        @else
                            <small class="text-muted">—</small>
                        @endif
                    </td>
                    <td><small class="text-muted">{{ $r->created_at }}</small></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
