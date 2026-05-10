@extends('layouts.app')
@section('title', __('SharePoint inbound events'))
@section('content')
<h1>{{ __('SharePoint inbound events') }}</h1>

<form method="get" class="mb-3">
    <label for="status" class="me-2">{{ __('Filter') }}:</label>
    <select name="status" id="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
        <option value="">{{ __('All') }}</option>
        @foreach (['received','queued','processing','completed','failed','skipped_duplicate','skipped_not_allowlisted'] as $s)
            <option value="{{ $s }}" @selected($statusFilter === $s)>{{ $s }}</option>
        @endforeach
    </select>
</form>

@php
$map = [
    'received' => 'secondary', 'queued' => 'info', 'processing' => 'primary',
    'completed' => 'success', 'failed' => 'danger',
    'skipped_duplicate' => 'warning', 'skipped_not_allowlisted' => 'warning',
];
@endphp
<table class="table table-sm table-striped">
    <thead>
        <tr><th>ID</th><th>{{ __('Received') }}</th><th>{{ __('Drive') }}</th><th>{{ __('Item') }}</th><th>{{ __('Change') }}</th><th>{{ __('Status') }}</th><th>{{ __('Attempts') }}</th><th>IO</th><th></th></tr>
    </thead>
    <tbody>
    @foreach ($events as $ev)
        <tr>
            <td>{{ $ev->id }}</td>
            <td>{{ $ev->received_at }}</td>
            <td>{{ $ev->drive_id }}</td>
            <td class="small text-muted">{{ $ev->sp_item_id ?? '—' }}</td>
            <td>{{ $ev->change_type }}</td>
            <td><span class="badge bg-{{ $map[$ev->status] ?? 'secondary' }}">{{ $ev->status }}</span></td>
            <td>{{ $ev->attempts }}</td>
            <td>{{ $ev->information_object_id ?? '—' }}</td>
            <td><a href="{{ route('sharepoint.events.detail', ['id' => $ev->id]) }}" class="btn btn-sm btn-outline-secondary">{{ __('Detail') }}</a></td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
