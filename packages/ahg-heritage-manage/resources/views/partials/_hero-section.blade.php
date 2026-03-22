{{-- Hero Section Partial --}}
<section class="heritage-hero py-5 text-white" style="background: linear-gradient(135deg, var(--ahg-primary) 0%, #1a1a2e 100%); min-height: 300px;">
  <div class="container text-center">
    <h1 class="display-4 mb-3">{{ $tagline ?? 'Discover Our Heritage' }}</h1>
    <p class="lead mb-4">{{ $subtext ?? '' }}</p>
    <form action="{{ route('heritage.search') }}" method="get" class="d-flex justify-content-center">
      <div class="input-group" style="max-width:600px">
        <input type="text" name="q" class="form-control form-control-lg" placeholder="{{ $searchPlaceholder ?? 'Search collections...' }}">
        <button class="btn btn-light btn-lg" type="submit"><i class="fas fa-search"></i></button>
      </div>
    </form>
  </div>
</section>