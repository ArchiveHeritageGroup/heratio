@extends('theme::layouts.1col')
@section('title', 'Scan — Watched folders')

@section('content')
<h1>{{ __('Watched scan folders') }}</h1>

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('scan.dashboard') }}">Scan</a></li>
        <li class="breadcrumb-item active">Watched folders</li>
    </ol>
</nav>

@if(session('notice'))
    <div class="alert alert-success">{{ session('notice') }}</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Directories that Heratio watches for new scan files. Each folder is backed by a persistent ingest session.</p>
    <div>
        <a href="{{ route('scan.dashboard') }}" class="btn btn-outline-secondary me-2">
            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
        </a>
        <a href="{{ route('scan.folders.create') }}" class="btn btn-outline-secondary">
            <i class="fas fa-plus me-1"></i>New watched folder
        </a>
    </div>
</div>

@if(empty($folders) || count($folders) === 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">{{ __('No watched folders') }}</h5>
            <p class="text-muted">Create a watched folder to auto-ingest scanned material.</p>
            <a href="{{ route('scan.folders.create') }}" class="btn btn-outline-secondary">
                <i class="fas fa-plus me-1"></i>New watched folder
            </a>
        </div>
    </div>
@else
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Label') }}</th>
                    <th>{{ __('Path') }}</th>
                    <th>{{ __('Sector') }}</th>
                    <th>{{ __('Standard') }}</th>
                    <th>{{ __('Layout') }}</th>
                    <th>{{ __('Enabled') }}</th>
                    <th>{{ __('Last scan') }}</th>
                    <th style="width:1%">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($folders as $f)
                <tr>
                    <td><code>{{ $f->code }}</code></td>
                    <td>{{ $f->label }}</td>
                    <td><small class="text-muted">{{ $f->path }}</small></td>
                    <td>{{ ucfirst($f->sector ?? '—') }}</td>
                    <td><small>{{ strtoupper($f->standard ?? '—') }}</small></td>
                    <td><small>{{ $f->layout }}</small></td>
                    <td>
                        @if($f->enabled)
                            <span class="badge bg-success">On</span>
                        @else
                            <span class="badge bg-secondary">Off</span>
                        @endif
                    </td>
                    <td><small class="text-muted">{{ $f->last_scanned_at ?? 'never' }}</small></td>
                    <td class="text-nowrap">
                        <form action="{{ route('scan.folders.run', $f->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" title="{{ __('Run a scan pass now') }}">
                                <i class="fas fa-play"></i>
                            </button>
                        </form>
                        <a href="{{ route('scan.folders.edit', $f->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('scan.folders.destroy', $f->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this watched folder? Historical ingest records are retained.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
