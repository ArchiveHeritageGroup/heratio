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
      <h2 class="h5 mb-0 atom-section-header">
        <div class="d-flex p-3 border-bottom text-primary">
          User details
          @auth
            <a href="{{ route('user.edit', $user->slug) }}" class="ms-auto text-primary opacity-75" style="font-size:.75rem;" title="Edit"><i class="fas fa-pencil-alt"></i></a>
          @endauth
        </div>
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

    {{-- ===== Profile ===== --}}
    @if($user->authorized_form_of_name)
      <section id="userProfile" class="mt-3">
        <h2 class="h5 mb-0 atom-section-header">
          <div class="d-flex p-3 border-bottom text-primary">
            Profile
            @auth
              <a href="{{ route('user.edit', $user->slug) }}" class="ms-auto text-primary opacity-75" style="font-size:.75rem;" title="Edit"><i class="fas fa-pencil-alt"></i></a>
            @endauth
          </div>
        </h2>
        <div>
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Authorized form of name</h3>
            <div class="col-9 p-2">{{ $user->authorized_form_of_name }}</div>
          </div>
        </div>
      </section>
    @endif

    {{-- ===== Contact information ===== --}}
    @if($user->contact)
      <section id="userContact" class="mt-3">
        <h2 class="h5 mb-0 atom-section-header">
          <div class="d-flex p-3 border-bottom text-primary">
            Contact information
            @auth
              <a href="{{ route('user.edit', $user->slug) }}" class="ms-auto text-primary opacity-75" style="font-size:.75rem;" title="Edit"><i class="fas fa-pencil-alt"></i></a>
            @endauth
          </div>
        </h2>
        <div>

          @if($user->email)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Email</h3>
              <div class="col-9 p-2">{{ $user->email }}</div>
            </div>
          @endif

          @if($user->contact->telephone)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Telephone</h3>
              <div class="col-9 p-2">{{ $user->contact->telephone }}</div>
            </div>
          @endif

          @if($user->contact->fax)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Fax</h3>
              <div class="col-9 p-2">{{ $user->contact->fax }}</div>
            </div>
          @endif

          @if($user->contact->website)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Website</h3>
              <div class="col-9 p-2"><a href="{{ $user->contact->website }}" target="_blank">{{ $user->contact->website }}</a></div>
            </div>
          @endif

          @if($user->contact->street_address)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Street address</h3>
              <div class="col-9 p-2">{{ $user->contact->street_address }}</div>
            </div>
          @endif

          @if($user->contact->city)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">City</h3>
              <div class="col-9 p-2">{{ $user->contact->city }}</div>
            </div>
          @endif

          @if($user->contact->region)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Region/province</h3>
              <div class="col-9 p-2">{{ $user->contact->region }}</div>
            </div>
          @endif

          @if($user->contact->postal_code)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Postal code</h3>
              <div class="col-9 p-2">{{ $user->contact->postal_code }}</div>
            </div>
          @endif

          @if($user->contact->country_code)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Country</h3>
              <div class="col-9 p-2">{{ strtoupper($user->contact->country_code) }}</div>
            </div>
          @endif

        </div>
      </section>
    @endif

    {{-- ===== Allowed languages for translation ===== --}}
    @if(isset($user->translateLanguages) && count($user->translateLanguages) > 0)
      <section id="userTranslateLanguages" class="mt-3">
        <h2 class="h5 mb-0 atom-section-header">
          <div class="d-flex p-3 border-bottom text-primary">
            Allowed languages for translation
            @auth
              <a href="{{ route('user.edit', $user->slug) }}" class="ms-auto text-primary opacity-75" style="font-size:.75rem;" title="Edit"><i class="fas fa-pencil-alt"></i></a>
            @endauth
          </div>
        </h2>
        <div>
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Languages</h3>
            <div class="col-9 p-2">
              <ul class="m-0 ms-1 ps-3">
                @foreach($user->translateLanguages as $lang)
                  <li>{{ strtoupper($lang) }}</li>
                @endforeach
              </ul>
            </div>
          </div>
        </div>
      </section>
    @endif

    {{-- ===== Active status ===== --}}
    <section id="userActiveStatus" class="mt-3">
      <h2 class="h5 mb-0 atom-section-header">
        <div class="d-flex p-3 border-bottom text-primary">
          Account status
        </div>
      </h2>
      <div>
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Active</h3>
          <div class="col-9 p-2">
            @if($user->active)
              <span class="badge bg-success">Yes</span>
            @else
              <span class="badge bg-danger">No</span>
            @endif
          </div>
        </div>
      </div>
    </section>

  </section>

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
