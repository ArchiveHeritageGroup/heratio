@extends('theme::layouts.1col')
@section('title', 'Scan inbox')

@section('content')
<h1>Scan inbox</h1>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('scan.dashboard') }}">Scan</a></li>
        <li class="breadcrumb-item active">Inbox</li>
    </ol>
</nav>

@if(session('notice'))
    <div class="alert alert-success">{{ session('notice') }}</div>
@endif

<form method="GET" class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">— All —</option>
                @foreach($statuses as $s)
                    <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Folder</label>
            <select name="folder" class="form-select form-select-sm">
                <option value="">— All —</option>
                @foreach($folders as $f)
                    <option value="{{ $f->code }}" {{ $folder === $f->code ? 'selected' : '' }}>{{ $f->label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Search filename</label>
            <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-sm btn-outline-secondary w-100">Filter</button>
        </div>
    </div>
</form>

@if($files->isEmpty())
    <p class="text-muted">No files match.</p>
@else
    <form method="POST" action="{{ route('scan.inbox.bulk') }}" id="bulkForm">
        @csrf
        @foreach(request()->except(['ids', 'action', '_token', 'page']) as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach

        <div class="d-flex justify-content-between align-items-center mb-2">
            <small class="text-muted">
                Showing {{ $files->firstItem() }}–{{ $files->lastItem() }} of {{ $files->total() }}
            </small>
            <div class="btn-group btn-group-sm">
                <button type="submit" name="action" value="retry" class="btn btn-outline-primary" data-confirm="Retry selected files?">
                    <i class="fas fa-redo me-1"></i>Retry selected
                </button>
                <button type="submit" name="action" value="discard" class="btn btn-outline-danger" data-confirm="Discard selected files?">
                    <i class="fas fa-ban me-1"></i>Discard selected
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th style="width:1%"><input type="checkbox" id="bulkSelectAll"></th>
                        <th>#</th>
                        <th>Folder</th>
                        <th>File</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Stage</th>
                        <th>IO</th>
                        <th>Attempts</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($files as $f)
                    <tr>
                        <td><input type="checkbox" class="bulk-item" name="ids[]" value="{{ $f->id }}"></td>
                        <td><a href="{{ route('scan.inbox.show', $f->id) }}">{{ $f->id }}</a></td>
                        <td><small><code>{{ $f->folder_code ?? '—' }}</code></small></td>
                        <td><small>{{ $f->original_name }}</small></td>
                        <td><small class="text-muted">{{ $f->file_size ? number_format($f->file_size / 1024, 0) . ' KB' : '—' }}</small></td>
                        <td>
                            @php $colors = ['pending'=>'secondary','processing'=>'primary','done'=>'success','failed'=>'danger','duplicate'=>'warning','quarantined'=>'warning','awaiting_rights'=>'info']; @endphp
                            <span class="badge bg-{{ $colors[$f->status] ?? 'secondary' }}">{{ $f->status }}</span>
                        </td>
                        <td><small class="text-muted">{{ $f->stage ?? '—' }}</small></td>
                        <td>
                            @if($f->resolved_io_id)
                                <a href="{{ url('/informationobject/' . $f->resolved_io_id) }}">#{{ $f->resolved_io_id }}</a>
                            @else
                                <small class="text-muted">—</small>
                            @endif
                        </td>
                        <td>{{ $f->attempts }}</td>
                        <td><small class="text-muted">{{ $f->created_at }}</small></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </form>

    <script>
    (function () {
        var all = document.getElementById('bulkSelectAll');
        if (!all) return;
        all.addEventListener('change', function () {
            document.querySelectorAll('.bulk-item').forEach(function (cb) { cb.checked = all.checked; });
        });
        document.querySelectorAll('#bulkForm button[data-confirm]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                var checked = document.querySelectorAll('.bulk-item:checked').length;
                if (checked === 0) { e.preventDefault(); alert('Select at least one file.'); return; }
                if (!confirm(btn.dataset.confirm + ' (' + checked + ' selected)')) { e.preventDefault(); }
            });
        });
    })();
    </script>

    {{ $files->links() }}
@endif
@endsection
