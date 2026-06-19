{{--
  Web archive (WARC) - the single landing page (admin). heratio#1244.

  ONE surface offering BOTH capture modes over ONE list:
    (a) Archive a URL      - submit any public http/https URL (url mode).
    (b) Capture a record   - snapshot a published record's own public page by id
                             (record mode; SSRF-scoped to this host, same-host subresources).

  Backed by the reusable ahg-core engines (WarcCaptureService + WarcReplayService) and the
  single warc_capture table. Replay serves the archived page back entirely from the stored
  WARC (never the live site). Bootstrap 5 + central theme; FontAwesome icons.
  Jurisdiction-neutral.

  Honest scope note: record mode archives the record's OWN HTML page PLUS its direct
  same-host subresources (CSS / JS / images / icons) into the same WARC; url mode is a
  single-page capture. Off-host assets (third-party CDNs / fonts) are not fetched, so they
  do not replay - that gap keeps #1244 open.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Web archive (WARC)'))

@section('content')
@php
  $available = $available ?? false;
  $captures  = $captures ?? [];
  $storageHint = $storageHint ?? '';

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
    <span class="text-muted small">{{ __('Snapshot a web page into a WARC 1.1 file and replay it back') }}</span>
    <span class="ms-auto"></span>
    @if(Route::has('preservation-maturity.index'))
      <a href="{{ route('preservation-maturity.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-shield-halved me-1"></i>{{ __('Preservation dashboard') }}
      </a>
    @endif
  </div>
  <p class="text-muted small mb-3" style="max-width:960px">
    {{ __('Web archiving captures a web page and preserves it in the standard WARC container (ISO 28500) so it remains readable after the live page changes. You can archive any public URL, or snapshot a published record\'s own page on this host. Each capture writes a valid WARC 1.1 file that you can store, download, and replay back from the archive.') }}
  </p>

  <div class="alert alert-info py-2 small" style="max-width:960px">
    <i class="fas fa-circle-info me-1"></i>
    {{ __('Record-mode captures archive the record\'s own HTML page plus its direct SAME-HOST subresources (CSS, JavaScript, images, icons) into the same WARC. URL-mode captures archive a single page. Off-host assets (third-party CDNs, fonts) are deliberately not fetched, so they do not replay - off-host capture remains on the roadmap.') }}
  </div>

  @foreach(['success' => 'alert-success', 'notice' => 'alert-success', 'error' => 'alert-danger'] as $key => $cls)
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

      {{-- Capture forms (both modes) --}}
      <div class="col-12 col-lg-4">

        {{-- (a) Archive a URL --}}
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-body">
            <h2 class="h6 mb-3"><i class="fas fa-link me-2 text-primary"></i>{{ __('Archive a URL') }}</h2>
            <form method="POST" action="{{ route('web-archive.store') }}">
              @csrf
              <div class="mb-3">
                <label class="form-label small fw-semibold" for="url">{{ __('Page URL') }}</label>
                <input type="url" class="form-control form-control-sm @error('url') is-invalid @enderror"
                       id="url" name="url" value="{{ old('url') }}" required
                       placeholder="{{ __('https://example.org/page') }}">
                @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text small">{{ __('Only public http/https URLs. Single-page capture.') }}</div>
              </div>
              <button type="submit" class="btn btn-sm btn-primary w-100">
                <i class="fas fa-spider me-1"></i>{{ __('Capture URL') }}
              </button>
            </form>
          </div>
        </div>

        {{-- (b) Capture a record page --}}
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h2 class="h6 mb-3"><i class="fas fa-camera me-2 text-success"></i>{{ __('Capture a record page') }}</h2>
            <form method="POST" action="{{ route('web-archive.capture') }}">
              @csrf
              <div class="mb-3">
                <label class="form-label small fw-semibold" for="information_object_id">{{ __('Published record ID') }}</label>
                <input type="number" min="2" step="1" class="form-control form-control-sm @error('information_object_id') is-invalid @enderror"
                       id="information_object_id" name="information_object_id"
                       value="{{ old('information_object_id') }}" required
                       placeholder="{{ __('e.g. 1234') }}">
                @error('information_object_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text small">
                  {{ __('The record must be published. Only that record\'s own page on this host is fetched, plus its same-host subresources.') }}
                </div>
              </div>
              <div class="form-check mb-3">
                <input type="hidden" name="include_off_host" value="0">
                <input type="checkbox" class="form-check-input" id="include_off_host"
                       name="include_off_host" value="1" {{ old('include_off_host') ? 'checked' : '' }}>
                <label class="form-check-label small" for="include_off_host">
                  {{ __('Also capture off-host assets (third-party CDN / font / image)') }}
                </label>
                <div class="form-text small">
                  {{ __('Opt-in. Off-host assets still pass the same SSRF guards and size / count limits; loopback, link-local, cloud-metadata and private-range hosts are always refused.') }}
                </div>
              </div>
              <button type="submit" class="btn btn-sm btn-success w-100">
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
              <p class="text-muted small mb-1">
                {{ __('No captures yet. Archive a URL or enter a published record ID to web-archive a page into a WARC file.') }}
              </p>
              @if($storageHint)
                <p class="text-muted small mb-0"><small>{{ __('WARC files are written under') }} <code>{{ $storageHint }}</code>.</small></p>
              @endif
            @else
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr class="small text-muted">
                      <th>{{ __('Captured') }}</th>
                      <th>{{ __('Mode') }}</th>
                      <th>{{ __('Target') }}</th>
                      <th class="text-end">{{ __('Assets') }}</th>
                      <th class="text-end">{{ __('Size') }}</th>
                      <th>{{ __('Status') }}</th>
                      <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($captures as $c)
                      <tr>
                        <td class="small text-nowrap"><a href="{{ route('web-archive.show', $c['id']) }}">{{ $c['captured_at'] ?? '-' }}</a></td>
                        <td class="small">
                          @if(($c['mode'] ?? 'record') === 'url')
                            <span class="badge bg-light text-dark border"><i class="fas fa-link me-1"></i>{{ __('URL') }}</span>
                          @else
                            <span class="badge bg-light text-dark border"><i class="fas fa-file-lines me-1"></i>{{ __('Record') }}</span>
                          @endif
                        </td>
                        <td class="small">
                          @if(($c['mode'] ?? 'record') === 'record' && $c['slug'])
                            <a href="{{ url('/'.ltrim($c['slug'], '/')) }}" target="_blank" rel="noopener">{{ $c['slug'] }}</a>
                          @else
                            <span class="text-truncate d-inline-block" style="max-width:240px" title="{{ $c['target_uri'] }}">{{ $c['target_uri'] }}</span>
                          @endif
                        </td>
                        <td class="small text-end text-nowrap">
                          @if($c['status'] === 'captured')
                            @php $sc = (int) ($c['subresource_count'] ?? 0); @endphp
                            @if($sc > 0)
                              <span class="badge bg-light text-dark border" title="{{ __('Same-host subresources captured into the WARC') }}">
                                <i class="fas fa-link me-1"></i>+{{ $sc }}
                              </span>
                            @else
                              <span class="text-muted" title="{{ __('Page only; no same-host subresources') }}">{{ __('page only') }}</span>
                            @endif
                          @else
                            <span class="text-muted">-</span>
                          @endif
                        </td>
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
                          @if($c['has_file'] && $c['status'] === 'captured')
                            <a href="{{ route('web-archive.replay', ['id' => $c['id']]) }}" target="_blank" rel="noopener"
                               class="btn btn-outline-primary btn-sm" title="{{ __('Replay snapshot') }}">
                              <i class="fas fa-clock-rotate-left"></i>
                            </a>
                          @endif
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
                          <td colspan="6" class="small text-muted font-monospace" style="font-size:.7rem">
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
