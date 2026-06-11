{{--
  Union catalogue - admin: member registry + opt-in sharing config (#1203).

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio (AGPL-3.0-or-later).
--}}
@extends('theme::layout')

@section('title', __('Federation members'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">
                <i class="bi bi-diagram-3 me-2"></i>{{ __('Federation members') }}
            </h4>
            <p class="text-muted mb-0">
                {{ __('Participating institutions in the union catalogue, plus this institution\'s opt-in sharing settings.') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('union.catalogue') }}" class="atom-btn-white" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Open union catalogue') }}
            </a>
            <a href="{{ route('union.members.add') }}" class="atom-btn-white">
                <i class="bi bi-plus-lg me-1"></i>{{ __('Add member') }}
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('publishOutput'))
        <pre class="bg-light border rounded p-2 small">{{ session('publishOutput') }}</pre>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4 col-sm-6">
            <div class="card h-100"><div class="card-body text-center">
                <h2 class="mb-0" style="color: var(--ahg-primary);">{{ count($members) }}</h2>
                <p class="text-muted mb-0">{{ __('Registered members') }}</p>
            </div></div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card h-100"><div class="card-body text-center">
                <h2 class="mb-0 text-info">{{ number_format($unionCount) }}</h2>
                <p class="text-muted mb-0">{{ __('Records in union index') }}</p>
            </div></div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="card h-100"><div class="card-body text-center">
                <h2 class="mb-0 {{ (int) ($share->share_enabled ?? 0) ? 'text-success' : 'text-warning' }}">
                    {{ (int) ($share->share_enabled ?? 0) ? __('ON') : __('OFF') }}
                </h2>
                <p class="text-muted mb-0">{{ __('This institution\'s sharing') }}</p>
            </div></div>
        </div>
    </div>

    {{-- Opt-in sharing config --}}
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-toggles me-1"></i>{{ __('Opt-in sharing (what this institution publishes)') }}
        </div>
        <div class="card-body">
            @if (! $self)
                <div class="alert alert-warning mb-3">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    {{ __('No self-member is registered. Add a member and tick "This institution" so publish knows who owns the shared records.') }}
                </div>
            @endif
            <form method="POST" action="{{ route('union.members.share') }}">
                @csrf
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="share_enabled" name="share_enabled" value="1"
                           {{ (int) ($share->share_enabled ?? 0) ? 'checked' : '' }}>
                    <label class="form-check-label" for="share_enabled">
                        {{ __('Share this institution\'s records into the union catalogue') }}
                        <span class="text-muted small">({{ __('default OFF') }})</span>
                    </label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox"
                           id="published_only" name="published_only" value="1"
                           {{ (int) ($share->published_only ?? 1) ? 'checked' : '' }}>
                    <label class="form-check-label" for="published_only">
                        {{ __('Only share records with publication status "Published"') }}
                    </label>
                </div>
                <div class="mb-3">
                    <label for="min_level_id" class="form-label">
                        {{ __('Minimum level-of-description term id (optional)') }}
                    </label>
                    <input type="number" class="form-control" style="max-width: 220px;"
                           id="min_level_id" name="min_level_id"
                           value="{{ $share->min_level_id ?? '' }}"
                           placeholder="{{ __('leave blank for no level gate') }}">
                </div>
                <button type="submit" class="atom-btn-white">
                    <i class="bi bi-save me-1"></i>{{ __('Save sharing settings') }}
                </button>
            </form>
        </div>
    </div>

    {{-- Publish trigger --}}
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-cloud-upload me-1"></i>{{ __('Publish into union index') }}
        </div>
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <p class="text-muted mb-0 flex-grow-1">
                {{ __('Runs ahg:federation-publish - pushes this institution\'s opt-in, published records into the union index. Respects the opt-in switch above.') }}
            </p>
            <form method="POST" action="{{ route('union.members.publish') }}" class="d-inline">
                @csrf
                <button type="submit" class="atom-btn-white">
                    <i class="bi bi-arrow-clockwise me-1"></i>{{ __('Publish now') }}
                </button>
            </form>
        </div>
    </div>

    {{-- Member registry --}}
    <div class="card">
        <div class="card-header">
            <i class="bi bi-people me-1"></i>{{ __('Member registry') }}
        </div>
        <div class="card-body p-0">
            @if (empty($members))
                <p class="text-muted m-3 mb-3">
                    {{ __('No members registered yet. Add this institution as the self-member, then add peers.') }}
                </p>
            @else
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Base URL') }}</th>
                            <th>{{ __('Contact') }}</th>
                            <th>{{ __('Self') }}</th>
                            <th>{{ __('Enabled') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $m)
                            <tr>
                                <td>{{ $m->name }}</td>
                                <td class="small text-muted">{{ $m->base_url ?: '-' }}</td>
                                <td class="small text-muted">{{ $m->contact ?: '-' }}</td>
                                <td>
                                    @if ((int) $m->is_self)
                                        <span class="badge bg-primary">{{ __('This institution') }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if ((int) $m->is_enabled)
                                        <span class="badge bg-success">{{ __('Enabled') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ __('Off') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('union.members.edit', $m->id) }}"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('union.members.delete', $m->id) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('{{ __('Remove this member and its shared records?') }}');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
