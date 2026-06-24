@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'workspaces'])@endsection
@section('title', 'Workspace Files')

@php
    // Human-readable byte formatter (kept inline so the view is self-contained).
    $humanBytes = function ($bytes) {
        $bytes = (int) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) { $n /= 1024; $i++; }
        return round($n, $i === 0 ? 0 : 1) . ' ' . $units[$i];
    };
    $usage = (int) ($storage['usage'] ?? 0);
    $limit = $storage['limit'] ?? null;
    $pct = (float) ($storage['pct'] ?? 0);
    $barClass = $pct >= 100 ? 'bg-danger' : ($pct >= 80 ? 'bg-warning' : 'bg-success');
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Workspace Files') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show">{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-folder-open text-primary me-2"></i>{{ __('Workspace Files') }}</h1>
</div>

@if(!empty($workspace))
    <p class="text-muted mb-3"><i class="bi bi-collection me-1"></i>{{ e($workspace->name ?? ('Workspace #' . $workspaceId)) }}</p>
@endif

{{-- Storage usage bar --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold"><i class="bi bi-hdd me-1"></i>{{ __('Storage usage') }}</span>
            <span class="text-muted small">
                {{ $humanBytes($usage) }}
                @if($limit !== null)
                    {{ __('of') }} {{ $humanBytes($limit) }} ({{ $pct }}%)
                @else
                    ({{ __('no limit') }})
                @endif
            </span>
        </div>
        @if($limit !== null)
            <div class="progress" style="height: 12px;" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar {{ $barClass }}" style="width: {{ min($pct, 100) }}%;"></div>
            </div>
        @else
            <div class="progress" style="height: 12px;" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-success" style="width: 3%;"></div>
            </div>
        @endif
    </div>
</div>

{{-- Upload form --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('research.workspace.files.store', $workspaceId) }}" enctype="multipart/form-data">
            @csrf
            <label class="form-label fw-semibold"><i class="fas fa-upload me-1"></i>{{ __('Upload a file') }}</label>
            <div class="input-group">
                <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" required>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>{{ __('Upload') }}</button>
            </div>
            @error('file')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </form>
    </div>
</div>

{{-- Files table --}}
@if(count($files) > 0)
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Size') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Uploaded') }}</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            @foreach($files as $file)
                <tr>
                    <td><i class="bi bi-file-earmark me-1 text-muted"></i>{{ e($file->file_name) }}</td>
                    <td>{{ $humanBytes($file->file_size) }}</td>
                    <td><span class="text-muted small">{{ e($file->mime_type ?: '-') }}</span></td>
                    <td>{{ $file->created_at ? date('M j, Y H:i', strtotime($file->created_at)) : '-' }}</td>
                    <td class="text-end">
                        <a href="{{ route('research.workspace.files.download', [$workspaceId, $file->id]) }}" class="btn btn-sm btn-outline-primary" title="{{ __('Download') }}"><i class="fas fa-download"></i></a>
                        @if(!empty($isOwner))
                        <form method="POST" action="{{ route('research.workspace.files.destroy', [$workspaceId, $file->id]) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this file?') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
        <h5>{{ __('No files yet') }}</h5>
        <p class="text-muted">{{ __('Upload files to attach them to this workspace.') }}</p>
    </div>
</div>
@endif
@endsection
