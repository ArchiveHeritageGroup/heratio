@extends('ahg-theme-b5::layout')

@section('title', __('Email and SMS factors'))

@section('content')
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-9">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h4 class="mb-0"><i class="bi bi-envelope-paper"></i> {{ __('Email and SMS factors') }}</h4>
          <a href="{{ route('security-clearance.otp.setup', ['return' => $returnUrl]) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> {{ __('Add factor') }}
          </a>
        </div>
        <div class="card-body">

          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
          @endif

          <p class="text-muted">
            {{ __('Email and SMS one-time codes are a low-friction MFA fallback. They sit alongside any authenticator app or passkey you have enrolled - any verified factor can satisfy two-factor sign-in.') }}
          </p>

          @if($factors->isEmpty())
            <div class="alert alert-info text-center">
              {{ __('You have no enrolled email or SMS factors yet.') }}
            </div>
          @else
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>{{ __('Channel') }}</th>
                    <th>{{ __('Label') }}</th>
                    <th>{{ __('Destination') }}</th>
                    <th>{{ __('Verified') }}</th>
                    <th>{{ __('Last used') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                  </tr>
                </thead>
                <tbody>
                @foreach($factors as $factor)
                  <tr>
                    <td>
                      @if($factor->factor_type === 'email')
                        <i class="bi bi-envelope-fill"></i> {{ __('Email') }}
                      @else
                        <i class="bi bi-phone-fill"></i> {{ __('SMS') }}
                      @endif
                    </td>
                    <td><strong>{{ $factor->label }}</strong></td>
                    <td><code class="small">{{ $factor->destination }}</code></td>
                    <td>
                      @if($factor->verified_at)
                        <span class="badge bg-success">{{ __('Yes') }}</span>
                        <div class="small text-muted">{{ \Carbon\Carbon::parse($factor->verified_at)->diffForHumans() }}</div>
                      @else
                        <a href="{{ route('security-clearance.otp.verify-enrolment', ['factor' => $factor->id, 'return' => $returnUrl]) }}"
                           class="badge bg-warning text-dark">{{ __('Pending') }}</a>
                      @endif
                    </td>
                    <td><span class="small">{{ $factor->last_used_at ? \Carbon\Carbon::parse($factor->last_used_at)->diffForHumans() : __('never') }}</span></td>
                    <td class="text-end">
                      <form method="POST" action="{{ route('security-clearance.otp.delete', $factor->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('{{ __('Delete this factor? You will no longer be able to sign in with it.') }}')">
                          <i class="bi bi-trash"></i> {{ __('Delete') }}
                        </button>
                      </form>
                    </td>
                  </tr>
                @endforeach
                </tbody>
              </table>
            </div>
          @endif

          <hr>
          <a href="{{ $returnUrl }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
