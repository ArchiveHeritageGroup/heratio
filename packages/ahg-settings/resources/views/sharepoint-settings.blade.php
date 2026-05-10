@extends('layouts.app')
@section('title', __('SharePoint Integration'))
@section('content')
<div class="container-fluid">
    @include('ahg-settings::_ahg-menu', ['menu' => $menu])

    <div class="row">
        <div class="col-md-12">
            <h1><i class="fa fa-cloud me-2"></i>{{ __('SharePoint Integration') }}</h1>
            <p class="text-muted">{{ __('Microsoft 365 SharePoint: tenant, drives, webhook URL, retention map, push.') }}</p>

            @if (session('notice'))
                <div class="alert alert-success">{{ session('notice') }}</div>
            @endif

            <form method="post" class="mt-3">
                @csrf

                <div class="form-check form-switch mb-2">
                    <input type="checkbox" class="form-check-input" id="sharepoint_enabled" name="sharepoint_enabled" @checked(($settings['sharepoint_enabled'] ?? 'false') === 'true')>
                    <label class="form-check-label" for="sharepoint_enabled">{{ __('SharePoint integration enabled') }}</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input type="checkbox" class="form-check-input" id="sharepoint_records_handoff_enabled" name="sharepoint_records_handoff_enabled" @checked(($settings['sharepoint_records_handoff_enabled'] ?? 'false') === 'true')>
                    <label class="form-check-label" for="sharepoint_records_handoff_enabled">{{ __('Records handoff (auto/declare) — Phase 2.A') }}</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input type="checkbox" class="form-check-input" id="sharepoint_push_user_create_enabled" name="sharepoint_push_user_create_enabled" @checked(($settings['sharepoint_push_user_create_enabled'] ?? 'true') === 'true')>
                    <label class="form-check-label" for="sharepoint_push_user_create_enabled">{{ __('Auto-create Heratio user on first manual push (Phase 2.B)') }}</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input type="checkbox" class="form-check-input" id="sharepoint_federated_search_enabled" name="sharepoint_federated_search_enabled" @checked(($settings['sharepoint_federated_search_enabled'] ?? 'false') === 'true')>
                    <label class="form-check-label" for="sharepoint_federated_search_enabled">{{ __('Federated search tab in Heratio (Phase 3)') }}</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="checkbox" class="form-check-input" id="sharepoint_m365_search_enabled" name="sharepoint_m365_search_enabled" @checked(($settings['sharepoint_m365_search_enabled'] ?? 'false') === 'true')>
                    <label class="form-check-label" for="sharepoint_m365_search_enabled">{{ __('Microsoft Search connector feed (Phase 3)') }}</label>
                </div>

                <div class="form-group mb-3">
                    <label for="webhook_public_url">{{ __('Public webhook URL (for Graph subscriptions)') }}</label>
                    <input type="url" class="form-control" id="webhook_public_url" name="webhook_public_url" value="{{ $settings['webhook_public_url'] ?? '' }}" placeholder="https://psis.theahg.co.za/sharepoint/webhook">
                    <small class="form-text text-muted">{{ __('Must be HTTPS, publicly reachable. Graph posts notifications here.') }}</small>
                </div>

                <div class="form-group mb-3">
                    <label for="retention_label_map">{{ __('Retention label → Heratio disposition map (JSON)') }}</label>
                    <textarea class="form-control font-monospace" id="retention_label_map" name="retention_label_map" rows="6" placeholder='{"Archive-Permanent":{"level_of_description_id":12,"parent_id":345}}'>{{ $settings['retention_label_map'] ?? '' }}</textarea>
                    <small class="form-text text-muted">{{ __('Per-tenant JSON object keyed by Purview compliance tag.') }}</small>
                </div>

                <div class="alert alert-info mb-3">
                    <i class="fa fa-info-circle me-2"></i>{{ __('Tenant credentials and per-drive auto-ingest label allowlists are managed at') }}
                    <a href="{{ route('sharepoint.tenants') }}">{{ __('SharePoint admin') }}</a>.
                </div>

                <button type="submit" class="btn btn-primary">{{ __('Save settings') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
