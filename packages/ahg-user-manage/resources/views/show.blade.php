@extends('theme::layouts.1col')

@section('title', 'User profile')
@section('body-class', 'view user')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">{{ __('User profile') }}</h1>
    <span class="small" id="heading-label">
      {{ $user->username ?? '[Unknown]' }}
      @if(auth()->check() && auth()->user()->id === $user->id)
        <span class="badge bg-info">(you)</span>
      @endif
    </span>
  </div>

  @if(!$user->active)
    <div class="alert alert-danger" role="alert">
      This user is inactive
    </div>
  @endif
@endsection

@section('content')

  {{-- ===== Basic info ===== --}}
  <section class="section border-bottom" id="basicInfo">
    <h2 class="h5 mb-0 atom-section-header">
      <div class="d-flex p-3 border-bottom text-primary">
        @auth<a href="{{ route('user.edit', $user->slug) }}" class="text-primary text-decoration-none">Basic info</a>@else Basic info @endauth
      </div>
    </h2>
    <div>
      @if($user->username)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Username') }}</h3>
          <div class="col-9 p-2">{{ $user->username }}</div>
        </div>
      @endif

      @if($user->email)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Email') }}</h3>
          <div class="col-9 p-2">{{ $user->email }}</div>
        </div>
      @endif

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Active') }}</h3>
        <div class="col-9 p-2">
          @if($user->active)
            <span class="badge bg-success">{{ __('Yes') }}</span>
          @else
            <span class="badge bg-danger">No</span>
          @endif
        </div>
      </div>
    </div>
  </section>

  {{-- ===== Profile ===== --}}
  @if(!empty($user->authorized_form_of_name))
    <section class="section border-bottom" id="profile">
      <h2 class="h5 mb-0 atom-section-header">
        <div class="d-flex p-3 border-bottom text-primary">
          @auth<a href="{{ route('user.edit', $user->slug) }}" class="text-primary text-decoration-none">Profile</a>@else Profile @endauth
        </div>
      </h2>
      <div>
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Authorized form of name') }}</h3>
          <div class="col-9 p-2">{{ $user->authorized_form_of_name }}</div>
        </div>
      </div>
    </section>
  @endif

  {{-- ===== Contact information ===== --}}
  @if(!empty($user->contact))
    <section class="section border-bottom" id="contactInfo">
      <h2 class="h5 mb-0 atom-section-header">
        <div class="d-flex p-3 border-bottom text-primary">
          @auth<a href="{{ route('user.edit', $user->slug) }}" class="text-primary text-decoration-none">Contact information</a>@else Contact information @endauth
        </div>
      </h2>
      <div>
        @if(!empty($user->contact->telephone))
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Telephone') }}</h3>
            <div class="col-9 p-2">{{ $user->contact->telephone }}</div>
          </div>
        @endif
        @if(!empty($user->contact->fax))
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Fax') }}</h3>
            <div class="col-9 p-2">{{ $user->contact->fax }}</div>
          </div>
        @endif
        @if(!empty($user->contact->street_address))
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Street address') }}</h3>
            <div class="col-9 p-2">{{ $user->contact->street_address }}</div>
          </div>
        @endif
        @if(!empty($user->contact->city))
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('City') }}</h3>
            <div class="col-9 p-2">{{ $user->contact->city }}</div>
          </div>
        @endif
        @if(!empty($user->contact->region))
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Region/province') }}</h3>
            <div class="col-9 p-2">{{ $user->contact->region }}</div>
          </div>
        @endif
        @if(!empty($user->contact->postal_code))
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Postal code') }}</h3>
            <div class="col-9 p-2">{{ $user->contact->postal_code }}</div>
          </div>
        @endif
        @if(!empty($user->contact->country_code))
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Country') }}</h3>
            <div class="col-9 p-2">{{ strtoupper($user->contact->country_code) }}</div>
          </div>
        @endif
        @if(!empty($user->contact->website))
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Website') }}</h3>
            <div class="col-9 p-2"><a href="{{ $user->contact->website }}" target="_blank">{{ $user->contact->website }}</a></div>
          </div>
        @endif
        @if(!empty($user->contact->note))
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Note') }}</h3>
            <div class="col-9 p-2">{{ $user->contact->note }}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- ===== Access control ===== --}}
  <section class="section border-bottom" id="accessControl">
    <h2 class="h5 mb-0 atom-section-header">
      <div class="d-flex p-3 border-bottom text-primary">
        @auth<a href="{{ route('user.edit', $user->slug) }}" class="text-primary text-decoration-none">Access control</a>@else Access control @endauth
      </div>
    </h2>
    <div>
      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('User groups') }}</h3>
        <div class="col-9 p-2">
          @if(isset($groups) && $groups->isNotEmpty())
            {{ $groups->pluck('name')->implode(', ') }}
          @else
            <em>None</em>
          @endif
        </div>
      </div>
      @if(isset($repositories) && count($repositories) > 0)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Repository affiliation') }}</h3>
          <div class="col-9 p-2">
            @foreach($repositories as $repo)
              {{ $repo->name ?? $repo->authorized_form_of_name ?? '[Unknown]' }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @endif
    </div>
  </section>

  {{-- ===== Allowed languages for translation ===== --}}
  <section class="section border-bottom" id="translate">
    <h2 class="h5 mb-0 atom-section-header">
      <div class="d-flex p-3 border-bottom text-primary">
        @auth<a href="{{ route('user.edit', $user->slug) }}" class="text-primary text-decoration-none">Allowed languages for translation</a>@else Allowed languages for translation @endauth
      </div>
    </h2>
    <div>
      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Translate') }}</h3>
        <div class="col-9 p-2">
          @if(isset($user->translateLanguages) && count($user->translateLanguages) > 0)
            {{ implode(', ', array_map('strtoupper', $user->translateLanguages)) }}
          @else
            <em>None</em>
          @endif
        </div>
      </div>
    </div>
  </section>

  {{-- ===== API keys ===== --}}
  <section class="section border-bottom" id="apiKeys">
    <h2 class="h5 mb-0 atom-section-header">
      <div class="d-flex p-3 border-bottom text-primary">
        @auth<a href="{{ route('user.edit', $user->slug) }}" class="text-primary text-decoration-none">API keys</a>@else API keys @endauth
      </div>
    </h2>
    <div>
      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('REST API access key') }}</h3>
        <div class="col-9 p-2">
          @if(isset($restApiKey))
            <code>{{ $restApiKey }}</code>
          @else
            <em>Not generated yet.</em>
          @endif
        </div>
      </div>
      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('OAI-PMH API access key') }}</h3>
        <div class="col-9 p-2">
          @if(isset($oaiApiKey))
            <code>{{ $oaiApiKey }}</code>
          @else
            <em>Not generated yet.</em>
          @endif
        </div>
      </div>
    </div>
  </section>

  {{-- ===== Security clearance ===== --}}
  @if(class_exists(\AhgSecurityClearance\Services\SecurityClearanceService::class))
    <section class="section border-bottom" id="securityClearance">
      <h2 class="h5 mb-0 atom-section-header">
        <div class="d-flex p-3 border-bottom text-primary">Security clearance</div>
      </h2>
      <div>
        @if(!empty($clearance))
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Clearance level') }}</h3>
            <div class="col-9 p-2">
              @if(!empty($clearance->color))
                <span class="badge" style="background:{{ $clearance->color }};color:#fff;">{{ $clearance->classification_name ?? 'Unknown' }}</span>
              @else
                {{ $clearance->classification_name ?? 'None' }}
              @endif
              @auth
                <a href="{{ route('security-clearance.user', $user->slug) }}" class="btn btn-sm atom-btn-outline-light ms-2">Manage</a>
              @endauth
            </div>
          </div>
          @if(!empty($clearance->granted_at))
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Granted') }}</h3>
              <div class="col-9 p-2">{{ \Carbon\Carbon::parse($clearance->granted_at)->format('Y-m-d H:i') }}@if(!empty($clearance->granted_by_name)) by {{ $clearance->granted_by_name }}@endif</div>
            </div>
          @endif
          @if(!empty($clearance->expires_at))
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Expires') }}</h3>
              <div class="col-9 p-2">
                @php $expired = strtotime($clearance->expires_at) < time(); @endphp
                <span class="{{ $expired ? 'text-danger fw-bold' : '' }}">
                  {{ \Carbon\Carbon::parse($clearance->expires_at)->format('Y-m-d') }}
                  @if($expired)<span class="badge bg-danger ms-1">{{ __('Expired') }}</span>@endif
                </span>
              </div>
            </div>
          @endif
        @else
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Clearance level') }}</h3>
            <div class="col-9 p-2">
              <em>None</em>
              @auth
                <a href="{{ route('security-clearance.user', $user->slug) }}" class="btn btn-sm atom-btn-outline-light ms-2">Grant clearance</a>
              @endauth
            </div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- ===== Administration area (admin only) ===== --}}
  @auth
    @if(auth()->user()->is_admin && ($user->created_at || $user->updated_at))
      <section class="section border-bottom" id="userAdmin">
        <h2 class="h5 mb-0 atom-section-header">
          <div class="d-flex p-3 border-bottom text-primary">Administration area</div>
        </h2>
        <div>
          @if($user->created_at)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Created at') }}</h3>
              <div class="col-9 p-2">{{ \Carbon\Carbon::parse($user->created_at)->format('Y-m-d H:i:s') }}</div>
            </div>
          @endif
          @if($user->updated_at)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Updated at') }}</h3>
              <div class="col-9 p-2">{{ \Carbon\Carbon::parse($user->updated_at)->format('Y-m-d H:i:s') }}</div>
            </div>
          @endif
        </div>
      </section>
    @endif
  @endauth

@endsection

@section('after-content')
  @auth
    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('user.edit', $user->slug) }}" class="btn atom-btn-outline-light">Edit</a></li>
      @if(auth()->user()->id !== $user->id)
        <li><a href="{{ route('user.confirmDelete', $user->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
      @endif
      <li><a href="{{ route('user.add') }}" class="btn atom-btn-outline-light">Add new</a></li>
      <li><a href="{{ route('user.browse') }}" class="btn atom-btn-outline-light">Return to user list</a></li>
    </ul>
  @endauth
@endsection
