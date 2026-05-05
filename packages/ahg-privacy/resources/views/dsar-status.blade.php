@extends('theme::layouts.1col')

@section('content')
<div class="container py-4">
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('ahgprivacy.index') }}">{{ __('Privacy') }}</a></li>
            <li class="breadcrumb-item active">{{ __('Check Status') }}</li>
        </ol>
    </nav>

    <h1 class="h2 mb-4"><i class="fas fa-search me-2"></i>{{ __('Check Request Status') }}</h1>

    @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if(isset($dsar))
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">{{ __('Request Details') }}: {{ $dsar->reference_number }}</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>{{ __('Status:') }}</strong>
                        @php
$statusClasses = [
                            'received' => 'secondary',
                            'verified' => 'info',
                            'in_progress' => 'primary',
                            'pending_info' => 'warning',
                            'completed' => 'success',
                            'rejected' => 'danger',
                            'withdrawn' => 'dark'
                        ];
                        $statusClass = $statusClasses[$dsar->status] ?? 'secondary';
@endphp
                        <span class="badge bg-{{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $dsar->status)) }}</span>
                    </p>
                    <p><strong>{{ __('Request Type:') }}</strong> {{ ucfirst(str_replace('_', ' ', $dsar->request_type)) }}</p>
                    <p><strong>{{ __('Submitted:') }}</strong> {{ $dsar->received_date }}</p>
                    <p><strong>{{ __('Due Date:') }}</strong> {{ $dsar->due_date }}</p>
                </div>
                <div class="col-md-6">
                    @if($dsar->status === 'completed')
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>{{ __('Your request has been completed.') }}
                    </div>
                    @elseif($dsar->status === 'rejected')
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>{{ __('Your request was not approved.') }}
                        @if($dsar->refusal_reason)
                        <p class="mb-0 mt-2"><strong>{{ __('Reason:') }}</strong> {{ $dsar->refusal_reason }}</p>
                        @endif
                    </div>
                    @else
                    <div class="alert alert-info">
                        <i class="fas fa-clock me-2"></i>{{ __('Your request is being processed.') }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('ahgprivacy.dsar-status') }}">
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label" for="dsar-ref-input">{{ __('Reference Number') }}</label>
                        @if(!empty($myDsars) && count($myDsars) > 0)
                            {{-- Authenticated user has at least one DSAR — combine a dropdown
                                 of their refs with a free-text fallback. The <datalist>
                                 gives type-ahead and lets them search/filter, while still
                                 accepting any reference if they paste one from elsewhere. --}}
                            <input list="dsar-refs-list" id="dsar-ref-input" name="reference"
                                   class="form-control"
                                   placeholder="{{ __('Pick from your requests or paste a reference...') }}"
                                   value="{{ request('reference') }}">
                            <datalist id="dsar-refs-list">
                                @foreach($myDsars as $r)
                                    <option value="{{ $r->reference_number }}">
                                        {{ ucfirst(str_replace('_', ' ', $r->request_type)) }} &middot; {{ ucfirst(str_replace('_', ' ', $r->status)) }} &middot; {{ $r->received_date }}
                                    </option>
                                @endforeach
                            </datalist>
                            <div class="form-text small">
                                <i class="fas fa-info-circle me-1"></i>
                                {{ trans_choice('You have :count request|You have :count requests', count($myDsars), ['count' => count($myDsars)]) }} —
                                {{ __('start typing to filter, or pick from the dropdown.') }}
                            </div>
                        @else
                            {{-- Anonymous / no requests on file: keep the freeform input + email
                                 so a data subject who got the reference via email can still
                                 look it up. --}}
                            <input type="text" id="dsar-ref-input" name="reference" class="form-control"
                                   placeholder="{{ __('DSAR-20260504-XXXXXX') }}" value="{{ request('reference') }}">
                        @endif
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label" for="dsar-email-input">{{ __('Email Address') }}</label>
                        <input type="email" id="dsar-email-input" name="email" class="form-control"
                               value="{{ request('email', auth()->check() ? (auth()->user()->email ?? '') : '') }}"
                               @if(auth()->check() && empty(request('email'))) readonly @endif>
                        @if(auth()->check())
                            <div class="form-text small text-muted">{{ __('Pre-filled from your account.') }}</div>
                        @endif
                    </div>
                    <div class="col-md-2 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>{{ __('Check') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
