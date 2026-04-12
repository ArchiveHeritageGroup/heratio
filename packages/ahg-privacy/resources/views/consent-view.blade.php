@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="{{ route('ahgprivacy.consent-list') }}" class="btn btn-outline-secondary btn-sm me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="h2 mb-0"><i class="fas fa-check-circle me-2"></i>{{ __('Consent Record') }} #{{ $consent->id }}</h1>
                <small class="text-muted">{{ $consent->data_subject_id }}</small>
            </div>
        </div>
        <div>
            <span class="badge bg-{{ ($consent->status ?? 'active') === 'active' ? 'success' : 'secondary' }} fs-6 me-2">{{ ucfirst($consent->status ?? 'active') }}</span>
            <span class="badge bg-{{ ($consent->consent_given ?? 0) ? 'success' : 'danger' }} fs-6">{{ ($consent->consent_given ?? 0) ? __('Consent Given') : __('No Consent') }}</span>
            <a href="{{ route('ahgprivacy.consent-edit', ['id' => $consent->id]) }}" class="btn btn-primary ms-3"><i class="fas fa-edit me-1"></i>{{ __('Edit') }}</a>
        </div>
    </div>
    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-user me-2"></i>{{ __('Data Subject') }}</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('Subject Identifier') }}</dt>
                        <dd class="col-sm-8">{{ $consent->data_subject_id }}</dd>
                        @if($consent->subject_name)
                        <dt class="col-sm-4">{{ __('Name') }}</dt>
                        <dd class="col-sm-8">{{ $consent->subject_name }}</dd>
                        @endif
                        @if($consent->subject_email)
                        <dt class="col-sm-4">{{ __('Email') }}</dt>
                        <dd class="col-sm-8">{{ $consent->subject_email }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>{{ __('Consent Details') }}</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('Purpose') }}</dt>
                        <dd class="col-sm-8">{{ $consent->purpose }}</dd>
                        <dt class="col-sm-4">{{ __('Consent Given') }}</dt>
                        <dd class="col-sm-8">{!! ($consent->consent_given ?? 0) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' !!}</dd>
                        <dt class="col-sm-4">{{ __('Consent Method') }}</dt>
                        <dd class="col-sm-8">{{ ucfirst($consent->consent_method ?? 'form') }}</dd>
                        <dt class="col-sm-4">{{ __('Consent Date') }}</dt>
                        <dd class="col-sm-8">{{ $consent->consent_date ?? '-' }}</dd>
                        @if($consent->source)
                        <dt class="col-sm-4">{{ __('Source') }}</dt>
                        <dd class="col-sm-8">{{ $consent->source }}</dd>
                        @endif
                        <dt class="col-sm-4">{{ __('Jurisdiction') }}</dt>
                        <dd class="col-sm-8">{{ strtoupper($consent->jurisdiction ?? 'popia') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('Status') }}</h5></div>
                <div class="card-body">
                    <p class="mb-2"><strong>{{ __('Status') }}:</strong> <span class="badge bg-{{ ($consent->status ?? 'active') === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($consent->status ?? 'active') }}</span></p>
                    <p class="mb-2"><strong>{{ __('Created') }}:</strong> {{ $consent->created_at }}</p>
                    @if($consent->withdrawal_date)
                    <p class="mb-0"><strong>{{ __('Withdrawn') }}:</strong> {{ $consent->withdrawal_date }}</p>
                    @endif
                </div>
            </div>
            @if(($consent->status ?? 'active') === 'active' && $consent->consent_given)
            <div class="card">
                <div class="card-header bg-warning"><h5 class="mb-0"><i class="fas fa-ban me-2"></i>{{ __('Withdraw Consent') }}</h5></div>
                <div class="card-body">
                    <form method="post" action="{{ route('ahgprivacy.consent-withdraw', ['id' => $consent->id]) }}" onsubmit="return confirm('Are you sure?');">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Reason') }}</label>
                            <textarea name="reason" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning w-100"><i class="fas fa-ban me-1"></i>{{ __('Withdraw') }}</button>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
