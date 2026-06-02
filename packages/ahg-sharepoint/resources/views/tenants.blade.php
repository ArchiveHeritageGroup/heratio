@extends('theme::layouts.1col')
@section('title', __('SharePoint tenants'))
@section('content')
<h1>{{ __('SharePoint tenants') }}</h1>
<p class="lead text-muted">{{ __('Azure AD app registrations Heratio uses to reach Microsoft Graph.') }}</p>

@if ($tenants->isEmpty())
    <div class="alert alert-info">
        {{ __('No tenants configured yet. Register an Azure AD app and add its tenant/client IDs to connect a Microsoft 365 environment.') }}
    </div>
@else
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Tenant ID') }}</th>
                <th>{{ __('Client ID') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Last token') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @foreach ($tenants as $t)
            @php $cls = $t->status === 'active' ? 'success' : ($t->status === 'error' ? 'danger' : 'secondary'); @endphp
            <tr>
                <td class="fw-semibold">{{ $t->name }}</td>
                <td class="small font-monospace">{{ $t->tenant_id }}</td>
                <td class="small font-monospace">{{ $t->client_id }}</td>
                <td><span class="badge bg-{{ $cls }}">{{ ucfirst($t->status) }}</span></td>
                <td class="small text-muted">{{ $t->last_token_at ?? '—' }}</td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('sharepoint.tenant.edit', ['id' => $t->id]) }}">{{ __('Edit') }}</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
@endsection
