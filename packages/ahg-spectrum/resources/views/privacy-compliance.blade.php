@extends('theme::layouts.1col')

@section('title', __('Privacy Compliance (POPIA/PAIA/GDPR)'))

@section('content')
<div class="row">
    <div class="col-md-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-user-shield me-2"></i>
            {{ __('Privacy Compliance (POPIA/PAIA/GDPR)') }}
        </h1>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="text-muted">{{ __('Compliance Score') }}</h6>
                <div class="display-4 text-{{ ($complianceScore ?? 0) >= 80 ? 'success' : 'warning' }}">
                    {{ $complianceScore ?? 0 }}%
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-primary h-100">
            <div class="card-body">
                <h4>{{ $ropaCount ?? 0 }}</h4>
                <small>{{ __('Processing Activities') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning h-100">
            <div class="card-body">
                <h4>{{ $dsarStats['pending'] ?? 0 }}</h4>
                <small>{{ __('Pending DSARs') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-danger h-100">
            <div class="card-body">
                <h4>{{ $breachStats['open'] ?? 0 }}</h4>
                <small>{{ __('Open Breaches') }}</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="btn-group mb-4">
            <a href="{{ route('ahgspectrum.privacy-ropa') }}" class="btn btn-outline-primary">{{ __('ROPA') }}</a>
            <a href="{{ route('ahgspectrum.privacy-dsar') }}" class="btn btn-outline-warning">{{ __('DSARs') }}</a>
            <a href="{{ route('ahgspectrum.privacy-breaches') }}" class="btn btn-outline-danger">{{ __('Breaches') }}</a>
        </div>
    </div>
</div>
@endsection
