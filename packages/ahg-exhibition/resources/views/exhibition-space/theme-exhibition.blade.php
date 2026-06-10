{{-- heratio#1186 Generative exhibitions (single-shot): theme prompt -> auto-built Exhibition Space. --}}
@extends('theme::layouts.1col')

@section('title', __('Generate an exhibition'))
@section('body-class', 'exhibition-space generate-theme')

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
    <h1 class="h4 mb-0"><i class="fas fa-wand-magic-sparkles me-2 text-primary"></i>{{ __('Generate an exhibition') }}</h1>
    <span class="text-muted small">{{ __('A theme in, a built show out') }}</span>
    <a href="{{ route('exhibition-space.browse') }}" class="btn btn-sm btn-outline-secondary ms-auto">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Exhibition spaces') }}
    </a>
  </div>

  <p class="text-muted small mb-3" style="max-width:760px">
    {{ __('Describe a theme. Heratio searches the catalogue for on-theme objects, the AI curates and orders the best of them into a narrative, and a real Exhibition Space is built with each object placed - ready for you to walk through or fine-tune in the builder.') }}
  </p>

  @if (session('error'))
    <div class="alert alert-warning" style="max-width:760px">{{ session('error') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger" style="max-width:760px">
      <ul class="mb-0">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('exhibition-space.generate.theme') }}" style="max-width:760px">
    @csrf

    <div class="mb-3">
      <label for="theme" class="form-label fw-semibold">{{ __('Theme / prompt') }}</label>
      <textarea id="theme" name="theme" class="form-control" rows="3" maxlength="200" required
                placeholder="{{ __('e.g. maps and exploration, women in the liberation struggle, Victorian furniture') }}">{{ old('theme') }}</textarea>
      <div class="form-text">{{ __('A few words or a short sentence describing the show you want.') }}</div>
    </div>

    <div class="row g-3">
      <div class="col-sm-4">
        <label for="max_objects" class="form-label fw-semibold">{{ __('Max objects') }}</label>
        <input type="number" id="max_objects" name="max_objects" class="form-control"
               min="1" max="48" value="{{ old('max_objects', 12) }}">
        <div class="form-text">{{ __('Upper limit on how many objects to place.') }}</div>
      </div>
      <div class="col-sm-8">
        <label for="building" class="form-label fw-semibold">{{ __('Building') }} <span class="text-muted fw-normal">({{ __('optional') }})</span></label>
        <input type="text" id="building" name="building" class="form-control" maxlength="120"
               value="{{ old('building') }}" placeholder="{{ __('e.g. Main Gallery, East Wing') }}">
        <div class="form-text">{{ __('Where the generated gallery space lives.') }}</div>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2 mt-4">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Generate exhibition') }}
      </button>
      <span class="small text-muted">{{ __('Creates a real Exhibition Space and opens it in the builder.') }}</span>
    </div>
  </form>
</div>
@endsection
