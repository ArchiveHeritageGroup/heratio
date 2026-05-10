@extends('layouts.app')
@section('title', __('SharePoint webhook subscriptions'))
@section('content')
<h1>{{ __('SharePoint webhook subscriptions') }}</h1>

<p class="lead text-muted">{{ __('Two subscriptions per ingest-enabled drive: driveItem (content) + list (metadata, including retention labels).') }}</p>

@if ($subscriptions->isEmpty())
    <div class="alert alert-info">
        {{ __('No active subscriptions. Run') }} <code>php artisan sharepoint:subscribe --drive=&lt;id&gt;</code> {{ __('to create one.') }}
    </div>
@else
    <table class="table table-striped">
        <thead>
            <tr><th>{{ __('Drive') }}</th><th>{{ __('Resource') }}</th><th>{{ __('Status') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Renewed') }}</th><th>{{ __('Subscription ID') }}</th></tr>
        </thead>
        <tbody>
        @foreach ($subscriptions as $sub)
            @php $cls = $sub->status === 'active' ? 'success' : ($sub->status === 'error' ? 'danger' : 'secondary'); @endphp
            <tr>
                <td>{{ $sub->drive_id }}</td>
                <td><code>{{ $sub->resource }}</code></td>
                <td><span class="badge bg-{{ $cls }}">{{ $sub->status }}</span></td>
                <td>{{ $sub->expires_at }}</td>
                <td>{{ $sub->last_renewed_at ?? '—' }}</td>
                <td class="small text-muted">{{ $sub->subscription_id }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
@endsection
