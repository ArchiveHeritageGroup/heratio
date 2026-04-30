@extends('theme::layouts.1col')
@section('title', 'Heritage Landing Configuration')
@section('body-class', 'admin heritage')

@php
$config = $config ?? (object)[];
$heroTagline = $config->hero_tagline ?? 'Discover Our Heritage';
$heroSubtext = $config->hero_subtext ?? '';
$heroSearchPlaceholder = $config->hero_search_placeholder ?? 'What are you looking for?';
$suggestedSearches = $config->suggested_searches ?? [];
if(is_string($suggestedSearches)) $suggestedSearches = json_decode($suggestedSearches, true) ?: [];
$heroRotationSeconds = $config->hero_rotation_seconds ?? 8;
$heroEffect = $config->hero_effect ?? 'kenburns';
$showCuratedStories = $config->show_curated_stories ?? 1;
$showCommunityActivity = $config->show_community_activity ?? 1;
$showFilters = $config->show_filters ?? 1;
$showStats = $config->show_stats ?? 1;
$showRecentAdditions = $config->show_recent_additions ?? 1;
$primaryColor = $config->primary_color ?? '#0d6efd';
$secondaryColor = $config->secondary_color ?? '';
$heroImagesArray = $heroImages ?? [];
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-heritage-manage::partials._admin-sidebar')
    <div class="list-group mt-4">
      <a href="#section-hero" class="list-group-item list-group-item-action">Hero Section</a>
      <a href="#section-sections" class="list-group-item list-group-item-action">Sections</a>
      <a href="#section-filters" class="list-group-item list-group-item-action">Filters</a>
      <a href="#section-stories" class="list-group-item list-group-item-action">Stories</a>
      <a href="#section-images" class="list-group-item list-group-item-action">Hero Images</a>
      <a href="#section-styling" class="list-group-item list-group-item-action">Styling</a>
    </div>
    <div class="mt-4">
      <a href="{{ route('heritage.landing') }}" class="btn atom-btn-white w-100" target="_blank"><i class="fas fa-eye me-2"></i>Preview Landing Page</a>
    </div>
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-cog me-2"></i>Heritage Landing Configuration</h1>

<form action="{{ route('heritage.admin-config') }}" method="post">@csrf

      <div class="card border-0 shadow-sm mb-4" id="section-hero">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h2 class="h5 mb-0">{{ __('Hero Section') }}</h2></div>
        <div class="card-body">
          <div class="mb-3"><label for="hero_tagline" class="form-label">Tagline <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" id="hero_tagline" name="hero_tagline" value="{{ $heroTagline }}" maxlength="500"><div class="form-text">Main headline displayed in the hero section.</div></div>
          <div class="mb-3"><label for="hero_subtext" class="form-label">Subtext <span class="badge bg-secondary ms-1">Optional</span></label><textarea class="form-control" id="hero_subtext" name="hero_subtext" rows="2">{{ $heroSubtext }}</textarea></div>
          <div class="mb-3"><label for="hero_search_placeholder" class="form-label">Search Placeholder <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" id="hero_search_placeholder" name="hero_search_placeholder" value="{{ $heroSearchPlaceholder }}"></div>
          <div class="mb-3"><label for="suggested_searches" class="form-label">Suggested Searches <span class="badge bg-secondary ms-1">Optional</span></label><textarea class="form-control" id="suggested_searches" name="suggested_searches" rows="4" placeholder="{{ __('One search term per line') }}">{{ implode("\n", (array)$suggestedSearches) }}</textarea><div class="form-text">Enter one search suggestion per line.</div></div>
          <div class="row">
            <div class="col-md-6 mb-3"><label for="hero_effect" class="form-label">Background Effect <span class="badge bg-secondary ms-1">Optional</span></label><select class="form-select" id="hero_effect" name="hero_effect"><option value="kenburns" {{ $heroEffect==='kenburns'?'selected':'' }}>{{ __('Ken Burns') }}</option><option value="fade" {{ $heroEffect==='fade'?'selected':'' }}>{{ __('Fade') }}</option><option value="none" {{ $heroEffect==='none'?'selected':'' }}>{{ __('None') }}</option></select></div>
            <div class="col-md-6 mb-3"><label for="hero_rotation_seconds" class="form-label">Image Rotation (seconds) <span class="badge bg-secondary ms-1">Optional</span></label><input type="number" class="form-control" id="hero_rotation_seconds" name="hero_rotation_seconds" value="{{ (int)$heroRotationSeconds }}" min="1" max="60"></div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4" id="section-sections">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h2 class="h5 mb-0">{{ __('Sections') }}</h2></div>
        <div class="card-body">
          <p class="text-muted">Choose which sections to display on the landing page.</p>
          @foreach([['show_filters','Explore By','Filter categories with top values',$showFilters],['show_curated_stories','Featured Stories','Curated collections and narratives',$showCuratedStories],['show_community_activity','Community Activity','Recent contributions',$showCommunityActivity],['show_recent_additions','Recently Added','Latest items carousel',$showRecentAdditions],['show_stats','Statistics','Collection counts',$showStats]] as [$field,$label,$desc,$val])
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="{{ $field }}" name="{{ $field }}" {{ $val ? 'checked' : '' }}>
            <label class="form-check-label" for="{{ $field }}"><strong>{{ $label }}</strong> - {{ $desc }} <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
          @endforeach
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4" id="section-filters">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h2 class="h5 mb-0">{{ __('Filters') }}</h2></div>
        <div class="card-body">
          <p class="text-muted">Filters displayed in the "Explore By" section.</p>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead><tr><th style="width:30px"></th><th>{{ __('Filter') }}</th><th>{{ __('Source') }}</th><th class="text-center">{{ __('Landing') }}</th><th class="text-center">{{ __('Search') }}</th><th class="text-center">{{ __('Enabled') }}</th></tr></thead>
              <tbody>
                @foreach($filters ?? [] as $filter)
                <tr>
                  <td><i class="fas fa-grip-vertical text-muted"></i></td>
                  <td><i class="{{ $filter['icon'] ?? 'fa-tag' }} me-2"></i>{{ $filter['label'] }}</td>
                  <td><small class="text-muted">{{ $filter['source_type'] }}</small></td>
                  <td class="text-center"><i class="fas {{ $filter['show_on_landing'] ? 'fa-check-circle text-success' : 'fa-minus-circle text-muted' }}"></i></td>
                  <td class="text-center"><i class="fas {{ $filter['show_in_search'] ? 'fa-check-circle text-success' : 'fa-minus-circle text-muted' }}"></i></td>
                  <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" disabled {{ ($filter['is_enabled'] ?? true) ? 'checked' : '' }}></div></td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4" id="section-stories">
        <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
          <h2 class="h5 mb-0">{{ __('Featured Stories') }}</h2>
          <span class="badge bg-secondary">{{ count($stories ?? []) }} stories</span>
        </div>
        <div class="card-body">
          @if(empty($stories ?? []))
          <p class="text-muted text-center py-4">No stories configured yet.</p>
          @else
          <div class="list-group">
            @foreach($stories as $story)
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <strong>{{ $story->title ?? $story['title'] ?? 'Untitled' }}</strong>
                <span class="badge bg-light text-dark ms-2">{{ $story->story_type ?? $story['story_type'] ?? 'collection' }}</span>
                @if(!($story->is_enabled ?? $story['is_enabled'] ?? 1))<span class="badge bg-warning text-dark ms-2">Disabled</span>@endif
              </div>
            </div>
            @endforeach
          </div>
          @endif
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4" id="section-images">
        <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
          <h2 class="h5 mb-0">{{ __('Hero Images') }}</h2>
          <a href="{{ route('heritage.admin-hero-slides') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-cog me-1"></i>Manage Hero Slides</a>
        </div>
        <div class="card-body">
          @if(empty($heroImagesArray))
          <p class="text-muted text-center py-4">No hero images configured. A gradient background will be used instead.</p>
          @else
          <div class="row g-3">
            @foreach((array)$heroImagesArray as $image)
            <div class="col-md-4"><div class="card h-100"><img src="{{ $image['image_path'] ?? '' }}" class="card-img-top" style="height:120px;object-fit:cover;" alt="{{ __('Hero image') }}"><div class="card-body p-2"><small class="text-muted">{{ $image['title'] ?? $image['caption'] ?? 'No title' }}</small></div></div></div>
            @endforeach
          </div>
          @endif
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4" id="section-styling">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h2 class="h5 mb-0">{{ __('Styling') }}</h2></div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3"><label for="primary_color" class="form-label">Primary Color <span class="badge bg-secondary ms-1">Optional</span></label><div class="input-group"><input type="color" class="form-control form-control-color" value="{{ $primaryColor }}" onchange="document.getElementById('cfg_primary_color').value=this.value;"><input type="text" class="form-control" id="cfg_primary_color" name="primary_color" value="{{ $primaryColor }}" pattern="#[0-9A-Fa-f]{6}"></div></div>
            <div class="col-md-6 mb-3"><label for="secondary_color" class="form-label">Secondary Color <span class="badge bg-secondary ms-1">Optional</span></label><div class="input-group"><input type="color" class="form-control form-control-color" value="{{ $secondaryColor ?: '#6c757d' }}" onchange="document.getElementById('cfg_secondary_color').value=this.value;"><input type="text" class="form-control" id="cfg_secondary_color" name="secondary_color" value="{{ $secondaryColor }}" pattern="#[0-9A-Fa-f]{6}"></div></div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('heritage.landing') }}" class="btn atom-btn-white">Cancel</a>
        <button type="submit" class="btn atom-btn-secondary"><i class="fas fa-check me-2"></i>Save Configuration</button>
      </div>
    </form>
  </div>
</div>
@endsection
