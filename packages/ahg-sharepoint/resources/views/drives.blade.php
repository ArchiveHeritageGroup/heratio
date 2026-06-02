@extends('theme::layouts.1col')
@section('title', __('SharePoint drives'))
@section('content')
<h1>{{ __('SharePoint drives') }}</h1>
<p class="lead text-muted">{{ __('Document libraries linked from connected tenants. Enable ingest to pull their content into Heratio.') }}</p>

@if ($drives->isEmpty())
    <div class="alert alert-info">
        {{ __('No drives linked yet. Add a tenant first, then browse its sites to link a document library.') }}
    </div>
@else
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>{{ __('Site') }}</th>
                <th>{{ __('Drive') }}</th>
                <th>{{ __('Tenant') }}</th>
                <th>{{ __('Sector') }}</th>
                <th>{{ __('Ingest') }}</th>
                <th>{{ __('Last sync') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @foreach ($drives as $d)
            <tr>
                <td class="fw-semibold">{{ $d->site_title ?: $d->site_url }}</td>
                <td>{{ $d->drive_name ?? '—' }}</td>
                <td class="small text-muted">{{ $d->tenant_name ?? '—' }}</td>
                <td><span class="badge bg-secondary">{{ $d->sector }}</span></td>
                <td>
                    @if ($d->ingest_enabled)
                        <span class="badge bg-success">{{ __('Enabled') }}</span>
                    @else
                        <span class="badge bg-light text-dark border">{{ __('Disabled') }}</span>
                    @endif
                </td>
                <td class="small text-muted">{{ $d->last_full_sync_at ?? '—' }}</td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('sharepoint.drives.mapping', ['id' => $d->id]) }}">{{ __('Mapping') }}</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
@endsection
