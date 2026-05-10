@extends('layouts.app')
@section('title', __('SharePoint user mappings'))
@section('content')
<h1>{{ __('SharePoint user mappings') }}</h1>
<p class="lead text-muted">{{ __('AAD object id → Heratio user id. Auto-created on first manual push (toggle in Admin > AHG Settings > SharePoint).') }}</p>

@if (count($mappings) === 0)
    <div class="alert alert-info">{{ __('No mappings yet.') }}</div>
@else
<table class="table table-striped">
    <thead><tr><th>ID</th><th>UPN</th><th>Email</th><th>{{ __('Heratio user') }}</th><th>{{ __('Created by') }}</th><th>{{ __('Last seen') }}</th><th></th></tr></thead>
    <tbody>
    @foreach ($mappings as $m)
        <tr>
            <td>{{ $m->id }}</td>
            <td class="small">{{ $m->aad_upn ?? '—' }}</td>
            <td class="small">{{ $m->aad_email ?? '—' }}</td>
            <td>{{ $m->atom_user_id }}</td>
            <td><span class="badge bg-secondary">{{ $m->created_by }}</span></td>
            <td>{{ $m->last_seen_at ?? '—' }}</td>
            <td><a class="btn btn-sm btn-outline-secondary" href="{{ route('sharepoint.user-mapping.edit', ['id' => $m->id]) }}">{{ __('Edit') }}</a></td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif
@endsection
