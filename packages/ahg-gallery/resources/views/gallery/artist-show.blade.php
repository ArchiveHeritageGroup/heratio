@extends('theme::layouts.1col')

@section('title', $artist->display_name ?? 'Artist')
@section('body-class', 'view gallery-artist')

@section('content')

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-user me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ $artist->display_name }}</h1>
      @if($artist->nationality || $artist->artist_type)
        <span class="small text-muted">
          @if($artist->artist_type){{ $artist->artist_type }}@endif
          @if($artist->artist_type && $artist->nationality) &mdash; @endif
          @if($artist->nationality){{ $artist->nationality }}@endif
        </span>
      @endif
    </div>
  </div>

  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('gallery.browse') }}">Gallery</a></li>
      <li class="breadcrumb-item"><a href="{{ route('gallery.artists') }}">Artists</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $artist->display_name }}</li>
    </ol>
  </nav>

  <div class="row">
    <div class="col-md-8">

      {{-- Biographical Information --}}
      <section class="mb-4">
        <h2 class="fs-5 border-bottom pb-2">{{ __('Biographical information') }}</h2>
        <div class="field-list">
          @if($artist->display_name)
            <div class="row mb-1">
              <div class="col-sm-4 fw-bold">Display name</div>
              <div class="col-sm-8">{{ $artist->display_name }}</div>
            </div>
          @endif
          @if($artist->sort_name && $artist->sort_name !== $artist->display_name)
            <div class="row mb-1">
              <div class="col-sm-4 fw-bold">Sort name</div>
              <div class="col-sm-8">{{ $artist->sort_name }}</div>
            </div>
          @endif
          @if($artist->birth_date)
            <div class="row mb-1">
              <div class="col-sm-4 fw-bold">Birth date</div>
              <div class="col-sm-8">{{ $artist->birth_date }}</div>
            </div>
          @endif
          @if($artist->birth_place)
            <div class="row mb-1">
              <div class="col-sm-4 fw-bold">Birth place</div>
              <div class="col-sm-8">{{ $artist->birth_place }}</div>
            </div>
          @endif
          @if($artist->death_date)
            <div class="row mb-1">
              <div class="col-sm-4 fw-bold">Death date</div>
              <div class="col-sm-8">{{ $artist->death_date }}</div>
            </div>
          @endif
          @if($artist->death_place)
            <div class="row mb-1">
              <div class="col-sm-4 fw-bold">Death place</div>
              <div class="col-sm-8">{{ $artist->death_place }}</div>
            </div>
          @endif
          @if($artist->nationality)
            <div class="row mb-1">
              <div class="col-sm-4 fw-bold">Nationality</div>
              <div class="col-sm-8">{{ $artist->nationality }}</div>
            </div>
          @endif
          @if($artist->active_period)
            <div class="row mb-1">
              <div class="col-sm-4 fw-bold">Active period</div>
              <div class="col-sm-8">{{ $artist->active_period }}</div>
            </div>
          @endif
        </div>
      </section>

      {{-- Artistic Practice --}}
      @if($artist->artist_type || $artist->medium_specialty || $artist->movement_style)
        <section class="mb-4">
          <h2 class="fs-5 border-bottom pb-2">{{ __('Artistic practice') }}</h2>
          <div class="field-list">
            @if($artist->artist_type)
              <div class="row mb-1">
                <div class="col-sm-4 fw-bold">Artist type</div>
                <div class="col-sm-8">{{ $artist->artist_type }}</div>
              </div>
            @endif
            @if($artist->medium_specialty)
              <div class="row mb-1">
                <div class="col-sm-4 fw-bold">Medium / Specialty</div>
                <div class="col-sm-8">{{ $artist->medium_specialty }}</div>
              </div>
            @endif
            @if($artist->movement_style)
              <div class="row mb-1">
                <div class="col-sm-4 fw-bold">Movement / Style</div>
                <div class="col-sm-8">{{ $artist->movement_style }}</div>
              </div>
            @endif
          </div>
        </section>
      @endif

      {{-- Representation --}}
      @if($artist->represented)
        <section class="mb-4">
          <h2 class="fs-5 border-bottom pb-2">{{ __('Representation') }}</h2>
          <div class="field-list">
            <div class="row mb-1">
              <div class="col-sm-4 fw-bold">Represented by</div>
              <div class="col-sm-8">{{ $artist->represented }}</div>
            </div>
          </div>
        </section>
      @endif

      {{-- Biography --}}
      @if($artist->biography)
        <section class="mb-4">
          <h2 class="fs-5 border-bottom pb-2">{{ __('Biography') }}</h2>
          <div>{!! nl2br(e($artist->biography)) !!}</div>
        </section>
      @endif

      {{-- Artist Statement --}}
      @if($artist->artist_statement)
        <section class="mb-4">
          <h2 class="fs-5 border-bottom pb-2">{{ __('Artist statement') }}</h2>
          <div>{!! nl2br(e($artist->artist_statement)) !!}</div>
        </section>
      @endif

      {{-- CV --}}
      @if($artist->cv)
        <section class="mb-4">
          <h2 class="fs-5 border-bottom pb-2">{{ __('Curriculum Vitae') }}</h2>
          <div>{!! nl2br(e($artist->cv)) !!}</div>
        </section>
      @endif

      {{-- Artworks by this artist --}}
      @if(isset($artist->artworks) && $artist->artworks->isNotEmpty())
        <section class="mb-4">
          <h2 class="fs-5 border-bottom pb-2">Artworks ({{ $artist->artworks->count() }})</h2>
          <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
            @foreach($artist->artworks as $aw)
              <div class="col">
                <div class="card h-100">
                  <div class="card-body p-2">
                    <h6 class="card-title mb-1">
                      <a href="{{ route('gallery.show', $aw->slug) }}">{{ $aw->title ?: '[Untitled]' }}</a>
                    </h6>
                    @if($aw->work_type)
                      <span class="badge bg-secondary">{{ $aw->work_type }}</span>
                    @endif
                    @if($aw->creation_date_display)
                      <small class="text-muted d-block">{{ $aw->creation_date_display }}</small>
                    @endif
                    @if($aw->materials)
                      <small class="text-muted d-block">{{ $aw->materials }}</small>
                    @endif
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        </section>
      @endif

      {{-- Notes --}}
      @if($artist->notes)
        <section class="mb-4">
          <h2 class="fs-5 border-bottom pb-2">{{ __('Notes') }}</h2>
          <div>{!! nl2br(e($artist->notes)) !!}</div>
        </section>
      @endif

    </div>

    <div class="col-md-4">
      {{-- Contact information --}}
      @if($artist->email || $artist->phone || $artist->website || $artist->studio_address)
        <div class="card mb-3">
          <div class="card-header fw-bold">
            <i class="fas fa-address-card me-1"></i> Contact
          </div>
          <div class="list-group list-group-flush">
            @if($artist->email)
              <div class="list-group-item small">
                <i class="fas fa-envelope me-1"></i>
                <a href="mailto:{{ $artist->email }}">{{ $artist->email }}</a>
              </div>
            @endif
            @if($artist->phone)
              <div class="list-group-item small">
                <i class="fas fa-phone me-1"></i> {{ $artist->phone }}
              </div>
            @endif
            @if($artist->website)
              <div class="list-group-item small">
                <i class="fas fa-globe me-1"></i>
                <a href="{{ $artist->website }}" target="_blank" rel="noopener">{{ $artist->website }}</a>
              </div>
            @endif
            @if($artist->studio_address)
              <div class="list-group-item small">
                <i class="fas fa-map-marker-alt me-1"></i> {{ $artist->studio_address }}
              </div>
            @endif
          </div>
        </div>
      @endif

      {{-- Social media --}}
      @if($artist->instagram || $artist->twitter || $artist->facebook)
        <div class="card mb-3">
          <div class="card-header fw-bold">
            <i class="fas fa-share-alt me-1"></i> Social media
          </div>
          <div class="list-group list-group-flush">
            @if($artist->instagram)
              <div class="list-group-item small">
                <i class="fab fa-instagram me-1"></i>
                <a href="https://instagram.com/{{ ltrim($artist->instagram, '@') }}" target="_blank" rel="noopener">{{ $artist->instagram }}</a>
              </div>
            @endif
            @if($artist->twitter)
              <div class="list-group-item small">
                <i class="fab fa-twitter me-1"></i>
                <a href="https://twitter.com/{{ ltrim($artist->twitter, '@') }}" target="_blank" rel="noopener">{{ $artist->twitter }}</a>
              </div>
            @endif
            @if($artist->facebook)
              <div class="list-group-item small">
                <i class="fab fa-facebook me-1"></i>
                <a href="{{ $artist->facebook }}" target="_blank" rel="noopener">{{ $artist->facebook }}</a>
              </div>
            @endif
          </div>
        </div>
      @endif

      {{-- Status --}}
      <div class="card mb-3">
        <div class="card-header fw-bold">
          <i class="fas fa-info-circle me-1"></i> Status
        </div>
        <div class="card-body p-2">
          <span class="badge {{ $artist->is_active ? 'bg-success' : 'bg-secondary' }}">
            {{ $artist->is_active ? 'Active' : 'Inactive' }}
          </span>
          @if($artist->created_at)
            <small class="text-muted d-block mt-1">Created: {{ \Carbon\Carbon::parse($artist->created_at)->format('Y-m-d') }}</small>
          @endif
          @if($artist->updated_at)
            <small class="text-muted d-block">Updated: {{ \Carbon\Carbon::parse($artist->updated_at)->format('Y-m-d') }}</small>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
