{{--
  Plugin Management — card grid with category/status filters
  Cloned from AtoM ahgSettingsPlugin pluginsSuccess.php

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Plugin Management')
@section('body-class', 'admin plugins')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-puzzle-piece"></i> Plugin Management</h1>
  <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i>Back to AHG Settings
  </a>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white">
    <div class="row align-items-center">
      <div class="col-auto">
        <strong><i class="fas fa-filter me-2"></i>Category</strong>
        <div class="btn-group btn-group-sm ms-2" role="group">
          <button type="button" class="btn btn-outline-primary active" data-filter="all">All</button>
          @foreach($categories as $key => $cat)
          <button type="button" class="btn btn-outline-{{ $cat['class'] }}" data-filter="{{ $key }}">
            <i class="fas {{ $cat['icon'] }} me-1"></i>{{ $cat['label'] }}
          </button>
          @endforeach
        </div>
      </div>
      <div class="col-auto">
        <strong><i class="fas fa-toggle-on me-2"></i>Status</strong>
        <div class="btn-group btn-group-sm ms-2" role="group">
          <button type="button" class="btn btn-outline-primary active" data-status="all">All</button>
          <button type="button" class="btn btn-outline-success" data-status="enabled"><i class="fas fa-check me-1"></i>Enabled</button>
          <button type="button" class="btn btn-outline-secondary" data-status="disabled"><i class="fas fa-times me-1"></i>Disabled</button>
        </div>
      </div>
      <div class="col-auto ms-auto">
        @php $enabledCount = $plugins->where('is_enabled', 1)->count(); $disabledCount = $plugins->where('is_enabled', 0)->count(); @endphp
        <span class="badge bg-success">{{ $enabledCount }} Enabled</span>
        <span class="badge bg-secondary">{{ $disabledCount }} Disabled</span>
        <span class="badge bg-primary">{{ $plugins->count() }} Total</span>
      </div>
    </div>
  </div>
</div>

<div class="row" id="plugins-grid">
  @forelse($plugins as $plugin)
  @php
    $isEnabled = (bool) $plugin->is_enabled;
    $category = $plugin->category ?? 'other';
    $catInfo = $categories[$category] ?? $categories['other'];
  @endphp
  <div class="col-lg-4 col-md-6 mb-4 plugin-card"
       data-category="{{ $category }}"
       data-status="{{ $isEnabled ? 'enabled' : 'disabled' }}">
    <div class="card h-100 {{ $isEnabled ? '' : 'border-secondary opacity-75' }}">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="badge bg-{{ $catInfo['class'] }}">
          <i class="fas {{ $catInfo['icon'] }} me-1"></i>{{ $catInfo['label'] }}
        </span>
        <span class="badge {{ $isEnabled ? 'bg-success' : 'bg-secondary' }}">
          {{ $isEnabled ? 'Enabled' : 'Disabled' }}
        </span>
      </div>
      <div class="card-body">
        <h5 class="card-title">
          <i class="fas fa-plug me-2 text-muted"></i>{{ e($plugin->name) }}
        </h5>
        <p class="card-text text-muted small">
          {{ e($plugin->description ?? 'No description available') }}
        </p>
        @php
          // Find the preferred help article for this plugin.
          // Strategy:
          //   1. exact related_plugin match (best — explicit curation)
          //   2. fuzzy fallback: slug contains plugin-stem as a token (handles unlinked articles)
          //
          // Stem derivation: "ahgAccessionManagePlugin" → "accession" (drop ahg/Manage/Plugin
          // suffixes to get the topic word). Compound stems like "AiCondition" yield
          // "ai-condition" so kebab-case slugs match.
          $__helpRow = null;
          $__stem = '';
          try {
              $__bare = preg_replace(['/^ahg/', '/(Manage)?Plugin$/'], '', $plugin->name);
              $__stem = strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $__bare));

              // Pass 1: explicit related_plugin link
              $__helpRow = \Illuminate\Support\Facades\DB::table('help_article')
                  ->where('related_plugin', $plugin->name)
                  ->where('is_published', 1)
                  ->orderByRaw(
                      "CASE
                          WHEN slug = ? THEN 0
                          WHEN slug LIKE ? AND slug LIKE '%-user-guide' THEN 1
                          WHEN slug LIKE '%-user-guide' THEN 2
                          ELSE 3
                       END",
                      [$__stem . '-user-guide', '%' . $__stem . '%']
                  )
                  ->orderBy('sort_order')
                  ->first(['slug', 'title']);

              // Pass 2: fuzzy-fallback by slug if nothing was explicitly linked
              if (! $__helpRow && strlen($__stem) >= 3) {
                  $__helpRow = \Illuminate\Support\Facades\DB::table('help_article')
                      ->where('is_published', 1)
                      ->where(function ($q) use ($__stem) {
                          $q->where('slug', 'LIKE', $__stem . '-%')
                            ->orWhere('slug', 'LIKE', '%-' . $__stem . '-%')
                            ->orWhere('slug', 'LIKE', '%-' . $__stem)
                            ->orWhere('slug', '=',     $__stem);
                      })
                      ->orderByRaw(
                          "CASE WHEN slug LIKE '%-user-guide' THEN 0 ELSE 1 END"
                      )
                      ->orderBy('sort_order')
                      ->first(['slug', 'title']);
              }
          } catch (\Throwable $e) { /* help table not present */ }
        @endphp
        <div class="d-flex justify-content-between align-items-center mt-2 small">
          @if(!empty($plugin->version))
            <span class="text-muted"><i class="fas fa-code-branch me-1"></i>v{{ e($plugin->version) }}</span>
          @else
            <span></span>
          @endif
          @if($__helpRow)
            <a href="{{ url('/help/article/' . $__helpRow->slug) }}" class="text-decoration-none" title="{{ e($__helpRow->title) }}">
              <i class="fas fa-book-open me-1"></i>Help
            </a>
          @endif
        </div>
      </div>
      <div class="card-footer bg-white">
        <form method="post" action="{{ route('settings.plugins') }}" class="d-inline">
          @csrf
          <input type="hidden" name="plugin_name" value="{{ e($plugin->name) }}">
          @if($plugin->is_core)
            <span class="badge bg-primary"><i class="fas fa-shield-alt me-1"></i>Core</span>
          @elseif($plugin->is_locked)
            <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i>Locked</span>
          @elseif($isEnabled)
            <button type="submit" name="plugin_action" value="disable"
                    class="btn btn-sm btn-outline-danger btn-plugin-disable"
                    data-plugin-name="{{ e($plugin->name) }}">
              <i class="fas fa-power-off me-1"></i>Disable
            </button>
          @else
            <button type="submit" name="plugin_action" value="enable"
                    class="btn btn-sm btn-success">
              <i class="fas fa-check me-1"></i>Enable
            </button>
          @endif
        </form>
      </div>
    </div>
  </div>
  @empty
  <div class="col-12">
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-2"></i>No plugins found in database.
    </div>
  </div>
  @endforelse
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var activeCategory = 'all';
    var activeStatus = 'all';
    var cards = document.querySelectorAll('.plugin-card');

    function filterCards() {
        cards.forEach(function(card) {
            var catMatch = (activeCategory === 'all' || card.dataset.category === activeCategory);
            var statusMatch = (activeStatus === 'all' || card.dataset.status === activeStatus);
            card.style.display = (catMatch && statusMatch) ? '' : 'none';
        });
    }

    document.querySelectorAll('.card-header [data-filter]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.card-header [data-filter]').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            activeCategory = btn.dataset.filter;
            filterCards();
        });
    });

    document.querySelectorAll('.card-header [data-status]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.card-header [data-status]').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            activeStatus = btn.dataset.status;
            filterCards();
        });
    });

    document.querySelectorAll('.btn-plugin-disable').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (!confirm('Disable ' + btn.dataset.pluginName + '?')) {
                e.preventDefault();
            }
        });
    });
});
</script>
@endsection
