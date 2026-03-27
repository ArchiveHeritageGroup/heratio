{{--
  Reusable AJAX autocomplete component for Heratio.

  Usage:
    @include('ahg-core::components.autocomplete', [
        'name'        => 'creator_id',           // input name attribute
        'label'       => 'Creator',               // visible label text
        'route'       => 'actor.autocomplete',    // named route for AJAX endpoint
        'value'       => old('creator_id', $io->creator_id ?? ''),
        'displayValue'=> old('creator_name', $io->creator_name ?? ''),
        'placeholder' => 'Type to search...',     // optional
        'required'    => false,                   // optional, default false
        'helpText'    => '',                      // optional form help text
        'minChars'    => 2,                       // optional, min chars before search (default 2)
        'queryParam'  => 'query',                 // optional, query parameter name (default 'query')
        'idField'     => 'id',                    // optional, JSON field for the hidden value (default 'id')
        'nameField'   => 'name',                  // optional, JSON field for the display text (default 'name')
        'extraFields' => [],                      // optional, extra fields to show in dropdown (e.g. ['slug'])
        'inputClass'  => '',                      // optional, extra CSS classes for input
        'allowFreeText' => false,                 // optional, allow values not from the list
        'extraParams' => [],                      // optional, extra query params appended to the route URL
        'multi'       => false,                   // optional, multi-select mode
        'multiName'   => '',                      // optional, name for hidden inputs in multi mode (e.g. 'creatorIds[]')
        'existingItems'=> [],                     // optional, pre-selected items [{id:..., name:...}, ...]
    ])

  The AJAX endpoint must return JSON: [{id: ..., name: ..., slug: ...}, ...]
--}}

@php
    $acId            = 'ac-' . str_replace(['[', ']', '.'], '-', $name) . '-' . uniqid();
    $acName          = $name;
    $acLabel         = $label ?? '';
    $acRoute         = $route ?? '';
    $acValue         = $value ?? '';
    $acDisplayValue  = $displayValue ?? '';
    $acPlaceholder   = $placeholder ?? 'Type to search...';
    $acRequired      = $required ?? false;
    $acHelpText      = $helpText ?? '';
    $acMinChars      = $minChars ?? 2;
    $acQueryParam    = $queryParam ?? 'query';
    $acIdField       = $idField ?? 'id';
    $acNameField     = $nameField ?? 'name';
    $acExtraFields   = $extraFields ?? [];
    $acInputClass    = $inputClass ?? '';
    $acAllowFreeText = $allowFreeText ?? false;
    $acMulti         = $multi ?? false;
    $acMultiName     = $multiName ?? ($acName . '[]');
    $acExistingItems = $existingItems ?? [];
    $acExtraParams   = $extraParams ?? [];
    if ($acRoute) {
        $acRouteUrl = route($acRoute);
        if (!empty($acExtraParams)) {
            $acRouteUrl .= (str_contains($acRouteUrl, '?') ? '&' : '?') . http_build_query($acExtraParams);
        }
    } else {
        $acRouteUrl = '';
    }
@endphp

<div class="mb-3 ahg-autocomplete" id="{{ $acId }}" data-config='@json([
    "url"          => $acRouteUrl,
    "minChars"     => $acMinChars,
    "queryParam"   => $acQueryParam,
    "idField"      => $acIdField,
    "nameField"    => $acNameField,
    "extraFields"  => $acExtraFields,
    "allowFreeText"=> $acAllowFreeText,
    "multi"        => $acMulti,
    "multiName"    => $acMultiName,
])'>

    @if($acLabel)
        <label for="{{ $acId }}-input" class="form-label">
            {{ $acLabel }}
            @if($acRequired)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    @if($acMulti)
        {{-- Multi-select mode: tag list + input --}}
        <div class="ahg-ac-tags" id="{{ $acId }}-tags">
            @foreach($acExistingItems as $item)
                <span class="badge bg-secondary me-1 mb-1 ahg-ac-tag" data-id="{{ $item['id'] ?? $item->id ?? '' }}">
                    {{ $item['name'] ?? $item->name ?? '' }}
                    <input type="hidden" name="{{ $acMultiName }}" value="{{ $item['id'] ?? $item->id ?? '' }}">
                    <button type="button" class="btn-close btn-close-white ms-1 ahg-ac-tag-remove" aria-label="Remove" style="font-size: 0.6em;"></button>
                </span>
            @endforeach
        </div>
        <div class="position-relative">
            <input type="text"
                   id="{{ $acId }}-input"
                   class="form-control form-control-sm {{ $acInputClass }}"
                   placeholder="{{ $acPlaceholder }}"
                   autocomplete="off">
            <div class="dropdown-menu w-100 ahg-ac-dropdown" id="{{ $acId }}-dropdown"></div>
        </div>
    @else
        {{-- Single-select mode: hidden ID + visible text --}}
        <div class="position-relative">
            <input type="hidden" name="{{ $acName }}" id="{{ $acId }}-hidden" value="{{ $acValue }}">
            <input type="text"
                   id="{{ $acId }}-input"
                   class="form-control {{ $acInputClass }}"
                   value="{{ $acDisplayValue ?: $acValue }}"
                   placeholder="{{ $acPlaceholder }}"
                   autocomplete="off"
                   @if($acRequired) required @endif>
            <div class="dropdown-menu w-100 ahg-ac-dropdown" id="{{ $acId }}-dropdown"></div>
        </div>
    @endif

    @if($acHelpText)
        <div class="form-text text-muted small">{!! $acHelpText !!}</div>
    @endif
</div>

{{-- Only include the JS once per page --}}
@once
@push('js')
<script>
(function () {
    'use strict';

    /** Debounce helper */
    function debounce(fn, ms) {
        let timer;
        return function () {
            const ctx = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, ms);
        };
    }

    /** Escape HTML to prevent XSS in dropdown items */
    function esc(str) {
        const el = document.createElement('span');
        el.textContent = str;
        return el.innerHTML;
    }

    /** Initialise one autocomplete widget */
    function initAutocomplete(container) {
        const cfg = JSON.parse(container.dataset.config || '{}');
        if (!cfg.url) return;

        const input    = container.querySelector('.form-control[id$="-input"]');
        const dropdown = container.querySelector('.ahg-ac-dropdown');
        const hidden   = container.querySelector('input[type="hidden"][id$="-hidden"]');
        const tagsEl   = container.querySelector('.ahg-ac-tags');

        if (!input || !dropdown) return;

        let activeIdx  = -1;
        let results    = [];

        /** Fetch results from the AJAX endpoint */
        const fetchResults = debounce(function () {
            const q = input.value.trim();
            if (q.length < cfg.minChars) {
                hideDropdown();
                return;
            }

            const url = new URL(cfg.url, window.location.origin);
            url.searchParams.set(cfg.queryParam, q);

            fetch(url.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                results = Array.isArray(data) ? data : (data.data || []);
                renderDropdown();
            })
            .catch(function () {
                results = [];
                hideDropdown();
            });
        }, 300);

        /** Render dropdown items */
        function renderDropdown() {
            if (results.length === 0) {
                hideDropdown();
                return;
            }

            activeIdx = -1;
            let html = '';
            results.forEach(function (item, idx) {
                const name = item[cfg.nameField] || '';
                let extra = '';
                if (cfg.extraFields && cfg.extraFields.length) {
                    const parts = [];
                    cfg.extraFields.forEach(function (f) {
                        if (item[f]) parts.push(esc(String(item[f])));
                    });
                    if (parts.length) {
                        extra = ' <small class="text-muted">(' + parts.join(', ') + ')</small>';
                    }
                }
                html += '<button type="button" class="dropdown-item ahg-ac-item" data-index="' + idx + '">'
                      + esc(name) + extra
                      + '</button>';
            });
            dropdown.innerHTML = html;
            dropdown.classList.add('show');
            dropdown.style.display = 'block';
        }

        function hideDropdown() {
            dropdown.classList.remove('show');
            dropdown.style.display = 'none';
            activeIdx = -1;
        }

        /** Select an item */
        function selectItem(item) {
            if (cfg.multi) {
                // Multi mode: add a tag
                addTag(item[cfg.idField], item[cfg.nameField] || '');
                input.value = '';
            } else {
                // Single mode: fill hidden + display
                if (hidden) hidden.value = item[cfg.idField] || '';
                input.value = item[cfg.nameField] || '';
            }
            hideDropdown();
            input.dispatchEvent(new Event('ahg:autocomplete:select', { bubbles: true }));
        }

        /** Multi mode: add a tag badge */
        function addTag(id, name) {
            if (!tagsEl) return;
            // Prevent duplicates
            if (tagsEl.querySelector('[data-id="' + id + '"]')) return;

            const badge = document.createElement('span');
            badge.className = 'badge bg-secondary me-1 mb-1 ahg-ac-tag';
            badge.dataset.id = id;
            badge.innerHTML = esc(name)
                + '<input type="hidden" name="' + esc(cfg.multiName) + '" value="' + esc(String(id)) + '">'
                + '<button type="button" class="btn-close btn-close-white ms-1 ahg-ac-tag-remove" aria-label="Remove" style="font-size: 0.6em;"></button>';
            tagsEl.appendChild(badge);
        }

        // --- Event listeners ---

        input.addEventListener('input', fetchResults);
        input.addEventListener('focus', function () {
            if (results.length > 0) renderDropdown();
        });

        // Keyboard navigation
        input.addEventListener('keydown', function (e) {
            const items = dropdown.querySelectorAll('.ahg-ac-item');
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIdx = Math.min(activeIdx + 1, items.length - 1);
                updateActive(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                updateActive(items);
            } else if (e.key === 'Enter' && activeIdx >= 0) {
                e.preventDefault();
                selectItem(results[activeIdx]);
            } else if (e.key === 'Escape') {
                hideDropdown();
            }
        });

        function updateActive(items) {
            items.forEach(function (el, i) {
                el.classList.toggle('active', i === activeIdx);
            });
            if (items[activeIdx]) {
                items[activeIdx].scrollIntoView({ block: 'nearest' });
            }
        }

        // Click on dropdown item
        dropdown.addEventListener('mousedown', function (e) {
            const btn = e.target.closest('.ahg-ac-item');
            if (!btn) return;
            e.preventDefault();
            const idx = parseInt(btn.dataset.index, 10);
            if (results[idx]) selectItem(results[idx]);
        });

        // Close dropdown on outside click
        document.addEventListener('click', function (e) {
            if (!container.contains(e.target)) hideDropdown();
        });

        // If single mode and free text not allowed, clear hidden on manual edit
        if (!cfg.multi && !cfg.allowFreeText && hidden) {
            input.addEventListener('input', function () {
                hidden.value = '';
            });
        }

        // If single mode and free text allowed, sync input value to hidden
        if (!cfg.multi && cfg.allowFreeText && hidden) {
            input.addEventListener('input', function () {
                hidden.value = input.value;
            });
        }

        // Multi mode: remove tag on click
        if (tagsEl) {
            tagsEl.addEventListener('click', function (e) {
                const removeBtn = e.target.closest('.ahg-ac-tag-remove');
                if (!removeBtn) return;
                const tag = removeBtn.closest('.ahg-ac-tag');
                if (tag) tag.remove();
            });
        }
    }

    /** Initialise all autocomplete widgets on the page */
    function initAll() {
        document.querySelectorAll('.ahg-autocomplete').forEach(initAutocomplete);
    }

    // Run on DOMContentLoaded or immediately if already loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
</script>
<style>
.ahg-autocomplete .ahg-ac-dropdown {
    max-height: 250px;
    overflow-y: auto;
    z-index: 1055;
    box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.15);
}
.ahg-autocomplete .ahg-ac-dropdown .dropdown-item.active {
    background-color: #0d6efd;
    color: #fff;
}
.ahg-autocomplete .ahg-ac-dropdown .dropdown-item {
    white-space: normal;
    word-break: break-word;
    padding: 0.35rem 0.75rem;
    font-size: 0.875rem;
    cursor: pointer;
}
.ahg-autocomplete .ahg-ac-tags {
    display: flex;
    flex-wrap: wrap;
    margin-bottom: 0.25rem;
}
.ahg-autocomplete .ahg-ac-tag {
    display: inline-flex;
    align-items: center;
}
</style>
@endpush
@endonce
