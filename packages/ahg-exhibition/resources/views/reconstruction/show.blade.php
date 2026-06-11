{{--
  heratio#1219 - "reconstruction assembly montage": a lost structure rebuilding
  itself on screen before the visitor walks into its walkable 3D twin.

  Two montage modes, both reading the same rebuild-stage rows:
    - Assembly (primary): each stage is an absolutely-positioned layer that fades
      in and STAYS, so the structure accretes from fragments to whole. Per-stage
      opacity controls how translucent a layer sits in the stack.
    - Time-lapse: one dated state cross-fades into the next (only the current
      layer visible) with a scrubbable, dated timeline.
  A visitor toggle switches modes live. Both end by revealing a prominent
  "Walk through it" CTA into the linked space's walkthrough.

  Vanilla JS + CSS only, self-hosted, nonce'd inline. Respects
  prefers-reduced-motion (shows the finished structure + a static stage list).
  $demo = true renders the self-contained bundled-SVG demonstration (no DB).

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3.0 or later.
--}}
@extends('theme::layouts.1col')

@section('title', ($reconstruction->record_title ?: __('Reconstruction')).' - '.__('Reconstruction montage'))
@section('body-class', 'exhibition-space reconstruction-montage')

@php
  $hasStages = ! empty($stages);
  $playable = array_values(array_filter($stages, fn ($s) => ! empty($s->src)));
  $hasPlayable = ! empty($playable);
  $walkUrl = $spaceSlug ? route('exhibition-space.walkthrough', ['slug' => $spaceSlug]) : null;
@endphp

@section('content')
  <div class="mb-3">
    <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
      <h1 class="mb-0 flex-grow-1">
        <i class="fas fa-archway me-2"></i>{{ $reconstruction->record_title ?: __('Untitled record') }}
      </h1>
      @if($demo)
        <span class="badge bg-secondary">{{ __('Demonstration') }}</span>
      @endif
      <a href="{{ route('exhibition-space.reconstructions') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-th-large me-1"></i>{{ __('All reconstructions') }}
      </a>
    </div>
    <p class="lead mb-1">{{ __('Watch it rebuild itself, then walk through what no longer exists.') }}</p>
    @if(!empty($reconstruction->note))
      <p class="text-muted small mb-0" style="max-width: 60rem;">{{ $reconstruction->note }}</p>
    @endif
    <p class="text-muted small fst-italic mt-2 mb-0" style="max-width: 60rem;">
      {{ __('A reconstruction is one informed reading of the evidence, assembled for interpretation. It is not a claim about the original\'s exact appearance.') }}
    </p>
  </div>

  @if(!$hasPlayable)
    {{-- Empty state: linked but no rebuild stages added yet. --}}
    <div class="alert alert-light border text-center py-5 my-4">
      <p class="h5 text-muted mb-2">
        <i class="far fa-images me-2"></i>{{ __('This reconstruction has no rebuild stages yet.') }}
      </p>
      <p class="text-muted small mb-3">
        {{ __('Once a curator adds the evidence layers, you will be able to watch the structure assemble itself here.') }}
      </p>
      @if($walkUrl)
        <a href="{{ $walkUrl }}" class="btn btn-primary">
          <i class="fas fa-walking me-1"></i>{{ __('Walk through it') }} &rarr;
        </a>
      @endif
    </div>
  @else
    <style nonce="{{ $cspNonce ?? '' }}">
      .recon-montage { --recon-accent:#0d6efd; }
      .recon-stage {
        position: relative;
        width: 100%;
        max-width: 960px;
        margin: 0 auto;
        aspect-ratio: 16 / 10;
        background: #0f1115;
        border-radius: .5rem;
        overflow: hidden;
        box-shadow: 0 2px 14px rgba(0,0,0,.25);
      }
      .recon-layer {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: contain;
        opacity: 0;
        transition: opacity .9s ease;
        will-change: opacity;
      }
      /* Assembly: shown layers stay; their opacity is the per-stage translucency. */
      .recon-montage[data-mode="assembly"] .recon-layer.is-shown { opacity: var(--layer-opacity, 1); }
      /* Time-lapse: only the current layer is visible; others fade out fully. */
      .recon-montage[data-mode="timelapse"] .recon-layer { opacity: 0; }
      .recon-montage[data-mode="timelapse"] .recon-layer.is-current { opacity: 1; }
      .recon-caption {
        position: absolute; left: 0; right: 0; bottom: 0;
        padding: .6rem .9rem;
        background: linear-gradient(to top, rgba(0,0,0,.72), rgba(0,0,0,0));
        color: #fff;
        font-size: .95rem;
        opacity: 0;
        transition: opacity .5s ease;
      }
      .recon-caption.is-visible { opacity: 1; }
      .recon-caption .recon-date { font-weight: 600; margin-right: .4rem; color: #cfe3ff; }
      .recon-progress { height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden; }
      .recon-progress > span { display: block; height: 100%; width: 0; background: var(--recon-accent); transition: width .25s linear; }
      .recon-toggle .btn.active { background: var(--recon-accent); color: #fff; }
      .recon-scrubber { display: none; }
      .recon-montage[data-mode="timelapse"] ~ .recon-controls .recon-scrubber { display: block; }
      .recon-cta { display: none; }
      .recon-cta.is-revealed { display: block; animation: reconCtaIn .6s ease; }
      @keyframes reconCtaIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
      .recon-static-list img { max-height: 120px; width: auto; border-radius: .35rem; background:#0f1115; }
      @media (prefers-reduced-motion: reduce) {
        .recon-layer, .recon-caption { transition: none; }
      }
    </style>

    <div class="recon-montage"
         id="reconMontage"
         data-mode="{{ in_array($defaultStyle, ['assembly','timelapse'], true) ? $defaultStyle : 'assembly' }}"
         data-default-mode="{{ in_array($defaultStyle, ['assembly','timelapse'], true) ? $defaultStyle : 'assembly' }}">
      <div class="recon-stage" id="reconStage" aria-label="{{ __('Reconstruction montage') }}">
        @foreach($playable as $i => $s)
          <img class="recon-layer"
               data-index="{{ $i }}"
               data-opacity="{{ number_format((float) $s->opacity, 2, '.', '') }}"
               src="{{ $s->src }}"
               alt="{{ $s->caption ?: __('Rebuild stage') }} {{ $i + 1 }}"
               style="--layer-opacity: {{ number_format((float) $s->opacity, 2, '.', '') }};"
               loading="lazy">
        @endforeach
        <div class="recon-caption" id="reconCaption" aria-live="polite">
          <span class="recon-date" id="reconCaptionDate"></span>
          <span id="reconCaptionText"></span>
        </div>
      </div>
    </div>

    <div class="recon-controls mt-3" style="max-width: 960px; margin: 0 auto;">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <div class="btn-group btn-group-sm recon-toggle" role="group" aria-label="{{ __('Montage mode') }}" id="reconToggle">
          @foreach($modes as $m)
            <button type="button" class="btn btn-outline-primary recon-mode-btn"
                    data-mode="{{ $m['code'] }}"
                    aria-pressed="false">{{ $m['label'] }}</button>
          @endforeach
        </div>

        <div class="ms-auto d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="reconPlayPause">
            <i class="fas fa-pause"></i>
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="reconReplay">
            <i class="fas fa-redo"></i> {{ __('Replay') }}
          </button>
        </div>
      </div>

      <div class="recon-progress mb-2"><span id="reconProgressBar"></span></div>

      <div class="recon-scrubber">
        <label for="reconScrub" class="form-label small text-muted mb-1">
          {{ __('Timeline') }}: <span id="reconScrubLabel"></span>
        </label>
        <input type="range" class="form-range" id="reconScrub" min="0" max="{{ count($playable) - 1 }}" step="1" value="0">
      </div>
    </div>

    {{-- CTA revealed at the end of either mode. --}}
    <div class="text-center mt-4">
      @if($walkUrl)
        <div class="recon-cta" id="reconCta">
          <p class="text-muted small mb-2">{{ __('The reconstruction is complete.') }}</p>
          <a href="{{ $walkUrl }}" class="btn btn-primary btn-lg">
            <i class="fas fa-walking me-1"></i>{{ __('Walk through it') }} &rarr;
          </a>
          @if($spaceName)
            <div class="small text-muted mt-2">{{ $spaceName }}</div>
          @endif
        </div>
      @else
        <div class="recon-cta" id="reconCta">
          @if($demo)
            <p class="text-muted small mb-0">
              {{ __('In a live reconstruction this is where the "Walk through it" button takes you into the walkable 3D twin. No walkable space exists on this site yet, so the button is unavailable in this demonstration.') }}
            </p>
          @else
            <p class="text-muted small mb-0">
              {{ __('No walkable space is linked to this reconstruction yet.') }}
            </p>
          @endif
        </div>
      @endif
    </div>

    {{-- prefers-reduced-motion + no-JS fallback: the finished structure already
         shows (all assembly layers visible via CSS below) plus a captioned list. --}}
    <noscript>
      <style>
        #reconMontage .recon-layer { opacity: var(--layer-opacity, 1) !important; }
        #reconCta { display: block !important; }
        .recon-controls { display: none !important; }
      </style>
    </noscript>

    <details class="mt-4 recon-static-list" style="max-width: 960px; margin: 0 auto;">
      <summary class="text-muted small">{{ __('Show the rebuild stages as a list') }}</summary>
      <ol class="list-unstyled mt-3">
        @foreach($playable as $s)
          <li class="d-flex gap-3 mb-3 align-items-start">
            <img src="{{ $s->src }}" alt="" width="160">
            <div>
              @if($s->date_display)
                <div class="fw-semibold text-primary">{{ $s->date_display }}</div>
              @endif
              <div class="fw-semibold">{{ $s->caption ?: __('Rebuild stage') }}</div>
              @if($s->body)
                <div class="small text-muted">{{ $s->body }}</div>
              @endif
            </div>
          </li>
        @endforeach
      </ol>
    </details>

    <script nonce="{{ $cspNonce ?? '' }}">
    @include('ahg-exhibition::reconstruction._player-js', [
      'stages' => $playable,
    ])
    </script>
  @endif
@endsection
