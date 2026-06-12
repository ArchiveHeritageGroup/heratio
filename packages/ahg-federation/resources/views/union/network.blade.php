{{--
  Federated GLAM network - public directory of participating institutions
  (#1203 slice).

  A public roll of the enabled member institutions: name, what each shares
  (the share-scope), how many discovery records it contributes to the union
  index, a link to the union catalogue filtered to that member, and a link to
  the member's own site. The local institution (self-member) is highlighted.
  The network-effects story: the more institutions participate, the richer the
  shared memory. Full-width public layout. Empty-state when nobody has opted
  in yet; never 500s.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio (AGPL-3.0-or-later).
--}}
@extends('theme::layouts.1col')

@section('title', __('GLAM network directory'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-diagram-3 me-2"></i>{{ __('GLAM network directory') }}
            </h4>
            <p class="text-muted mb-0">
                {{ __('The galleries, libraries, archives and museums that share their collections through this federation. Every institution that joins makes the shared memory richer for everyone.') }}
            </p>
        </div>
        {{-- #1203 join-request slice: invite new institutions to join. --}}
        <a href="{{ url('/federation/join') }}" class="btn btn-primary">
            <i class="bi bi-envelope-plus me-1"></i>{{ __('Join the network') }}
        </a>
    </div>

    @if ($directory['memberCount'] === 0)
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            {{ __('No participating institutions yet. When institutions opt in, they appear here and their shared records become searchable across the network.') }}
            <a href="{{ url('/federation/join') }}" class="alert-link">{{ __('Be the first to join.') }}</a>
        </div>
    @else
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="display-6 fw-bold">{{ number_format($directory['memberCount']) }}</div>
                        <div class="text-muted">
                            {{ trans_choice('{1}participating institution|[2,*]participating institutions', $directory['memberCount']) }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="display-6 fw-bold">{{ number_format($directory['recordCount']) }}</div>
                        <div class="text-muted">{{ __('shared discovery records') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-12 col-lg-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <a href="{{ url('/union-catalogue') }}" class="btn btn-primary mb-2">
                            <i class="bi bi-search me-1"></i>{{ __('Search the union catalogue') }}
                        </a>
                        <a href="{{ url('/federation/network.json') }}"
                           class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                            <i class="bi bi-braces me-1"></i>{{ __('Directory as JSON') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            @foreach ($directory['members'] as $member)
                <div class="col-12 col-lg-6">
                    <div class="card h-100 {{ $member->is_self ? 'border-primary' : '' }}">
                        <div class="card-header d-flex align-items-center flex-wrap gap-2">
                            <i class="bi bi-building me-1"></i>
                            <strong>{{ $member->name }}</strong>
                            @if ($member->is_self)
                                <span class="badge bg-primary">
                                    <i class="bi bi-house-door me-1"></i>{{ __('This institution') }}
                                </span>
                            @endif
                            <span class="badge bg-light text-dark ms-auto">
                                {{ trans_choice('{0}no shared records|{1}:count shared record|[2,*]:count shared records', $member->record_count, ['count' => number_format($member->record_count)]) }}
                            </span>
                        </div>
                        <div class="card-body">
                            @if ($member->share_scope)
                                <p class="mb-2">{{ $member->share_scope }}</p>
                            @else
                                <p class="mb-2 text-muted fst-italic">
                                    {{ __('This institution has not described what it shares yet.') }}
                                </p>
                            @endif

                            @if ($member->contact)
                                <p class="small text-muted mb-0">
                                    <i class="bi bi-envelope me-1"></i>{{ $member->contact }}
                                </p>
                            @endif
                        </div>
                        <div class="card-footer d-flex flex-wrap gap-2">
                            @if (! empty($member->catalogue_url))
                                <a href="{{ $member->catalogue_url }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-collection me-1"></i>{{ __('View records') }}
                                </a>
                            @endif
                            @if ($member->base_url)
                                <a href="{{ $member->base_url }}" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Visit site') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
