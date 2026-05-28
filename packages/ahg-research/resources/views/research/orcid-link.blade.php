@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'orcid'])@endsection
@section('title-block')
    <h1><i class="fab fa-orcid me-2 text-success"></i>{{ __('ORCID Integration') }}</h1>
    <p class="text-muted mb-0">{{ __('Link your ORCID iD to pull your publications list and push citations from this archive back to your ORCID Works.') }}</p>
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

{{-- Self-service per-researcher ORCID client. Each researcher registers their
     own free client at orcid.org/developer-tools and enters it here - no admin
     or .env dependency. Tokenless Fetch + Pull-profile below work regardless;
     these credentials only unlock Connect & Sync (Works pull + citation push). --}}
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between" style="background:var(--ahg-primary,#2C3C2D);color:#fff;">
        <span><i class="fas fa-key me-2"></i>{{ __('My ORCID app credentials') }}</span>
        @if($isConfigured)
            <span class="badge bg-success">{{ __('Configured') }}</span>
        @else
            <span class="badge bg-secondary">{{ __('Not set (optional)') }}</span>
        @endif
    </div>
    <div class="card-body">
        <p class="small text-muted mb-2">
            {{ __('To Connect & Sync your Works, register your own free ORCID application and paste its Client ID + Secret below. This is optional - Fetch and Pull-profile already work without it.') }}
        </p>
        <ol class="small text-muted mb-3">
            <li>{{ __('Go to') }} <a href="https://orcid.org/developer-tools" target="_blank">orcid.org/developer-tools</a> {{ __('and sign in.') }}</li>
            <li>{{ __('Register for the free public API. Set the redirect URI to exactly:') }}<br>
                <code>{{ $orcidRedirectUri }}</code></li>
            <li>{{ __('Copy the Client ID + Client Secret it gives you into the fields below.') }}</li>
        </ol>
        <form method="post" action="{{ route('research.orcidSaveCredentials') }}" class="row g-2">
            @csrf
            <div class="col-md-5">
                <label class="form-label small mb-0">{{ __('Client ID') }}</label>
                <input type="text" name="client_id" class="form-control form-control-sm" placeholder="APP-XXXXXXXXXXXXXXXX"
                       value="{{ $orcidCreds['client_id'] ?? '' }}" required>
            </div>
            <div class="col-md-5">
                <label class="form-label small mb-0">{{ __('Client Secret') }}</label>
                <input type="password" name="client_secret" class="form-control form-control-sm" placeholder="{{ __('paste secret') }}" required>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
            </div>
            <div class="col-12">
                <label class="form-check-label small text-muted">
                    <input type="radio" name="api_base" value="https://pub.orcid.org" checked> {{ __('Public API (free)') }}
                    &nbsp;&nbsp;
                    <input type="radio" name="api_base" value="https://api.orcid.org"> {{ __('Member API (push citations)') }}
                </label>
            </div>
        </form>
        @if($isConfigured && ($orcidCreds['client_id'] ?? null))
            <form method="post" action="{{ route('research.orcidClearCredentials') }}" class="mt-2" onsubmit="return confirm('Remove your ORCID client credentials?')">
                @csrf
                <button type="submit" class="btn btn-sm btn-link text-danger p-0">{{ __('Remove my credentials') }}</button>
            </form>
        @endif
    </div>
</div>

@if($link)
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="mb-2"><i class="fab fa-orcid me-1 text-success"></i>{{ __('Linked') }}</h6>
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('ORCID iD') }}</dt>
                <dd class="col-sm-9"><a href="https://orcid.org/{{ e($link->orcid_id) }}" target="_blank">{{ e($link->orcid_id) }}</a></dd>

                <dt class="col-sm-3">{{ __('Profile synced') }}</dt>
                <dd class="col-sm-9">{{ ($link->last_profile_synced_at ?? null) ?: '-' }}</dd>

                <dt class="col-sm-3">{{ __('Works synced') }}</dt>
                <dd class="col-sm-9">{{ $link->last_synced_at ?: '-' }} @if($link->last_works_count !== null) <span class="badge bg-secondary ms-1">{{ $link->last_works_count }} works</span> @endif</dd>

                @if($link->last_error)
                    <dt class="col-sm-3 text-danger">{{ __('Last error') }}</dt>
                    <dd class="col-sm-9 text-danger">{{ e($link->last_error) }}</dd>
                @endif
            </dl>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div class="d-flex gap-2 flex-wrap">
                {{-- Pull profile = tokenless public read; always available. --}}
                <form method="post" action="{{ route('research.orcidPullProfile') }}" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-success"><i class="fas fa-id-card me-1"></i>{{ __('Pull profile from ORCID') }}</button>
                </form>
                {{-- Works pull needs an OAuth token; only shown once an admin has
                     configured the client (otherwise it can only ever fail). --}}
                @if($isConfigured)
                    <form method="post" action="{{ route('research.orcidSync') }}" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-primary"><i class="fas fa-sync me-1"></i>{{ __('Pull Works from ORCID') }}</button>
                    </form>
                @endif
            </div>
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
            <p class="text-muted">{{ __('Linking lets Heratio recognise your authorship and pull your public profile.') }}</p>

            @if(!empty($researcher->orcid_id))
                <p class="small text-muted mb-2">{{ __('Your profile has ORCID iD :id on file. Pull your public profile details now:', ['id' => $researcher->orcid_id]) }}</p>
                <form method="post" action="{{ route('research.orcidPullProfile') }}" class="d-inline">
                    @csrf
                    <button class="btn btn-success"><i class="fas fa-id-card me-1"></i>{{ __('Pull profile from ORCID') }}</button>
                </form>
            @endif

            @if($isConfigured)
                <div class="mt-3">
                    <a href="{{ route('research.orcidAuthorize') }}" class="btn btn-outline-success">
                        <i class="fab fa-orcid me-1"></i>{{ __('Connect & Sync with ORCID') }}
                    </a>
                    <small class="form-text text-muted d-block">{{ __('Sign in at ORCID to also pull your Works and push citations.') }}</small>
                </div>
            @else
                <p class="small text-muted mt-3 mb-0">{{ __('Publication sync (Connect & Sync) is not enabled on this server yet. Profile pull above works now.') }}</p>
            @endif
        </div>
    </div>
@endif
@endsection
