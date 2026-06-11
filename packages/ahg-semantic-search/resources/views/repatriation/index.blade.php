{{--
  Repatriation claims register - admin (heratio#1207)

  Structured register of repatriation claims recorded against displaced-heritage
  items, with a status filter and quick links to the virtual-return surface and
  the claim edit form. Sensitive subject matter: copy is factual and respectful;
  a claim status describes where a dialogue stands, never a legal outcome. The
  standing framing disclaimer is always visible. Empty-state when no claims (or
  none for the chosen status). International, jurisdiction-neutral.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Repatriation claims'))

@section('content')
<div class="container-fluid py-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-scale-balanced me-2"></i>{{ __('Repatriation claims') }}
            </h1>
            <p class="text-muted mb-0">
                {{ __('Documented claims and where their dialogue currently stands, recorded against traced displaced-heritage items.') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('semantic-search.displaced-heritage.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-route me-1"></i>{{ __('Displaced-heritage register') }}
            </a>
            <a href="{{ route('repatriation.claims.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>{{ __('Register a claim') }}
            </a>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Standing disclaimer --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('A documented request and its status, not a determination.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    {{-- Status filter chips --}}
    <div class="d-flex flex-wrap gap-1 align-items-center mb-3">
        <span class="text-muted small me-1">{{ __('Filter by status:') }}</span>
        <a href="{{ route('repatriation.claims.index') }}"
           class="badge rounded-pill text-decoration-none {{ $statusFilter === '' ? 'text-bg-dark' : 'text-bg-light border' }}">
            {{ __('All') }} <span class="opacity-75">{{ (int) $total }}</span>
        </a>
        @foreach($statuses as $key => $meta)
            @php $c = (int) ($counts[$key] ?? 0); @endphp
            <a href="{{ route('repatriation.claims.index', ['status' => $key]) }}"
               class="badge rounded-pill text-decoration-none {{ strcasecmp($statusFilter, $key) === 0 ? 'text-bg-dark' : 'text-bg-light border' }}">
                {{ __($meta['label']) }} <span class="opacity-75">{{ $c }}</span>
            </a>
        @endforeach
    </div>

    @if(empty($claims))
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                <h2 class="h5">
                    @if($statusFilter !== '')
                        {{ __('No claims with this status') }}
                    @else
                        {{ __('No repatriation claims recorded yet') }}
                    @endif
                </h2>
                <p class="text-muted mb-3 mx-auto" style="max-width: 42rem;">
                    @if($statusFilter !== '')
                        {{ __('No claims currently hold this status. Other statuses may still appear in the register.') }}
                    @else
                        {{ __('When a claim is registered against a traced displaced-heritage item, it will appear here with its origin context and the stage its dialogue has reached.') }}
                    @endif
                </p>
                <a href="{{ route('repatriation.claims.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i>{{ __('Register a claim') }}
                </a>
            </div>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th>{{ __('Claimant community') }}</th>
                            <th>{{ __('Origin') }}</th>
                            <th>{{ __('Current holder') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($claims as $c)
                            @php
                                $itemTitle = $c['item_title'] ?: (__('Record').' #'.$c['item_ref']);
                                $sm = $c['status_meta'] ?? ['label' => $c['claim_status'], 'level' => 'secondary'];
                            @endphp
                            <tr>
                                <td>
                                    @if(!empty($c['item_slug']))
                                        <a href="{{ url('/'.$c['item_slug']) }}" class="text-decoration-none">{{ $itemTitle }}</a>
                                    @else
                                        {{ $itemTitle }}
                                    @endif
                                    <div class="small text-muted">{{ __('Item ref') }} #{{ $c['item_ref'] }}</div>
                                </td>
                                <td>{{ $c['claimant_community'] ?: '-' }}</td>
                                <td>{{ $c['origin_place'] ?: '-' }}</td>
                                <td>{{ $c['current_holder'] ?: '-' }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $sm['level'] }}">{{ __($sm['label']) }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('virtual-return.show', ['id' => $c['id']]) }}"
                                           class="btn btn-outline-dark" target="_blank" rel="noopener"
                                           title="{{ __('Open the public virtual-return page') }}">
                                            <i class="fas fa-person-walking-arrow-right"></i>
                                            <span class="d-none d-lg-inline ms-1">{{ __('Virtual return') }}</span>
                                        </a>
                                        <a href="{{ route('repatriation.claims.edit', ['id' => $c['id']]) }}"
                                           class="btn btn-outline-secondary">
                                            <i class="fas fa-pen"></i>
                                            <span class="d-none d-lg-inline ms-1">{{ __('Edit') }}</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection
