@extends('theme::layouts.1col')
@section('title', 'Scan inbox — file #' . $file->id)

@section('content')
<h1>Scan file #{{ $file->id }}</h1>

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('scan.dashboard') }}">Scan</a></li>
        <li class="breadcrumb-item"><a href="{{ route('scan.inbox.index') }}">Inbox</a></li>
        <li class="breadcrumb-item active">{{ $file->original_name }}</li>
    </ol>
</nav>

@if(session('notice'))
    <div class="alert alert-success">{{ session('notice') }}</div>
@endif

<div class="row">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header"><strong>File</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Original name</dt><dd class="col-sm-8">{{ $file->original_name }}</dd>
                    <dt class="col-sm-4">Stored path</dt><dd class="col-sm-8"><code>{{ $file->stored_path }}</code></dd>
                    <dt class="col-sm-4">File size</dt><dd class="col-sm-8">{{ $file->file_size ? number_format($file->file_size) . ' bytes' : '—' }}</dd>
                    <dt class="col-sm-4">MIME</dt><dd class="col-sm-8">{{ $file->mime_type ?? '—' }}</dd>
                    <dt class="col-sm-4">SHA-256</dt><dd class="col-sm-8"><small><code>{{ $file->source_hash ?? '—' }}</code></small></dd>
                    <dt class="col-sm-4">Folder</dt><dd class="col-sm-8"><code>{{ $file->folder_code ?? '—' }}</code> ({{ $file->folder_label ?? '—' }})</dd>
                    <dt class="col-sm-4">Session</dt><dd class="col-sm-8">{{ $file->session_title ?? '—' }} — sector {{ $file->sector ?? '—' }}, standard {{ $file->standard ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        @if($file->resolved_io_id)
        <div class="card mb-3">
            <div class="card-header"><strong>Resulting information object</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">IO #</dt><dd class="col-sm-8">{{ $file->resolved_io_id }}</dd>
                    <dt class="col-sm-4">Title</dt><dd class="col-sm-8">{{ $file->io_title ?? '—' }}</dd>
                    <dt class="col-sm-4">Slug</dt><dd class="col-sm-8">
                        @if($file->io_slug)
                            <a href="{{ url('/' . $file->io_slug) }}">{{ $file->io_slug }}</a>
                        @else — @endif
                    </dd>
                    <dt class="col-sm-4">Digital object #</dt><dd class="col-sm-8">{{ $file->resolved_do_id ?? '—' }}</dd>
                </dl>
            </div>
        </div>
        @endif

        @if($file->error_message)
        <div class="card mb-3 border-danger">
            <div class="card-header bg-danger text-white"><strong>Last error</strong></div>
            <div class="card-body">
                <pre class="mb-0" style="white-space: pre-wrap">{{ $file->error_message }}</pre>
            </div>
        </div>
        @endif
    </div>

    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><strong>Status</strong></div>
            <div class="card-body">
                @php $colors = ['pending'=>'secondary','processing'=>'primary','done'=>'success','failed'=>'danger','duplicate'=>'warning','quarantined'=>'warning']; @endphp
                <p><span class="badge bg-{{ $colors[$file->status] ?? 'secondary' }} fs-6">{{ $file->status }}</span></p>
                <dl class="row mb-0 small">
                    <dt class="col-sm-5">Stage</dt><dd class="col-sm-7">{{ $file->stage ?? '—' }}</dd>
                    <dt class="col-sm-5">Attempts</dt><dd class="col-sm-7">{{ $file->attempts }}</dd>
                    <dt class="col-sm-5">Created</dt><dd class="col-sm-7">{{ $file->created_at }}</dd>
                    <dt class="col-sm-5">Completed</dt><dd class="col-sm-7">{{ $file->completed_at ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Actions</strong></div>
            <div class="card-body d-grid gap-2">
                @if(in_array($file->status, ['failed', 'pending']))
                    <form method="POST" action="{{ route('scan.inbox.retry', $file->id) }}">
                        @csrf
                        <button class="btn btn-outline-primary w-100">
                            <i class="fas fa-redo me-1"></i>Retry now
                        </button>
                    </form>
                @endif
                @if($file->status === 'quarantined')
                    <form method="POST" action="{{ route('scan.inbox.restore', $file->id) }}" onsubmit="return confirm('Restore this file from quarantine and re-dispatch for ingest?')">
                        @csrf
                        <button class="btn btn-outline-warning w-100">
                            <i class="fas fa-box-open me-1"></i>Restore from quarantine
                        </button>
                    </form>
                @endif
                @if($file->status === 'awaiting_rights')
                    <form method="POST" action="{{ route('scan.inbox.releaseRights', $file->id) }}" onsubmit="return confirm('Confirm rights have been reviewed and resume the pipeline for this file?')">
                        @csrf
                        <button class="btn btn-success w-100">
                            <i class="fas fa-user-shield me-1"></i>Release rights + resume
                        </button>
                    </form>
                @endif
                @if(!in_array($file->status, ['done', 'duplicate']))
                    <form method="POST" action="{{ route('scan.inbox.discard', $file->id) }}" onsubmit="return confirm('Discard this file?')">
                        @csrf
                        <button class="btn btn-outline-danger w-100">
                            <i class="fas fa-ban me-1"></i>Discard
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
