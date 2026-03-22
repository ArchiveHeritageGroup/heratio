@extends('theme::layouts.1col')

@section('title', 'Community Hub')
@section('body-class', 'registry index')

@section('content')

{{-- Hero banner --}}
<div class="text-white px-4 py-3 mb-4" style="background:var(--ahg-primary);">
  <div class="row align-items-center">
    <div class="col-lg-8">
      <h1 class="h3 fw-bold mb-1">{{ __('Community Hub') }}</h1>
      <p class="mb-2 small opacity-75">{{ __('The global directory for institutions, vendors, and archival software.') }}</p>
      <form method="get" action="{{ route('registry.search') }}">
        <div class="input-group input-group-sm" style="max-width:500px;">
          <input type="text" class="form-control" name="q" placeholder="{{ __('Search institutions, vendors, software...') }}">
          <button type="submit" class="btn atom-btn-white"><i class="fas fa-search"></i></button>
        </div>
      </form>
    </div>
    <div class="col-lg-4 d-none d-lg-block text-end">
      <span class="d-inline-flex gap-1 opacity-25">
        <i class="fas fa-earth-americas fa-4x"></i>
        <i class="fas fa-earth-europe fa-4x"></i>
        <i class="fas fa-earth-asia fa-4x"></i>
      </span>
    </div>
  </div>
</div>

{{-- Stats row --}}
@if(!empty($stats))
<div class="row g-3 mb-5">
  <div class="col-6 col-lg">
    <a href="{{ route('registry.institutionBrowse') }}" class="card text-center h-100 text-decoration-none">
      <div class="card-body">
        <div class="display-6 fw-bold text-primary">{{ number_format($stats['institutions'] ?? 0) }}</div>
        <div class="text-muted small">{{ __('Institutions') }}</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg">
    <a href="{{ route('registry.vendorBrowse') }}" class="card text-center h-100 text-decoration-none">
      <div class="card-body">
        <div class="display-6 fw-bold text-success">{{ number_format($stats['vendors'] ?? 0) }}</div>
        <div class="text-muted small">{{ __('Vendors') }}</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg">
    <a href="{{ route('registry.softwareBrowse') }}" class="card text-center h-100 text-decoration-none">
      <div class="card-body">
        <div class="display-6 fw-bold text-info">{{ number_format($stats['software'] ?? 0) }}</div>
        <div class="text-muted small">{{ __('Software') }}</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg">
    <a href="{{ route('registry.standardBrowse') }}" class="card text-center h-100 text-decoration-none">
      <div class="card-body">
        <div class="display-6 fw-bold text-danger">{{ number_format($stats['standards'] ?? 0) }}</div>
        <div class="text-muted small">{{ __('Standards') }}</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg">
    <a href="{{ route('registry.groupBrowse') }}" class="card text-center h-100 text-decoration-none">
      <div class="card-body">
        <div class="display-6 fw-bold text-warning">{{ number_format($stats['groups'] ?? 0) }}</div>
        <div class="text-muted small">{{ __('User Groups') }}</div>
      </div>
    </a>
  </div>
</div>
@endif

{{-- Featured institutions --}}
@if(!empty($featuredInstitutions) && $featuredInstitutions->count())
<div class="mb-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0">{{ __('Featured Institutions') }}</h2>
    <div>
      @auth
      <a href="{{ route('registry.myFavorites') }}" class="btn btn-sm btn-outline-warning me-1"><i class="fas fa-star me-1"></i>{{ __('All Favorites') }}</a>
      @endauth
      <a href="{{ route('registry.institutionBrowse') }}" class="btn btn-sm btn-outline-primary">{{ __('View All') }}</a>
    </div>
  </div>
  <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
    @foreach($featuredInstitutions as $inst)
      @include('ahg-registry::partials.institution-card', ['item' => $inst])
    @endforeach
  </div>
</div>
@endif

{{-- Featured vendors --}}
@if(!empty($featuredVendors) && $featuredVendors->count())
<div class="mb-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0">{{ __('Featured Vendors') }}</h2>
    <a href="{{ route('registry.vendorBrowse') }}" class="btn btn-sm btn-outline-primary">{{ __('View All') }}</a>
  </div>
  <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
    @foreach($featuredVendors as $v)
      @include('ahg-registry::partials.vendor-card', ['item' => $v])
    @endforeach
  </div>
</div>
@endif

{{-- Featured software --}}
@if(!empty($featuredSoftware) && $featuredSoftware->count())
<div class="mb-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0">{{ __('Featured Software') }}</h2>
    <a href="{{ route('registry.softwareBrowse') }}" class="btn btn-sm btn-outline-primary">{{ __('View All') }}</a>
  </div>
  <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
    @foreach($featuredSoftware as $sw)
      @include('ahg-registry::partials.software-card', ['item' => $sw])
    @endforeach
  </div>
</div>
@endif

<div class="row g-4 mb-5">
  {{-- Recent blog posts --}}
  <div class="col-lg-6">
    @if(!empty($recentBlog) && $recentBlog->count())
    <h2 class="h4 mb-3">{{ __('Recent Blog Posts') }}</h2>
    <div class="list-group list-group-flush">
      @foreach($recentBlog as $post)
      <a href="{{ route('registry.blogView', ['slug' => $post->slug ?? $post->id]) }}" class="list-group-item list-group-item-action">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h6 class="mb-1">{{ e($post->title) }}</h6>
            <small class="text-muted">{{ e($post->author_name ?? '') }} &middot; {{ \Carbon\Carbon::parse($post->published_at ?? $post->created_at)->format('M j, Y') }}</small>
          </div>
          <span class="badge bg-secondary">{{ e($post->category ?? 'news') }}</span>
        </div>
      </a>
      @endforeach
    </div>
    <div class="mt-2">
      <a href="{{ route('registry.blogList') }}" class="small">{{ __('View all posts') }} &rarr;</a>
    </div>
    @endif
  </div>

  {{-- Recent discussions --}}
  <div class="col-lg-6">
    @if(!empty($recentDiscussions) && $recentDiscussions->count())
    <h2 class="h4 mb-3">{{ __('Recent Discussions') }}</h2>
    <div class="list-group list-group-flush">
      @foreach($recentDiscussions as $disc)
      <div class="list-group-item">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h6 class="mb-1">{{ e($disc->title) }}</h6>
            <small class="text-muted">
              {{ e($disc->author_name ?? '') }}
              &middot; {{ (int) ($disc->reply_count ?? 0) }} {{ __('replies') }}
              &middot; {{ \Carbon\Carbon::parse($disc->created_at)->format('M j, Y') }}
            </small>
          </div>
          <span class="badge bg-info text-dark">{{ e($disc->topic_type ?? 'discussion') }}</span>
        </div>
      </div>
      @endforeach
    </div>
    <div class="mt-2">
      <a href="{{ route('registry.community') }}" class="small">{{ __('View community hub') }} &rarr;</a>
    </div>
    @endif
  </div>
</div>

{{-- Quick links --}}
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card border-primary h-100">
      <div class="card-body text-center">
        <i class="fas fa-university fa-2x text-primary mb-2"></i>
        <h5 class="card-title">{{ __('Register Institution') }}</h5>
        <p class="card-text small text-muted">{{ __('Add your institution to the global directory.') }}</p>
        <a href="{{ route('registry.institutionRegister') }}" class="btn btn-outline-primary btn-sm">{{ __('Register') }}</a>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-success h-100">
      <div class="card-body text-center">
        <i class="fas fa-handshake fa-2x text-success mb-2"></i>
        <h5 class="card-title">{{ __('Register as Vendor') }}</h5>
        <p class="card-text small text-muted">{{ __('List your services and reach institutions worldwide.') }}</p>
        <a href="{{ route('registry.vendorRegister') }}" class="btn btn-outline-success btn-sm">{{ __('Register') }}</a>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-info h-100">
      <div class="card-body text-center">
        <i class="fas fa-users fa-2x text-info mb-2"></i>
        <h5 class="card-title">{{ __('Browse Groups') }}</h5>
        <p class="card-text small text-muted">{{ __('Join user groups, participate in discussions, and collaborate.') }}</p>
        <a href="{{ route('registry.groupBrowse') }}" class="btn btn-outline-info btn-sm">{{ __('Browse') }}</a>
      </div>
    </div>
  </div>
</div>

{{-- Newsletter CTA --}}
<div class="card bg-light border-0 mt-4 mb-3">
  <div class="card-body py-4">
    <div class="row align-items-center">
      <div class="col-md-8">
        <h5 class="mb-1"><i class="fas fa-envelope text-primary me-2"></i>{{ __('Stay Connected') }}</h5>
        <p class="text-muted mb-0 small">{{ __('Subscribe to our newsletter for updates on new institutions, software releases, and community events.') }}</p>
      </div>
      <div class="col-md-4 text-md-end mt-2 mt-md-0">
        <a href="{{ route('registry.newsletterSubscribe') }}" class="btn atom-btn-white"><i class="fas fa-paper-plane me-1"></i> {{ __('Subscribe') }}</a>
      </div>
    </div>
  </div>
</div>

@endsection
