{{--
    auth-res::settings - external lookup adapter settings (Bootstrap 5, Task 6).

    One card per source (VIAF, Wikidata, GeoNames, TGN, GND, ISNI, SAGNC).
    Admin can toggle enabled, tune rate_limit / cache_ttl, and edit the
    licence acknowledgement.
--}}
@extends('theme::layouts.1col')

@section('title', 'Authority Resolution lookup settings')

@section('content')
@php
    $sourceInfo = [
        'viaf'     => ['label' => 'VIAF',     'desc' => 'Virtual International Authority File. No key; CC0.'],
        'wikidata' => ['label' => 'Wikidata', 'desc' => 'Wikidata wbsearchentities. No key; CC0.'],
        'geonames' => ['label' => 'GeoNames', 'desc' => 'GeoNames searchJSON. Free username required; CC BY 4.0.'],
        'tgn'      => ['label' => 'Getty TGN','desc' => 'Getty Thesaurus of Geographic Names SPARQL. No key; ODbL 1.0. (stub)'],
        'gnd'      => ['label' => 'GND',      'desc' => 'Deutsche Nationalbibliothek Integrated Authority File (lobid). No key; CC0. (stub)'],
        'isni'     => ['label' => 'ISNI',     'desc' => 'International Standard Name Identifier SRU. Institutional credentials required. (stub)'],
        'sagnc'    => ['label' => 'SAGNC',    'desc' => 'South African Geographical Names Council. (stub)'],
    ];
@endphp
<div class="container py-4">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('auth-res.queue') }}">{{ __('Authority Resolution') }}</a>
            </li>
            <li class="breadcrumb-item active">{{ __('Lookup settings') }}</li>
        </ol>
    </nav>

    <h1 class="mb-3">
        <i class="bi bi-sliders me-2"></i>{{ __('Lookup settings') }}
    </h1>

    <p class="text-muted mb-3">
        {{ __('Toggle each external authority source. All sources default to OFF - no HTTP fires until you opt in. Honour each source licence terms before publishing derived data.') }}
    </p>

    @if(session('notice'))
        <div class="alert alert-success">{{ session('notice') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        {{ __('All sources default to disabled. Heratio will never make outbound HTTP calls until a source is explicitly enabled here.') }}
    </div>

    <form method="POST" action="{{ route('auth-res.settings.save') }}">
        @csrf

        <div class="row g-3">
            @foreach($sources as $src)
                @php
                    $s          = $bySource[$src] ?? [];
                    $enabled    = isset($s['enabled']) ? (int) $s['enabled']->setting_value : 0;
                    $rateLimit  = isset($s['rate_limit']) ? (int) $s['rate_limit']->setting_value : 60;
                    $cacheTtl   = isset($s['cache_ttl']) ? (int) $s['cache_ttl']->setting_value : 604800;
                    $licenceN   = isset($s['license_note']) ? (string) $s['license_note']->setting_value : '';
                    $licenceUrl = isset($s['license_url']) ? (string) $s['license_url']->setting_value : '';
                    $isStub     = in_array($src, ['tgn', 'gnd', 'isni', 'sagnc'], true);
                    $info       = $sourceInfo[$src] ?? ['label' => strtoupper($src), 'desc' => ''];
                @endphp

                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{{ $info['label'] }}</strong>
                                <code class="ms-2 small text-muted">{{ $src }}</code>
                                @if($isStub)
                                    <span class="badge bg-warning text-dark ms-2">
                                        {{ __('stub adapter') }}
                                    </span>
                                @endif
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="settings[lookup.{{ $src }}.enabled]" value="0">
                                <input class="form-check-input" type="checkbox"
                                       id="ar-enable-{{ $src }}"
                                       name="settings[lookup.{{ $src }}.enabled]"
                                       value="1"
                                       @if($enabled === 1) checked @endif>
                                <label class="form-check-label" for="ar-enable-{{ $src }}">
                                    {{ __('Enabled') }}
                                </label>
                            </div>
                        </div>
                        <div class="card-body">

                            <p class="text-muted small mb-3">{{ $info['desc'] }}</p>

                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">
                                        {{ __('Rate limit') }}
                                        <small class="text-muted">({{ __('calls/min') }})</small>
                                    </label>
                                    <input type="number" min="1" max="1000"
                                           name="settings[lookup.{{ $src }}.rate_limit]"
                                           value="{{ $rateLimit }}"
                                           class="form-control form-control-sm">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">
                                        {{ __('Cache TTL') }}
                                        <small class="text-muted">({{ __('seconds') }})</small>
                                    </label>
                                    <input type="number" min="0" max="31536000"
                                           name="settings[lookup.{{ $src }}.cache_ttl]"
                                           value="{{ $cacheTtl }}"
                                           class="form-control form-control-sm">
                                </div>

                                @if($src === 'geonames')
                                    @php $gnUsername = isset($s['username']) ? (string) $s['username']->setting_value : 'demo'; @endphp
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">{{ __('GeoNames username') }}</label>
                                        <input type="text"
                                               name="settings[lookup.geonames.username]"
                                               value="{{ $gnUsername }}"
                                               class="form-control form-control-sm"
                                               placeholder="archivist123">
                                        <div class="form-text">
                                            {{ __("Register at geonames.org and replace 'demo'.") }}
                                            <a href="https://www.geonames.org/login" target="_blank" rel="noopener">geonames.org/login</a>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-3">
                                <label class="form-label small mb-1">{{ __('Licence acknowledgement') }}</label>
                                <textarea name="settings[lookup.{{ $src }}.license_note]"
                                          rows="2"
                                          class="form-control form-control-sm">{{ $licenceN }}</textarea>
                                @if($licenceUrl)
                                    <a href="{{ $licenceUrl }}" target="_blank" rel="noopener noreferrer"
                                       class="d-inline-block mt-1 small">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Open licence URL') }}
                                    </a>
                                @endif
                            </div>

                            <div data-status-src="{{ $src }}" class="text-muted small mt-3">
                                {{ __('loading status...') }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Cross-source settings --}}
        @if(!empty($cross))
            <div class="card mt-3">
                <div class="card-header">
                    <strong>{{ __('Cross-source settings') }}</strong>
                </div>
                <div class="card-body">
                    @foreach($cross as $key => $row)
                        <div class="mb-3">
                            <label class="form-label small mb-1"><code>{{ $key }}</code></label>
                            @if($row->setting_type === 'json')
                                <textarea name="settings[{{ $key }}]" rows="2"
                                          class="form-control form-control-sm font-monospace">{{ $row->setting_value }}</textarea>
                            @else
                                <input type="text"
                                       name="settings[{{ $key }}]"
                                       value="{{ $row->setting_value }}"
                                       class="form-control form-control-sm">
                            @endif
                            @if($row->description)
                                <div class="form-text">{{ $row->description }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>{{ __('Save settings') }}
            </button>
            <a href="{{ route('auth-res.queue') }}" class="btn btn-link">
                <i class="bi bi-arrow-left me-1"></i>{{ __('Back to queue') }}
            </a>
        </div>
    </form>
</div>

@push('js')
<script nonce="{{ function_exists('csp_nonce') ? csp_nonce() : '' }}">
    fetch({!! json_encode(route('auth-res.lookup.status')) !!}, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
    .then(function (r) { return r.json(); })
    .then(function (json) {
        var srcs = (json && json.sources) || {};
        Object.keys(srcs).forEach(function (k) {
            var el = document.querySelector('[data-status-src="' + k + '"]');
            if (!el) return;
            var s = srcs[k];
            el.textContent = (s.enabled ? 'enabled' : 'disabled')
                + ' / ' + s.cache_size + ' cached row(s)'
                + (s.newest_cache_at ? ' / newest ' + s.newest_cache_at : '');
        });
    })
    .catch(function () {});
</script>
@endpush
@endsection
