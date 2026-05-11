@extends('theme::layouts.1col')

@section('title', 'SharePoint Auto-Ingest Rules')

@section('content')
<h1>{{ __('SharePoint Auto-Ingest Rules') }}</h1>

@if(session('notice'))
<div class="alert alert-success alert-dismissible fade show">
    {{ session('notice') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<p>
    <a href="{{ route('sharepoint.rule.edit') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i>{{ __('New rule') }}
    </a>
    <a href="{{ route('sharepoint.mappings') }}" class="btn btn-outline-secondary">{{ __('Mapping templates') }}</a>
</p>

@if($drives->isEmpty())
    <div class="alert alert-info">
        {{ __('Register a SharePoint drive before creating rules.') }}
        <a href="{{ route('sharepoint.drives') }}">{{ __('Drives') }}</a>.
    </div>
@endif

<table class="table table-striped">
    <thead>
        <tr>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Drive') }}</th>
            <th>{{ __('Folder / Pattern') }}</th>
            <th>{{ __('Schedule') }}</th>
            <th>{{ __('Enabled') }}</th>
            <th>{{ __('Last run') }}</th>
            <th>{{ __('Ingested') }}</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    @foreach($rules as $r)
        <tr>
            <td><strong>{{ $r->name }}</strong></td>
            <td><small>{{ $r->site_title ?: '?' }}</small><br><code>{{ $r->drive_name ?: '?' }}</code></td>
            <td><code>{{ $r->folder_path ?: '/' }}</code><br><small class="text-muted">{{ $r->file_pattern ?: '*' }}</small></td>
            <td><code>{{ $r->schedule_cron }}</code></td>
            <td>
                @if($r->is_enabled)
                    <span class="badge bg-success">{{ __('Enabled') }}</span>
                @else
                    <span class="badge bg-secondary">{{ __('Disabled') }}</span>
                @endif
            </td>
            <td>
                @if($r->last_run_at)
                    <small>{{ $r->last_run_at }}</small><br>
                    <span class="badge bg-{{ in_array($r->last_run_status, ['ok','dry_run']) ? 'success' : ($r->last_run_status === 'error' ? 'danger' : 'secondary') }}">{{ $r->last_run_status }}</span>
                @else
                    <span class="text-muted">{{ __('never') }}</span>
                @endif
            </td>
            <td>{{ (int) $r->items_ingested }}</td>
            <td class="text-end">
                <a href="{{ route('sharepoint.rule.edit', ['id' => $r->id]) }}" class="btn btn-sm btn-outline-secondary">{{ __('Edit') }}</a>
                <form method="post" action="{{ route('sharepoint.rule.run', ['id' => $r->id]) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Run now') }}</button>
                </form>
                <form method="post" action="{{ route('sharepoint.rule.delete', ['id' => $r->id]) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this rule?') }}')">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                </form>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
