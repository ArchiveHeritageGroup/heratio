@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'orcid'])@endsection
@section('title-block')
    <h1><i class="fab fa-orcid me-2 text-success"></i>{{ __('ORCID Integration') }}</h1>
    <p class="text-muted mb-0">{{ __('Link your ORCID iD to pull your publications list and push citations from this archive back to your ORCID Works.') }}</p>
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@if(!$isConfigured)
    <div class="alert alert-warning">
        <h6 class="mb-1"><i class="fas fa-exclamation-triangle me-1"></i>{{ __('ORCID not configured') }}</h6>
        <p class="mb-2">{{ __('Set these environment variables in .env and restart php-fpm:') }}</p>
        <ul class="mb-2 small font-monospace">
            <li>ORCID_CLIENT_ID</li>
            <li>ORCID_CLIENT_SECRET</li>
            <li>ORCID_REDIRECT_URI={{ url('/research/orcid/callback') }}</li>
            <li>ORCID_BASE=https://orcid.org   {{ __('(or https://sandbox.orcid.org for testing)') }}</li>
            <li>ORCID_API_BASE=https://api.orcid.org   {{ __('(Member API; pub.orcid.org for Public)') }}</li>
        </ul>
        <p class="mb-0 small">{{ __('Register the app at') }} <a href="https://orcid.org/developer-tools" target="_blank">orcid.org/developer-tools</a>.</p>
    </div>
@else
    @if($link)
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-2"><i class="fab fa-orcid me-1 text-success"></i>{{ __('Linked') }}</h6>
                <dl class="row mb-0">
                    <dt class="col-sm-3">{{ __('ORCID iD') }}</dt>
                    <dd class="col-sm-9"><a href="https://orcid.org/{{ e($link->orcid_id) }}" target="_blank">{{ e($link->orcid_id) }}</a></dd>

                    <dt class="col-sm-3">{{ __('Scope') }}</dt>
                    <dd class="col-sm-9"><code>{{ e($link->scope) }}</code></dd>

                    <dt class="col-sm-3">{{ __('Token expires') }}</dt>
                    <dd class="col-sm-9">{{ $link->expires_at ?: '-' }}</dd>

                    <dt class="col-sm-3">{{ __('Last synced') }}</dt>
                    <dd class="col-sm-9">{{ $link->last_synced_at ?: '-' }} @if($link->last_works_count !== null) <span class="badge bg-secondary ms-1">{{ $link->last_works_count }} works</span> @endif</dd>

                    @if($link->last_error)
                        <dt class="col-sm-3 text-danger">{{ __('Last error') }}</dt>
                        <dd class="col-sm-9 text-danger">{{ e($link->last_error) }}</dd>
                    @endif
                </dl>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <form method="post" action="{{ route('research.orcidSync') }}" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-primary"><i class="fas fa-sync me-1"></i>{{ __('Pull Works from ORCID') }}</button>
                </form>
                <form method="post" action="{{ route('research.orcidUnlink') }}" class="d-inline" onsubmit="return confirm('Unlink your ORCID iD?')">
                    @csrf
                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-unlink me-1"></i>{{ __('Unlink') }}</button>
                </form>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body text-center py-4">
                <i class="fab fa-orcid fa-3x text-success mb-3"></i>
                <h5 class="mb-2">{{ __('You haven\'t linked an ORCID iD yet.') }}</h5>
                <p class="text-muted">{{ __('Linking lets Heratio recognise your authorship and push citations back to your ORCID Works.') }}</p>
                <a href="{{ route('research.orcidAuthorize') }}" class="btn btn-success">
                    <i class="fab fa-orcid me-1"></i>{{ __('Connect with ORCID') }}
                </a>
            </div>
        </div>
    @endif
@endif
@endsection
