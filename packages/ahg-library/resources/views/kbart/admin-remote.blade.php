@extends('theme::layouts.1col')
@section('title', 'KBART Remote Feeds')

@section('content')
<div class="container py-4">

    {{-- Header + back link --}}
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <a href="{{ route('library.kbart') }}" class="text-decoration-none small mb-2 d-inline-block">
                <i class="fas fa-arrow-left me-1"></i>KBART Knowledge Base
            </a>
            <h1 class="h3 mb-1">
                <i class="fas fa-globe me-2"></i>KBART Remote Feeds
            </h1>
            <p class="text-muted small mb-0">
                Automated scheduled import from remote KBART TSV endpoints.
                Feeds are fetched daily and records are upserted into the library catalogue.
            </p>
        </div>
        <div>
            <span class="badge bg-{{ $auto_import_enabled ? 'success' : 'secondary' }} me-2">
                Auto-import {{ $auto_import_enabled ? 'enabled' : 'disabled' }}
            </span>
            <a href="{{ route('library.kbart-remote-create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i>Add Feed
            </a>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}</div>
    @endif

    @if($feeds->isEmpty())
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fas fa-rss fa-3x mb-3 opacity-25"></i>
                <p class="mb-2">No KBART feeds configured yet.</p>
                <a href="{{ route('library.kbart-remote-create') }}" class="btn btn-primary btn-sm">
                    Add your first feed
                </a>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Feed name</th>
                            <th>URL</th>
                            <th>Vendor</th>
                            <th>Active</th>
                            <th>Last fetch</th>
                            <th>Rows</th>
                            <th>Status</th>
                            <th style="width:180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($feeds as $feed)
                            <tr>
                                <td>
                                    <a href="{{ route('library.kbart-remote-edit', $feed->id) }}"
                                       class="text-decoration-none fw-semibold">
                                        {{ $feed->name }}
                                    </a>
                                </td>
                                <td class="small text-break" style="max-width:200px;">
                                    <a href="{{ $feed->url }}" target="_blank"
                                       class="text-muted text-decoration-none"
                                       title="{{ $feed->url }}">
                                        {{ \Illuminate\Support\Str::limit($feed->url, 40) }}
                                    </a>
                                </td>
                                <td>{{ $feed->vendor ?: '—' }}</td>
                                <td>
                                    @if($feed->active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if($feed->last_fetch_at)
                                        {{ \Carbon\Carbon::parse($feed->last_fetch_at)->diffForHumans() }}
                                    @else
                                        <span class="text-muted">Never</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($feed->last_fetch_at)
                                        {{ number_format($feed->last_row_count) }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($feed->last_fetch_at)
                                        @if($feed->last_fetch_status === 'success')
                                            <span class="badge bg-success" title="{{ $feed->last_error ?: 'OK' }}">
                                                <i class="fas fa-check me-1"></i>OK
                                            </span>
                                        @elseif($feed->last_fetch_status === 'fail')
                                            <span class="badge bg-danger" title="{{ $feed->last_error }}">
                                                <i class="fas fa-times me-1"></i>Fail
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">
                                                {{ $feed->last_fetch_status }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- Refresh now --}}
                                    <form action="{{ route('library.kbart-remote-refresh', $feed->id) }}"
                                          method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success btn-sm py-0 px-1"
                                                title="Fetch now">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>

                                    {{-- Toggle active --}}
                                    <form action="{{ route('library.kbart-remote-toggle', $feed->id) }}"
                                          method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1"
                                                title="{{ $feed->active ? 'Deactivate' : 'Activate' }}">
                                            <i class="fas fa-toggle-{{ $feed->active ? 'on' : 'off' }}"></i>
                                        </button>
                                    </form>

                                    {{-- Edit --}}
                                    <a href="{{ route('library.kbart-remote-edit', $feed->id) }}"
                                       class="btn btn-outline-primary btn-sm py-0 px-1" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>

                                    {{-- Delete --}}
                                    <form action="{{ route('library.kbart-remote-destroy', $feed->id) }}"
                                          method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete feed \'{{ $feed->name }}\'?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            @if($feed->last_error)
                                <tr class="table-danger">
                                    <td colspan="8" class="small text-danger ps-4">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Last error: {{ $feed->last_error }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Schedule info --}}
        <div class="alert alert-info mt-3 mb-0 small">
            <i class="fas fa-clock me-1"></i>
            Feeds run daily at <strong>01:00</strong> via <code>ahg:library-kbart-refresh</code>.
            The master switch <strong>library_kbart_auto_import_enabled</strong> in
            <a href="/admin/ahgSettings" class="alert-link">AHG Settings</a>
            disables all scheduled runs without deleting feed subscriptions.
        </div>
    @endif
</div>
@endsection
