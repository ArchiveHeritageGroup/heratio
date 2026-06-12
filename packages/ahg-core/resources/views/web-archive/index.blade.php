{{--
  Web archive (WARC) - landing page (admin). heratio#1244.

  Lets the catalogue web-archive its OWN published record pages into valid WARC 1.1
  (ISO 28500) files. Lists past captures (with a download link to each stored .warc and
  its fixity SHA-256) and offers a "capture a record" form that takes a published record
  id and snapshots that record's own public page. The capture is bounded (one page, size
  + timeout caps) and SSRF-safe (only the record's own canonical url() on this host).
  Bootstrap 5 + central theme; FontAwesome icons. Jurisdiction-neutral.

  Honest scope note (shown to the operator): this archives the record's OWN HTML page
  only - not yet its subresources (CSS / JS / images), and there is no replay surface yet.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Web archive (WARC)'))

@section('content')
@php
  $available = $available ?? false;
  $captures  = $captures ?? [];

  $statusBadge = function (string $s): string {
      return $s === 'captured' ? 'bg-success' : 'bg-danger';
  };
  $fmtBytes = function (?int $b): string {
      if ($b === null) { return '-'; }
      $units = ['B', 'KB', 'MB', 'GB'];
      $i = 0; $n = (float) $b;
      while ($n >= 1024 && $i < count($units) - 1) { $n /= 1024; $i++; }
      return ($i === 0 ? (int) $n : number_format($n, 1)).' '.$units[$i];
  };
@endphp

<div class="container-fluid py-3">

  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
    <h1 class="h4 mb-0"><i class="fas fa-globe me-2 text-primary"></i>{{ __('Web archive (WARC)') }}</h1>
    <span class="text-muted small">{{ __('Snapshot a published record\'s own public page into a WARC 1.1 file') }}</span>
    <span class="ms-auto"></span>
    @if(Route::has('preservation-maturity.index'))
      <a href="{{ route('preservation-maturity.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-shield-halved me-1"></i>{{ __('Preservation dashboard') }}
      </a>
    @endif
  </div>
  <p class="text-muted small mb-3" style="max-width:960px">
    {{ __('Web archiving captures a web page and preserves it in the standard WARC container (ISO 28500) so it remains readable after the live page changes. Here the catalogue can web-archive its own record pages: each capture performs a server-side request for the record\'s own public page on this host and writes a valid WARC 1.1 file (a warcinfo, request and response record) that you can store and download.') }}
  </p>

  <div class="alert alert-info py-2 small" style="max-width:960px">
    <i class="fas fa-circle-info me-1"></i>
    {{ __('This first slice archives the record\'s own HTML page only. It does not yet fetch the page\'s subresources (CSS, JavaScript, images), and there is no in-app replay (Wayback) viewer yet - multi-resource capture and replay remain on the roadmap.') }}
  </div>

  @foreach(['success' => 'alert-success', 'error' => 'alert-danger'] as $key => $cls)
    @if(session($key))
      <div class="alert {{ $cls }} py-2"><i class="fas fa-circle-info me-1"></i>{{ session($key) }}</div>
    @endif
  @endforeach

  @if(! $available)
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-5">
        <div class="display-6 text-muted mb-2"><i class="fas fa-globe"></i></div>
        <h2 class="h5">{{ __('Web archive is being set up') }}</h2>
        <p class="text-muted mb-0" style="max-width:560px;margin:0 auto">
          {{ __('The web-archive table is not installed on this instance yet. It is created automatically on the next boot; please check back shortly.') }}
        </p>
      </div>
    </div>
  @else
    <div class="row g-3">

      {{-- Capture a record --}}
      <div class="col-12 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h2 class="h6 mb-3"><i class="fas fa-camera me-2 text-success"></i>{{ __('Capture a record page') }}</h2>
            <form method="POST" action="{{ route('web-archive.capture') }}">
              @csrf
              <div class="mb-3">
                <label class="form-label small fw-semibold" for="information_object_id">{{ __('Published record ID') }}</label>
                <input type="number" min="2" step="1" class="form-control form-control-sm"
                       id="information_object_id" name="information_object_id"
                       value="{{ old('information_object_id') }}" required
                       placeholder="{{ __('e.g. 1234') }}">
                @error('information_object_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                <div class="form-text small">
                  {{ __('The record must be published. Only that record\'s own public page on this host is fetched.') }}
                </div>
              </div>
              <button type="submit" class="btn btn-sm btn-primary w-100">
                <i class="fas fa-download me-1"></i>{{ __('Capture snapshot') }}
              </button>
            </form>
          </div>
        </div>
      </div>

      {{-- Captures list --}}
      <div class="col-12 col-lg-8">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h2 class="h6 mb-3"><i class="fas fa-clock-rotate-left me-2 text-muted"></i>{{ __('Captures') }}</h2>

            @if(empty($captures))
              <p class="text-muted small mb-0">
                {{ __('No captures yet. Enter a published record ID on the left to web-archive its page into a WARC file.') }}
              </p>
            @else
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr class="small text-muted">
                      <th>{{ __('Captured') }}</th>
                      <th>{{ __('Record') }}</th>
                      <th>{{ __('Target URI') }}</th>
                      <th class="text-end">{{ __('Size') }}</th>
                      <th>{{ __('Status') }}</th>
                      <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($captures as $c)
                      <tr>
                        <td class="small text-nowrap">{{ $c['captured_at'] ?? '-' }}</td>
                        <td class="small">
                          @if($c['slug'])
                            <a href="{{ url('/'.ltrim($c['slug'], '/')) }}" target="_blank" rel="noopener">{{ $c['slug'] }}</a>
                          @else
                            <span class="text-muted">#{{ $c['information_object_id'] ?? '-' }}</span>
                          @endif
                        </td>
                        <td class="small text-truncate" style="max-width:240px" title="{{ $c['target_uri'] }}">{{ $c['target_uri'] }}</td>
                        <td class="small text-end text-nowrap">{{ $fmtBytes($c['byte_size']) }}</td>
                        <td>
                          <span class="badge {{ $statusBadge($c['status']) }}">{{ __(ucfirst($c['status'])) }}</span>
                          @if($c['status'] !== 'captured' && $c['error_message'])
                            <i class="fas fa-circle-exclamation text-danger ms-1" title="{{ $c['error_message'] }}"></i>
                          @endif
                          @if($c['http_status'])
                            <span class="text-muted small ms-1">HTTP {{ $c['http_status'] }}</span>
                          @endif
                        </td>
                        <td class="text-end text-nowrap">
                          @if($c['has_file'])
                            <a href="{{ route('web-archive.download', ['id' => $c['id']]) }}"
                               class="btn btn-outline-secondary btn-sm" title="{{ __('Download WARC') }}">
                              <i class="fas fa-file-arrow-down"></i>
                            </a>
                          @else
                            <span class="text-muted small">-</span>
                          @endif
                        </td>
                      </tr>
                      @if($c['status'] === 'captured' && $c['sha256'])
                        <tr>
                          <td></td>
                          <td colspan="5" class="small text-muted font-monospace" style="font-size:.7rem">
                            sha256: {{ $c['sha256'] }}
                          </td>
                        </tr>
                      @endif
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif
          </div>
        </div>
      </div>

    </div>
  @endif

</div>
@endsection
