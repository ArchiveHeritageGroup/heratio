{{-- heratio#1192 - Live virtual opening: PUBLIC event page (RSVP + timed join link). --}}
@extends('theme::layouts.1col')

@section('title', $event->title)
@section('body-class', 'exhibition-space opening-public')

@section('content')
  @php
    $startsTs = strtotime($event->starts_at);
    $endsTs = $startsTs + ((int) $event->duration_minutes * 60);
    $isCancelled = $event->status === 'cancelled';
    $isEnded = $event->status === 'ended' || time() > $endsTs;
    $isFull = $remaining <= 0;
  @endphp

  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="mb-2">
        <span class="badge bg-primary"><i class="fas fa-vr-cardboard me-1"></i>{{ __('Live virtual opening') }}</span>
        @if($isCancelled)<span class="badge bg-danger">{{ __('Cancelled') }}</span>
        @elseif($isEnded)<span class="badge bg-secondary">{{ __('Ended') }}</span>
        @elseif($event->status === 'live')<span class="badge bg-success">{{ __('Live now') }}</span>
        @endif
      </div>

      <h1 class="mb-1">{{ $event->title }}</h1>
      <p class="text-muted mb-3">
        <i class="fas fa-map-marker-alt me-1"></i>{{ $space->name }}
        @if($event->host_name)&nbsp;&middot;&nbsp;<i class="fas fa-user me-1"></i>{{ __('Hosted by') }} {{ $event->host_name }}@endif
      </p>

      <div class="card mb-3">
        <div class="card-body">
          <div class="row">
            <div class="col-sm-6 mb-2 mb-sm-0">
              <div class="small text-muted">{{ __('Starts') }}</div>
              <div class="fw-semibold">{{ \Illuminate\Support\Carbon::parse($event->starts_at)->format('l j F Y') }}</div>
              <div>{{ \Illuminate\Support\Carbon::parse($event->starts_at)->format('H:i') }} &middot; {{ $event->duration_minutes }} {{ __('min') }}</div>
            </div>
            <div class="col-sm-6">
              <div class="small text-muted">{{ __('Seats') }}</div>
              <div class="fw-semibold">{{ $reserved }} / {{ $event->capacity }} {{ __('booked') }}</div>
              <div class="@if($isFull) text-danger @else text-success @endif">
                @if($isFull){{ __('Fully booked') }}@else{{ $remaining }} {{ __('seats remaining') }}@endif
              </div>
            </div>
          </div>
          <div id="opCountdown" class="mt-3 small text-muted" data-start="{{ $startsTs }}" data-end="{{ $endsTs }}"></div>
        </div>
      </div>

      @if($event->description)
        <div class="mb-3">{!! nl2br(e($event->description)) !!}</div>
      @endif

      {{-- Join the walkthrough: live from a window before start until the event ends. --}}
      <div class="card mb-3">
        <div class="card-body text-center">
          @if($isCancelled)
            <p class="text-danger mb-0"><i class="fas fa-ban me-1"></i>{{ __('This opening has been cancelled.') }}</p>
          @elseif($isEnded)
            <p class="text-muted mb-2">{{ __('This opening has ended.') }}</p>
            <a href="{{ route('exhibition-space.walkthrough', ['slug' => $space->slug]) }}" class="btn btn-outline-primary">
              <i class="fas fa-vr-cardboard me-1"></i>{{ __('Explore the gallery on your own') }}
            </a>
          @else
            <a id="opJoinBtn" href="{{ route('exhibition-space.walkthrough', ['slug' => $space->slug]) }}"
               class="btn btn-lg @if($joinable) btn-success @else btn-secondary disabled @endif"
               @if(!$joinable) aria-disabled="true" tabindex="-1" @endif>
              <i class="fas fa-door-open me-1"></i>{{ __('Join the walkthrough') }}
            </a>
            <div id="opJoinHint" class="small text-muted mt-2">
              @if($joinable)
                {{ __('The opening is live - step inside.') }}
              @else
                {{ __('The join link opens :n minutes before the start time.', ['n' => $joinWindow]) }}
              @endif
            </div>
          @endif
        </div>
      </div>

      {{-- RSVP / ticket --}}
      @if(session('success') && session('ticket'))
        <div class="alert alert-success">
          <div class="fw-semibold"><i class="fas fa-ticket-alt me-1"></i>{{ session('success') }}</div>
          <div class="mt-1">{{ __('Ticket code') }}: <code>{{ session('ticket') }}</code></div>
          <div class="small text-muted">{{ __('Keep this code - return to this page at event time and click Join.') }}</div>
        </div>
      @elseif(!$isCancelled && !$isEnded)
        <div class="card">
          <div class="card-header py-2"><strong><i class="fas fa-ticket-alt me-1"></i>{{ __('Reserve your free ticket') }}</strong></div>
          <div class="card-body">
            @if($errors->any())
              <div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            @if($isFull)
              <p class="text-muted mb-0">{{ __('This event is fully booked.') }}</p>
            @else
              <form method="post" action="{{ route('exhibition-space.opening-rsvp', ['token' => $event->public_token]) }}">
                @csrf
                <div class="row g-2">
                  <div class="col-md-5">
                    <label class="form-label" for="rsvp_name">{{ __('Your name') }}</label>
                    <input type="text" id="rsvp_name" name="name" class="form-control" maxlength="160" required value="{{ old('name') }}">
                  </div>
                  <div class="col-md-5">
                    <label class="form-label" for="rsvp_email">{{ __('Email') }}</label>
                    <input type="email" id="rsvp_email" name="email" class="form-control" maxlength="190" required value="{{ old('email') }}">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label" for="rsvp_party">{{ __('Party') }}</label>
                    <input type="number" id="rsvp_party" name="party_size" class="form-control" min="1" max="20" value="{{ old('party_size', 1) }}" required>
                  </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-check me-1"></i>{{ __('Reserve ticket') }}</button>
              </form>
            @endif
          </div>
        </div>
      @endif

      <p class="text-muted small mt-3 mb-0">
        {{ __('Live multi-user spatial audio and a real-time guided docent are coming in a future release (heratio#1150). For now, ticket holders join the same 3D walkthrough at event time.') }}
      </p>
    </div>
  </div>

  <script nonce="{{ $cspNonce ?? '' }}">
    (function () {
      var el = document.getElementById('opCountdown');
      if (!el) return;
      var start = parseInt(el.getAttribute('data-start'), 10) * 1000;
      var end = parseInt(el.getAttribute('data-end'), 10) * 1000;
      var windowMin = {{ (int) $joinWindow }};
      var btn = document.getElementById('opJoinBtn');
      var hint = document.getElementById('opJoinHint');

      function fmt(ms) {
        var s = Math.max(0, Math.floor(ms / 1000));
        var d = Math.floor(s / 86400); s -= d * 86400;
        var h = Math.floor(s / 3600); s -= h * 3600;
        var m = Math.floor(s / 60); s -= m * 60;
        var parts = [];
        if (d) parts.push(d + 'd');
        if (h || d) parts.push(h + 'h');
        parts.push(m + 'm');
        if (!d && !h) parts.push(s + 's');
        return parts.join(' ');
      }

      function tick() {
        var now = Date.now();
        var openAt = start - windowMin * 60 * 1000;
        if (now > end) {
          el.textContent = '{{ __('This opening has ended.') }}';
          return;
        }
        if (now >= openAt) {
          el.textContent = '{{ __('The opening is live now.') }}';
          if (btn && btn.classList.contains('disabled')) {
            btn.classList.remove('btn-secondary', 'disabled');
            btn.classList.add('btn-success');
            btn.removeAttribute('aria-disabled');
            btn.removeAttribute('tabindex');
            if (hint) hint.textContent = '{{ __('The opening is live - step inside.') }}';
          }
          return;
        }
        el.textContent = '{{ __('Starts in') }} ' + fmt(start - now);
      }

      tick();
      setInterval(tick, 1000);
    })();
  </script>
@endsection
