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
// Language mode (?lang=xx): the four hero text fields hold that language's text;
// blank fields fall back to the main text, which shows as the placeholder.
$lang = $lang ?? '';
$languages = $languages ?? [];
$langName = fn ($c) => ucfirst(\Locale::getDisplayLanguage($c, $c)) . ' (' . $c . ')';
$mainTagline = $heroTagline; $mainSubtext = $heroSubtext; $mainPlaceholder = $heroSearchPlaceholder; $mainSuggested = (array) $suggestedSearches;
if ($lang !== '') {
  $t = $translation ?? null;
  $heroTagline = $t->hero_tagline ?? '';
  $heroSubtext = $t->hero_subtext ?? '';
  $heroSearchPlaceholder = $t->hero_search_placeholder ?? '';
  $suggestedSearches = json_decode((string) ($t->suggested_searches ?? '[]'), true) ?: [];
}
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-heritage-manage::partials._admin-sidebar')
    <div class="list-group mt-4">
      <a href="#section-hero" class="list-group-item list-group-item-action">{{ __('Hero Section') }}</a>
      <a href="#section-sections" class="list-group-item list-group-item-action">{{ __('Sections') }}</a>
      <a href="#section-filters" class="list-group-item list-group-item-action">{{ __('Filters') }}</a>
      <a href="#section-stories" class="list-group-item list-group-item-action">{{ __('Stories') }}</a>
      <a href="#section-images" class="list-group-item list-group-item-action">{{ __('Hero Images') }}</a>
      <a href="#section-styling" class="list-group-item list-group-item-action">{{ __('Styling') }}</a>
    </div>
    <div class="mt-4">
      <a href="{{ route('heritage.landing') }}" class="btn atom-btn-white w-100" target="_blank"><i class="fas fa-eye me-2"></i>{{ __('Preview Landing Page') }}</a>
    </div>
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-cog me-2"></i>{{ __('Heritage Landing Configuration') }}</h1>

    @if(count($languages) > 1)
    <form method="get" action="{{ route('heritage.admin-config') }}" class="card border-0 shadow-sm mb-4">
      <div class="card-body d-flex flex-wrap align-items-center gap-2">
        <label for="lang" class="form-label mb-0 fw-bold"><i class="fas fa-language me-1"></i>{{ __('Language of the hero text') }}</label>
        <select id="lang" name="lang" class="form-select w-auto" onchange="this.form.submit()">
          <option value="" {{ $lang === '' ? 'selected' : '' }}>{{ __('Main text (all languages)') }}</option>
          @foreach($languages as $code)
          <option value="{{ $code }}" {{ $lang === $code ? 'selected' : '' }}>{{ $langName($code) }}</option>
          @endforeach
        </select>
        <noscript><button type="submit" class="btn btn-sm atom-btn-white">{{ __('Open') }}</button></noscript>
        <div class="form-text w-100 mb-0">
          @if($lang === '')
            {{ __('The main text is shown in every language that has no text of its own. Choose a language to give it its own tagline, subtext, search hint and suggested searches.') }}
          @else
            {{ __('Text for :language. Leave a field blank to show the main text (shown greyed in the field) in this language.', ['language' => $langName($lang)]) }}
          @endif
        </div>
      </div>
    </form>
    @endif

<form action="{{ route('heritage.admin-config') }}" method="post">@csrf
      <input type="hidden" name="lang" value="{{ $lang }}">

      <div class="card border-0 shadow-sm mb-4" id="section-hero">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h2 class="h5 mb-0">{{ __('Hero Section') }}</h2></div>
        <div class="card-body">
          <div class="mb-3"><label for="hero_tagline" class="form-label">{{ __('Tagline') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="text" class="form-control" id="hero_tagline" name="hero_tagline" value="{{ $heroTagline }}" maxlength="500" @if($lang !== '') placeholder="{{ $mainTagline }}" @endif><div class="form-text">{{ __('Main headline displayed in the hero section.') }}</div></div>
          <div class="mb-3"><label for="hero_subtext" class="form-label">{{ __('Subtext') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><textarea class="form-control" id="hero_subtext" name="hero_subtext" rows="2" @if($lang !== '') placeholder="{{ $mainSubtext }}" @endif>{{ $heroSubtext }}</textarea></div>
          <div class="mb-3"><label for="hero_search_placeholder" class="form-label">{{ __('Search Placeholder') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="text" class="form-control" id="hero_search_placeholder" name="hero_search_placeholder" value="{{ $heroSearchPlaceholder }}" @if($lang !== '') placeholder="{{ $mainPlaceholder }}" @endif></div>
          <div class="mb-3"><label for="suggested_searches" class="form-label">{{ __('Suggested Searches') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><textarea class="form-control" id="suggested_searches" name="suggested_searches" rows="4" placeholder="{{ $lang !== '' && $mainSuggested ? implode("\n", $mainSuggested) : __('One search term per line') }}">{{ implode("\n", (array)$suggestedSearches) }}</textarea><div class="form-text">{{ __('Enter one search suggestion per line.') }}</div></div>
          @if($lang === '')
          <div class="row">
            <div class="col-md-6 mb-3"><label for="hero_effect" class="form-label">{{ __('Background Effect') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><select class="form-select" id="hero_effect" name="hero_effect"><option value="kenburns" {{ $heroEffect==='kenburns'?'selected':'' }}>{{ __('Ken Burns') }}</option><option value="fade" {{ $heroEffect==='fade'?'selected':'' }}>{{ __('Fade') }}</option><option value="none" {{ $heroEffect==='none'?'selected':'' }}>{{ __('None') }}</option></select></div>
            <div class="col-md-6 mb-3"><label for="hero_rotation_seconds" class="form-label">{{ __('Image Rotation (seconds)') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="number" class="form-control" id="hero_rotation_seconds" name="hero_rotation_seconds" value="{{ (int)$heroRotationSeconds }}" min="1" max="60"></div>
          </div>
          @endif
        </div>
      </div>

      {{-- Sections, filters, images and styling are the same in every language. --}}
      @if($lang === '')

      <div class="card border-0 shadow-sm mb-4" id="section-sections">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h2 class="h5 mb-0">{{ __('Sections') }}</h2></div>
        <div class="card-body">
          <p class="text-muted">{{ __('Choose which sections to display on the landing page.') }}</p>
          @foreach([['show_filters','Explore By','Filter categories with top values',$showFilters],['show_curated_stories','Featured Stories','Curated collections and narratives',$showCuratedStories],['show_community_activity','Community Activity','Recent contributions',$showCommunityActivity],['show_recent_additions','Recently Added','Latest items carousel',$showRecentAdditions],['show_stats','Statistics','Collection counts',$showStats]] as [$field,$label,$desc,$val])
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="{{ $field }}" name="{{ $field }}" {{ $val ? 'checked' : '' }}>
            <label class="form-check-label" for="{{ $field }}"><strong>{{ $label }}</strong> - {{ $desc }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          </div>
          @endforeach
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4" id="section-filters">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h2 class="h5 mb-0">{{ __('Filters') }}</h2></div>
        <div class="card-body">
          <p class="text-muted">{{ __('Filters displayed in the "Explore By" section.') }}</p>
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
          <span class="badge bg-secondary">{{ __(':count stories', ['count' => count($stories ?? [])]) }}</span>
        </div>
        <div class="card-body">
          @if(empty($stories ?? []))
          <p class="text-muted text-center py-4">{{ __('No stories configured yet.') }}</p>
          @else
          <div class="list-group">
            @foreach($stories as $story)
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <strong>{{ $story->title ?? $story['title'] ?? 'Untitled' }}</strong>
                <span class="badge bg-light text-dark ms-2">{{ $story->story_type ?? $story['story_type'] ?? 'collection' }}</span>
                @if(!($story->is_enabled ?? $story['is_enabled'] ?? 1))<span class="badge bg-warning text-dark ms-2">{{ __('Disabled') }}</span>@endif
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
          <a href="{{ route('heritage.admin-hero-slides') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-cog me-1"></i>{{ __('Manage Hero Slides') }}</a>
        </div>
        <div class="card-body">
          @if(empty($heroImagesArray))
          <p class="text-muted text-center py-4">{{ __('No hero images configured. A gradient background will be used instead.') }}</p>
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
            <div class="col-md-6 mb-3"><label for="primary_color" class="form-label">{{ __('Primary Color') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><div class="input-group"><input type="color" class="form-control form-control-color" value="{{ $primaryColor }}" onchange="document.getElementById('cfg_primary_color').value=this.value;"><input type="text" class="form-control" id="cfg_primary_color" name="primary_color" value="{{ $primaryColor }}" pattern="#[0-9A-Fa-f]{6}"></div></div>
            <div class="col-md-6 mb-3"><label for="secondary_color" class="form-label">{{ __('Secondary Color') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><div class="input-group"><input type="color" class="form-control form-control-color" value="{{ $secondaryColor ?: '#6c757d' }}" onchange="document.getElementById('cfg_secondary_color').value=this.value;"><input type="text" class="form-control" id="cfg_secondary_color" name="secondary_color" value="{{ $secondaryColor }}" pattern="#[0-9A-Fa-f]{6}"></div></div>
          </div>
        </div>
      </div>
      @endif

      <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('heritage.landing') }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>
        <button type="submit" class="btn atom-btn-secondary"><i class="fas fa-check me-2"></i>{{ __('Save Configuration') }}</button>
      </div>
    </form>
  </div>
</div>
@endsection
