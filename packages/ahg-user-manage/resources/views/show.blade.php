@extends('theme::layouts.1col')

@section('title', 'User ' . ($user->authorized_form_of_name ?? $user->username ?? 'User'))
@section('body-class', 'view user')

@section('title-block')
  <h1>User {{ $user->authorized_form_of_name ?? $user->username ?? '[Unknown]' }}</h1>

  @if(!$user->active)
    <div class="alert alert-danger" role="alert">
      This user is inactive
    </div>
  @endif
@endsection

@section('content')

  <section id="content">

    {{-- ===== User details ===== --}}
    <section id="userDetails">
      <h2 class="h6 mb-0 py-2 px-3 rounded-top" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#user-details-collapse">User details</a>
        @auth
          <a href="{{ route('user.edit', $user->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit"><i class="fas fa-pencil-alt"></i></a>
        @endauth
      </h2>
      <div id="user-details-collapse">

        @if($user->username)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">User name</h3>
            <div class="col-9 p-2">
              {{ $user->username }}
              @if(auth()->check() && auth()->user()->id === $user->id)
                (you)
              @endif
            </div>
          </div>
        @endif

        @if($user->email)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Email</h3>
            <div class="col-9 p-2">{{ $user->email }}</div>
          </div>
        @endif

        @if(auth()->check() && !(auth()->user()->is_admin ?? false))
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Password</h3>
            <div class="col-9 p-2">
              <a href="{{ route('user.edit', $user->slug) }}">Reset password</a>
            </div>
          </div>
        @endif

        @if(isset($groups) && $groups->isNotEmpty())
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">User groups</h3>
            <div class="col-9 p-2">
              <ul class="m-0 ms-1 ps-3">
                @foreach($groups as $group)
                  <li>{{ $group->name }}</li>
                @endforeach
              </ul>
            </div>
          </div>
        @endif

        @if(isset($repositories) && count($repositories) > 0)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Repository affiliation</h3>
            <div class="col-9 p-2">
              <ul class="m-0 ms-1 ps-3">
                @foreach($repositories as $repo)
                  <li>{{ $repo->name ?? $repo->authorized_form_of_name ?? '[Unknown]' }}</li>
                @endforeach
              </ul>
            </div>
          </div>
        @endif

        @if(isset($restApiKey))
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">REST API key</h3>
            <div class="col-9 p-2"><code>{{ $restApiKey }}</code></div>
          </div>
        @endif

        @if(isset($oaiApiKey))
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">OAI-PMH API key</h3>
            <div class="col-9 p-2"><code>{{ $oaiApiKey }}</code></div>
          </div>
        @endif

      </div>
    </section>

    {{-- ===== Security Clearance ===== --}}
    @if(isset($clearanceInfo) || (auth()->check() && (auth()->user()->is_admin ?? false)))
      <section id="securityClearance" class="mt-4">
        <div class="section border rounded">
          <div class="d-flex justify-content-between align-items-center section-heading rounded-top bg-light p-3">
            <h4 class="mb-0">
              <i class="fas fa-shield-alt me-2"></i>Security Clearance
            </h4>
            @if(auth()->check() && (auth()->user()->is_admin ?? false))
              <a href="{{ url('/admin/settings/security-clearances') }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-cog me-1"></i>Manage Clearances
              </a>
            @endif
          </div>

          <div class="p-3">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <strong>Current Clearance Level:</strong>
                  <span class="badge bg-{{ $clearanceColor ?? 'secondary' }} ms-2 fs-6">
                    {{ $clearanceName ?? 'None' }}
                  </span>
                </div>

                @if(isset($clearanceInfo) && $clearanceInfo)
                  @if($clearanceInfo->granted_at ?? null)
                    <div class="mb-2">
                      <strong>Granted:</strong>
                      {{ \Carbon\Carbon::parse($clearanceInfo->granted_at)->format('Y-m-d') }}
                    </div>
                  @endif

                  @if($clearanceInfo->expires_at ?? null)
                    @php
                      $expiresAt = \Carbon\Carbon::parse($clearanceInfo->expires_at);
                      $isExpired = $expiresAt->isPast();
                      $isExpiringSoon = $expiresAt->isBefore(now()->addDays(30));
                    @endphp
                    <div class="mb-2">
                      <strong>Expires:</strong>
                      <span class="{{ $isExpired ? 'text-danger' : ($isExpiringSoon ? 'text-warning' : '') }}">
                        {{ $expiresAt->format('Y-m-d') }}
                        @if($isExpired)
                          <span class="badge bg-danger ms-1">EXPIRED</span>
                        @elseif($isExpiringSoon)
                          <span class="badge bg-warning text-dark ms-1">Expiring Soon</span>
                        @endif
                      </span>
                    </div>
                  @endif

                  @if($clearanceInfo->notes ?? null)
                    <div class="mb-2">
                      <strong>Notes:</strong>
                      <span class="text-muted">{{ $clearanceInfo->notes }}</span>
                    </div>
                  @endif
                @else
                  <p class="text-muted mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    No security clearance assigned. This user can only access public records.
                  </p>
                @endif
              </div>

              <div class="col-md-6">
                <div class="card bg-light">
                  <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-info-circle me-1"></i>Classification Levels</h6>
                    <ul class="list-unstyled small mb-0">
                      <li><span class="badge bg-success">Public</span> - Open access materials</li>
                      <li><span class="badge bg-info">Restricted</span> - Limited distribution</li>
                      <li><span class="badge bg-warning text-dark">Confidential</span> - Sensitive information</li>
                      <li><span class="badge bg-danger">Secret</span> - Highly sensitive</li>
                      <li><span class="badge bg-dark">Top Secret</span> - Maximum protection</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    @endif

  </section>

@endsection

@section('after-content')
  @auth
    <ul class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
      <li><a href="{{ route('user.edit', $user->slug) }}" class="btn atom-btn-outline-light">Edit</a></li>

      @if(auth()->user()->id !== $user->id)
        <li><a href="{{ route('user.confirmDelete', $user->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
      @endif

      <li><a href="{{ route('user.create') }}" class="btn atom-btn-outline-light">Add new</a></li>
      <li><a href="{{ route('user.browse') }}" class="btn atom-btn-outline-light">Return to user list</a></li>
    </ul>
  @endauth
@endsection
