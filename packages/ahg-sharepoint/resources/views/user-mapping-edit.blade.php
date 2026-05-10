@extends('layouts.app')
@section('title', __('SharePoint user mapping'))
@section('content')
<h1>{{ __('SharePoint user mapping') }}</h1>

@if ($mapping !== null)
    <dl class="row">
        <dt class="col-sm-3">AAD oid</dt><dd class="col-sm-9 small text-muted">{{ $mapping->aad_object_id }}</dd>
        <dt class="col-sm-3">UPN</dt><dd class="col-sm-9">{{ $mapping->aad_upn ?? '—' }}</dd>
        <dt class="col-sm-3">Email</dt><dd class="col-sm-9">{{ $mapping->aad_email ?? '—' }}</dd>
        <dt class="col-sm-3">{{ __('Heratio user id') }}</dt><dd class="col-sm-9">{{ $mapping->atom_user_id }}</dd>
        <dt class="col-sm-3">{{ __('Created by') }}</dt><dd class="col-sm-9">{{ $mapping->created_by }}</dd>
        <dt class="col-sm-3">{{ __('Last seen') }}</dt><dd class="col-sm-9">{{ $mapping->last_seen_at ?? '—' }}</dd>
    </dl>
    <form method="post" onsubmit="return confirm('{{ __('Delete this mapping? The Heratio user account is NOT deleted.') }}');">
        @csrf
        <input type="hidden" name="form_action" value="delete">
        <button type="submit" class="btn btn-danger">{{ __('Remove mapping') }}</button>
        <a class="btn btn-link" href="{{ route('sharepoint.user-mappings') }}">{{ __('Back') }}</a>
    </form>
@else
    <div class="alert alert-warning">{{ __('Mapping not found.') }}</div>
@endif
@endsection
