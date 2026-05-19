{{--
    auth-res::settings

    Admin page for toggling the seven external authority lookup sources
    (VIAF / Wikidata / GeoNames / TGN / GND / ISNI / SAGNC) and tuning
    rate-limit / cache-ttl / licence acknowledgement per source.

    Tailwind 4. Lives at /admin/authority-resolution/settings/lookup.
--}}
@extends('theme::layouts.1col')

@section('title', 'Authority Resolution lookup settings')

@section('content')
<div class="px-4 py-6 max-w-screen-xl mx-auto">

    <div class="mb-4 flex items-center justify-between">
        <div>
            <p class="text-xs text-slate-500">
                <a href="{{ route('auth-res.queue') }}" class="hover:underline">&larr; Review queue</a>
            </p>
            <h1 class="text-xl font-semibold text-slate-900 mt-1">Lookup source settings</h1>
            <p class="text-sm text-slate-500 mt-1">
                Toggle each external authority source. All sources default to OFF -
                no HTTP fires until you opt in. Honour each source's licence terms
                before publishing derived data.
            </p>
        </div>
    </div>

    @if(session('notice'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ session('notice') }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth-res.settings.save') }}">
        @csrf

        <div class="space-y-4">
            @foreach($sources as $src)
                @php
                    $s = $bySource[$src] ?? [];
                    $enabled    = isset($s['enabled']) ? (int) $s['enabled']->setting_value : 0;
                    $rateLimit  = isset($s['rate_limit']) ? (int) $s['rate_limit']->setting_value : 60;
                    $cacheTtl   = isset($s['cache_ttl']) ? (int) $s['cache_ttl']->setting_value : 604800;
                    $licenceN   = isset($s['license_note']) ? (string) $s['license_note']->setting_value : '';
                    $licenceUrl = isset($s['license_url']) ? (string) $s['license_url']->setting_value : '';
                    $isStub     = in_array($src, ['tgn', 'gnd', 'isni', 'sagnc'], true);
                @endphp

                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900 uppercase">{{ $src }}</h2>
                            @if($isStub)
                                <span class="inline-block mt-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-800">stub adapter / returns [] until endpoint wired</span>
                            @endif
                        </div>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="settings[lookup.{{ $src }}.enabled]" value="0">
                            <input type="checkbox" name="settings[lookup.{{ $src }}.enabled]" value="1"
                                   @if($enabled === 1) checked @endif
                                   class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-slate-700">Enabled</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Rate limit (calls/min)</label>
                            <input type="number" min="1"
                                   name="settings[lookup.{{ $src }}.rate_limit]"
                                   value="{{ $rateLimit }}"
                                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Cache TTL (seconds)</label>
                            <input type="number" min="60"
                                   name="settings[lookup.{{ $src }}.cache_ttl]"
                                   value="{{ $cacheTtl }}"
                                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    </div>

                    @if($src === 'geonames')
                        @php $gnUsername = isset($s['username']) ? (string) $s['username']->setting_value : 'demo'; @endphp
                        <div class="mt-3">
                            <label class="block text-xs font-medium text-slate-700 mb-1">GeoNames username</label>
                            <input type="text"
                                   name="settings[lookup.geonames.username]"
                                   value="{{ $gnUsername }}"
                                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <p class="mt-1 text-[10px] text-slate-500">Register at geonames.org and replace 'demo'.</p>
                        </div>
                    @endif

                    <div class="mt-3">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Licence acknowledgement</label>
                        <textarea name="settings[lookup.{{ $src }}.license_note]"
                                  rows="2"
                                  class="w-full rounded-md border border-slate-300 px-3 py-2 text-xs">{{ $licenceN }}</textarea>
                        @if($licenceUrl)
                            <a href="{{ $licenceUrl }}" target="_blank" rel="noopener noreferrer"
                               class="mt-1 inline-block text-[10px] text-indigo-700 hover:underline">Open licence URL</a>
                        @endif
                    </div>

                    <div data-status-src="{{ $src }}" class="mt-3 text-[10px] text-slate-500">
                        loading status...
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Cross-source settings --}}
        @if(!empty($cross))
            <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="text-base font-semibold text-slate-900 mb-2">Cross-source settings</h2>
                @foreach($cross as $key => $row)
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-slate-700 mb-1">{{ $key }}</label>
                        @if($row->setting_type === 'json')
                            <textarea name="settings[{{ $key }}]" rows="2"
                                      class="w-full rounded-md border border-slate-300 px-3 py-2 text-xs font-mono">{{ $row->setting_value }}</textarea>
                        @else
                            <input type="text" name="settings[{{ $key }}]"
                                   value="{{ $row->setting_value }}"
                                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @endif
                        @if($row->description)
                            <p class="mt-1 text-[10px] text-slate-500">{{ $row->description }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-6 flex justify-end">
            <button type="submit"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                Save settings
            </button>
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
