{{--
  SharePoint federated search — package-owned admin UI (issue #1221).

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio (AGPL-3.0-or-later).

  Degrades cleanly: when $configured is false it renders an honest
  "SharePoint not configured" panel instead of a search form.
--}}
@extends('theme::layouts.1col')
@section('title', __('SharePoint federated search'))
@section('content')
<h1>{{ __('SharePoint federated search') }}</h1>
<p class="lead text-muted">{{ __('Search live Microsoft 365 / SharePoint content via the Microsoft Graph search API.') }}</p>

@if (! $configured)
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
        <i class="fas fa-triangle-exclamation mt-1"></i>
        <div>
            <strong>{{ __('SharePoint is not configured on this instance.') }}</strong>
            <div class="small">
                {{ __('Add a Microsoft 365 tenant to enable federated search.') }}
                <a href="{{ route('sharepoint.tenants') }}">{{ __('Manage tenants') }}</a>.
            </div>
        </div>
    </div>
@else
    <form method="get" action="{{ route('sharepoint.federated-search') }}" class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="q" class="form-label">{{ __('Query') }}</label>
                    <input type="text" class="form-control" id="q" name="q"
                           value="{{ $query }}" placeholder="{{ __('Search SharePoint…') }}" autofocus>
                </div>
                @if (count($tenantOptions) > 1)
                    <div class="col-md-3">
                        <label for="tenant_id" class="form-label">{{ __('Tenant') }}</label>
                        <select class="form-select" id="tenant_id" name="tenant_id">
                            @foreach ($tenantOptions as $opt)
                                <option value="{{ $opt['id'] }}"
                                    {{ (int) request('tenant_id') === (int) $opt['id'] ? 'selected' : '' }}>
                                    {{ $opt['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-magnifying-glass me-1"></i>{{ __('Search') }}
                    </button>
                </div>
            </div>
        </div>
    </form>

    @if ($result !== null)
        @if ($result->state === \AhgSharePoint\Federation\SharePointFederationRunResult::STATE_FAILED)
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-circle-exclamation me-1"></i>
                {{ __('SharePoint search failed:') }} {{ $result->message }}
            </div>
        @elseif ($result->count() === 0)
            <div class="alert alert-info" role="alert">
                <i class="fas fa-circle-info me-1"></i>
                {{ __('No SharePoint results for') }} <strong>{{ $query }}</strong>.
            </div>
        @else
            <p class="text-muted small">
                {{ trans_choice('{1}:count result|[2,*]:count results', $result->count(), ['count' => $result->count()]) }}
            </p>
            <div class="list-group">
                @foreach ($result->results as $r)
                    <a href="{{ $r->url ?: '#' }}" target="_blank" rel="noopener"
                       class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">{{ $r->title }}</h6>
                            <span class="badge bg-secondary align-self-start">{{ $r->sourceBadge }}</span>
                        </div>
                        @if ($r->snippet)
                            <p class="mb-1 small text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($r->snippet), 280) }}</p>
                        @endif
                        @if ($r->date)
                            <small class="text-muted"><i class="far fa-clock me-1"></i>{{ $r->date }}</small>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    @endif
@endif
@endsection
