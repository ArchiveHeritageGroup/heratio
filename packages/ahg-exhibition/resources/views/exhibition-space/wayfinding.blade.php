{{--
  Wayfinding floor plan + directory (heratio#1217) - first slice of the
  building-scale museum twin.

  A read-only 2D top-down "you are here / take me to X" plan: rooms/zones drawn
  as blocks (server-rendered inline SVG from the placement geometry), each placed
  object as a labelled dot coloured by wall/zone, beside a searchable directory.
  Picking an object highlights its dot and offers a deep-link into the 3D
  walkthrough (?focus=<ioId>). Self-hosted only: inline SVG + vanilla JS, no libs.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3.0 or later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Wayfinding') . ' - ' . $space->name)
@section('body-class', 'exhibition-space wayfinding')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1"><i class="fas fa-map-location-dot me-2"></i>{{ __('Wayfinding') }} <small class="text-muted">{{ $space->name }}</small></h1>
    @include('ahg-exhibition::exhibition-space._nav-actions', ['space' => $space, 'current' => 'wayfinding'])
  </div>
  <p class="text-muted small mb-3">{{ __('A top-down floor plan of this space. Search the directory to find an object, see where it is on the plan, then step into the walkthrough to go to it.') }}</p>

  <div class="row g-3">
    {{-- ---- Floor plan (inline SVG) ---- --}}
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
          <strong><i class="fas fa-vector-square me-1"></i>{{ __('Floor plan') }}</strong>
          <span class="small text-muted">
            {{ trans_choice('{0}No rooms|{1}:count room|[2,*]:count rooms', $plan['room_count'], ['count' => $plan['room_count']]) }}
            &middot;
            {{ trans_choice('{0}No objects placed|{1}:count object|[2,*]:count objects', $plan['object_count'], ['count' => $plan['object_count']]) }}
          </span>
        </div>
        <div class="card-body">
          @if($plan['room_count'] === 0 && $plan['object_count'] === 0)
            {{-- Dignified empty state: nothing placed yet. --}}
            <div class="text-center text-muted py-5">
              <i class="fas fa-map-location-dot fa-2x mb-3 d-block opacity-50"></i>
              <p class="mb-1">{{ __('This space has no rooms or placed objects yet.') }}</p>
              <p class="small mb-0">{{ __('Use the Builder or Building Plan to lay out rooms and place objects, and they will appear here.') }}</p>
            </div>
          @else
            @if($plan['has_grid_fallback'])
              <p class="small text-muted mb-2"><i class="fas fa-circle-info me-1"></i>{{ __('Some objects have not been positioned on the plan yet. They are shown in a grid below the rooms.') }}</p>
            @endif
            <div id="wfPlanWrap" class="w-100" style="overflow:auto;">
              <svg id="wfPlan" viewBox="0 0 {{ $plan['view_w'] }} {{ $plan['view_h'] }}"
                   width="100%" style="max-width:100%;height:auto;background:#f7f8fa;border-radius:.375rem;border:1px solid #e3e6ea;"
                   role="img" aria-label="{{ __('Floor plan of') }} {{ $space->name }}"
                   preserveAspectRatio="xMidYMid meet">
                {{-- Rooms / zones as blocks --}}
                @foreach($plan['rooms'] as $r)
                  <g class="wf-room">
                    <rect x="{{ $r['x'] }}" y="{{ $r['y'] }}" width="{{ $r['w'] }}" height="{{ $r['h'] }}"
                          rx="4" fill="{{ $r['is_current'] ? '#e7f1ff' : '#ffffff' }}"
                          stroke="{{ $r['is_current'] ? '#0d6efd' : '#aeb6bf' }}" stroke-width="{{ $r['is_current'] ? 2 : 1.2 }}"></rect>
                  </g>
                @endforeach
                {{-- Room labels drawn after blocks so they sit on top --}}
                @foreach($plan['rooms'] as $r)
                  <text x="{{ $r['cx'] }}" y="{{ $r['y'] + 16 }}" text-anchor="middle"
                        font-size="12" font-weight="600" fill="#495057">{{ \Illuminate\Support\Str::limit($r['name'], 28) }}</text>
                  @if($r['is_current'])
                    <text x="{{ $r['cx'] }}" y="{{ $r['y'] + 30 }}" text-anchor="middle" font-size="9.5" fill="#0d6efd">{{ __('You are here') }}</text>
                  @endif
                @endforeach
                {{-- Object dots (coloured by zone). Each carries data-dot for the directory highlight. --}}
                @foreach($plan['dots'] as $d)
                  <circle class="wf-dot" data-dot="{{ $d['dot_id'] }}" data-io="{{ $d['io_id'] }}"
                          cx="{{ $d['x'] }}" cy="{{ $d['y'] }}" r="5"
                          fill="{{ $d['color'] }}" stroke="#fff" stroke-width="1.5"
                          tabindex="0" role="button"
                          aria-label="{{ $d['title'] }} - {{ $d['room'] }}">
                    <title>{{ $d['title'] }} &middot; {{ $d['room'] }} ({{ $d['zone_label'] }})</title>
                  </circle>
                @endforeach
                @if($plan['has_grid_fallback'])
                  <line x1="0" y1="{{ $plan['plan_h'] + 8 }}" x2="{{ $plan['view_w'] }}" y2="{{ $plan['plan_h'] + 8 }}" stroke="#dee2e6" stroke-dasharray="4 3"></line>
                  <text x="{{ \intval($plan['view_w']) / 2 }}" y="{{ $plan['plan_h'] + 26 }}" text-anchor="middle" font-size="10.5" fill="#868e96">{{ __('Objects not yet positioned') }}</text>
                @endif
              </svg>
            </div>
            {{-- Zone legend --}}
            @if(!empty($plan['legend']))
              <div class="d-flex flex-wrap gap-3 mt-2 small">
                @foreach($plan['legend'] as $lg)
                  <span class="d-inline-flex align-items-center">
                    <span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:{{ $lg['color'] }};"></span>{{ $lg['label'] }}
                  </span>
                @endforeach
              </div>
            @endif
          @endif
        </div>
      </div>
      <div class="mt-3 d-flex flex-wrap gap-2">
        <a href="{{ $plan['walkthrough_url'] }}" class="btn btn-primary"><i class="fas fa-vr-cardboard me-1"></i>{{ __('Walk through this space') }}</a>
        <a href="{{ route('exhibition-space.show', ['slug' => $space->slug]) }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to the space') }}</a>
      </div>
    </div>

    {{-- ---- Searchable object directory ("take me to X") ---- --}}
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header py-2"><strong><i class="fas fa-magnifying-glass me-1"></i>{{ __('Directory') }}</strong></div>
        <div class="card-body p-2">
          @if($plan['object_count'] === 0)
            <p class="text-muted small mb-0">{{ __('No objects are placed in this space yet.') }}</p>
          @else
            <div class="mb-2">
              <input type="search" id="wfFilter" class="form-control form-control-sm"
                     placeholder="{{ __('Search objects, e.g. take me to ...') }}"
                     aria-label="{{ __('Filter objects') }}" autocomplete="off">
            </div>
            <div id="wfList" class="list-group list-group-flush small" style="max-height:62vh;overflow:auto;">
              @foreach($plan['directory'] as $d)
                <div class="list-group-item list-group-item-action px-2 py-2 wf-entry d-flex align-items-start gap-2"
                     data-dot="{{ $d['dot_id'] }}" data-io="{{ $d['io_id'] }}"
                     data-search="{{ \Illuminate\Support\Str::lower($d['title'] . ' ' . $d['room'] . ' ' . $d['zone_label']) }}"
                     role="button" tabindex="0">
                  <span class="d-inline-block rounded-circle mt-1 flex-shrink-0" style="width:11px;height:11px;background:{{ $d['color'] }};"></span>
                  <span class="flex-grow-1">
                    <span class="d-block fw-semibold text-truncate">{{ $d['title'] }}</span>
                    <span class="d-block text-muted" style="font-size:.78rem;">{{ $d['room'] }} &middot; {{ $d['zone_label'] }}</span>
                    <span class="d-flex flex-wrap gap-2 mt-1">
                      <a class="link-primary" href="{{ $plan['walkthrough_url'] }}?focus={{ $d['io_id'] }}"><i class="fas fa-person-walking me-1"></i>{{ __('View in the walkthrough') }}</a>
                      @if(!empty($d['record_url']))
                        <a class="link-secondary" href="{{ $d['record_url'] }}"><i class="fas fa-file-lines me-1"></i>{{ __('Record') }}</a>
                      @endif
                    </span>
                  </span>
                </div>
              @endforeach
            </div>
            <p id="wfNoMatch" class="text-muted small mt-2 mb-0 d-none">{{ __('No objects match your search.') }}</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  @if($plan['object_count'] > 0)
  <style nonce="{{ $cspNonce ?? '' }}">
    .wf-dot { cursor:pointer; transition:r .1s ease; }
    .wf-dot.wf-active { r:9; stroke:#212529; stroke-width:2.5; }
    .wf-dot.wf-dim { opacity:.25; }
    .wf-entry.wf-active { background:#e7f1ff; }
  </style>
  <script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    var svg = document.getElementById('wfPlan');
    var list = document.getElementById('wfList');
    var filter = document.getElementById('wfFilter');
    var noMatch = document.getElementById('wfNoMatch');
    if (!list) return;

    function dot(id) { return svg ? svg.querySelector('.wf-dot[data-dot="' + id + '"]') : null; }
    function entry(id) { return list.querySelector('.wf-entry[data-dot="' + id + '"]'); }

    function clearActive() {
      if (svg) svg.querySelectorAll('.wf-dot.wf-active').forEach(function (n) { n.classList.remove('wf-active'); });
      list.querySelectorAll('.wf-entry.wf-active').forEach(function (n) { n.classList.remove('wf-active'); });
    }
    function highlight(id, scroll) {
      clearActive();
      var d = dot(id); if (d) { d.classList.add('wf-active'); d.parentNode.appendChild(d); }   // raise to top
      var e = entry(id);
      if (e) {
        e.classList.add('wf-active');
        if (scroll) { try { e.scrollIntoView({ block: 'nearest' }); } catch (er) {} }
      }
    }

    // Directory entry <-> dot interplay (hover preview + click pin).
    list.querySelectorAll('.wf-entry').forEach(function (e) {
      var id = e.getAttribute('data-dot');
      e.addEventListener('mouseenter', function () { var d = dot(id); if (d) d.classList.add('wf-active'); });
      e.addEventListener('mouseleave', function () { if (!e.classList.contains('wf-active')) { var d = dot(id); if (d) d.classList.remove('wf-active'); } });
      e.addEventListener('click', function (ev) {
        // Let the inner links (walkthrough / record) navigate normally.
        if (ev.target.closest('a')) return;
        highlight(id, false);
      });
      e.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); highlight(id, false); } });
    });

    // Dot -> directory entry.
    if (svg) {
      svg.querySelectorAll('.wf-dot').forEach(function (d) {
        var id = d.getAttribute('data-dot');
        d.addEventListener('click', function () { highlight(id, true); });
        d.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); highlight(id, true); } });
      });
    }

    // Filter box: hide non-matching entries + dim their dots.
    if (filter) {
      filter.addEventListener('input', function () {
        var q = (filter.value || '').trim().toLowerCase();
        // Strip a leading "take me to" so natural phrasing still matches.
        q = q.replace(/^(take me to|go to|find|where is|where's)\s+/i, '').trim();
        var shown = 0;
        list.querySelectorAll('.wf-entry').forEach(function (e) {
          var hay = e.getAttribute('data-search') || '';
          var match = q === '' || hay.indexOf(q) !== -1;
          e.classList.toggle('d-none', !match);
          var d = dot(e.getAttribute('data-dot'));
          if (d) d.classList.toggle('wf-dim', !(q === '' || match));
          if (match) shown++;
        });
        if (noMatch) noMatch.classList.toggle('d-none', shown !== 0);
      });
    }
  })();
  </script>
  @endif
@endsection
