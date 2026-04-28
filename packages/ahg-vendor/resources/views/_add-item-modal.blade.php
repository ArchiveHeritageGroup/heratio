{{--
  Add/Link GLAM item modal for a vendor transaction.

  Vars:
    $transactionRaw (object) — must expose ->id and ->currency.
                                Currency falls back to 'ZAR' if absent.

  Backend: routes the form POST to ahgvendor.add-transaction-item, which
  consumes information_object_id, condition_before, condition_before_rating,
  declared_value, service_description. Status / quantity / unit cost are
  set later via the update endpoint, so we don't carry them here.

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $action = \Illuminate\Support\Facades\Route::has('ahgvendor.add-transaction-item')
        ? route('ahgvendor.add-transaction-item', ['transactionId' => (int) $transactionRaw->id])
        : '#';
    $currencyCode = $transactionRaw->currency ?? 'ZAR';
    $nonce = csp_nonce() ?? '';

    $conditionGrades = Schema::hasTable('ahg_dropdown')
        ? DB::table('ahg_dropdown')
            ->where('taxonomy', 'condition_grade')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get(['code', 'label'])
        : collect();
@endphp
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="{{ $action }}" id="addItemForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-link me-2"></i>{{ __('Link GLAM/DAM Item') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        {{ __('Start typing to search for an archival item to link to this vendor transaction.') }}
                    </div>

                    <div class="mb-4">
                        <label class="form-label">{{ __('Search GLAM Items') }} *</label>
                        <input type="text" id="glamAutocomplete" class="form-control form-control-lg"
                               placeholder="{{ __('Type title or identifier to search...') }}" autocomplete="off">
                        <input type="hidden" name="information_object_id" id="selectedItemId" required>
                        <div id="autocompleteResults" class="autocomplete-results"></div>
                    </div>

                    <div id="selectedItemCard" class="card bg-light mb-4" style="display: none;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1" id="selectedTitle"></h6>
                                    <small class="text-muted">
                                        <span id="selectedIdentifier"></span>
                                        <span id="selectedLevel" class="badge bg-secondary ms-2"></span>
                                    </small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="clearSelectionBtn">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">{{ __('Condition before service') }}</label>
                            <textarea name="condition_before" class="form-control" rows="2"
                                      placeholder="{{ __('Describe the item condition at intake') }}"></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ __('Condition rating') }}</label>
                            <select name="condition_before_rating" class="form-select">
                                <option value="">{{ __('— select —') }}</option>
                                @foreach ($conditionGrades as $g)
                                    <option value="{{ $g->code }}">{{ $g->label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ __('Declared value') }}</label>
                            <div class="input-group">
                                <span class="input-group-text" title="{{ __('Currency from parent transaction') }}">{{ $currencyCode }}</span>
                                <input type="number" name="declared_value" class="form-control" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">{{ __('Service description') }}</label>
                            <input type="text" name="service_description" class="form-control"
                                   placeholder="{{ __('What needs to be done to this item') }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="addItemBtn" disabled>
                        <i class="fas fa-link me-1"></i>{{ __('Link Item') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style @if ($nonce) nonce="{{ $nonce }}" @endif>
.autocomplete-results {
    position: absolute; z-index: 1050; width: calc(100% - 2rem); max-height: 300px;
    overflow-y: auto; background: white; border: 1px solid #dee2e6;
    border-radius: 0.375rem; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); display: none;
}
.autocomplete-results .autocomplete-item {
    padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #f0f0f0;
}
.autocomplete-results .autocomplete-item:hover,
.autocomplete-results .autocomplete-item.active { background-color: #e9ecef; }
.autocomplete-results .autocomplete-item:last-child { border-bottom: none; }
.autocomplete-results .autocomplete-item .item-title { font-weight: 500; }
.autocomplete-results .autocomplete-item .item-meta { font-size: 0.85em; color: #6c757d; }
</style>

<script @if ($nonce) nonce="{{ $nonce }}" @endif>
(function () {
    var input = document.getElementById('glamAutocomplete');
    var resultsDiv = document.getElementById('autocompleteResults');
    var selectedId = document.getElementById('selectedItemId');
    var card = document.getElementById('selectedItemCard');
    var addBtn = document.getElementById('addItemBtn');
    var titleEl = document.getElementById('selectedTitle');
    var idEl = document.getElementById('selectedIdentifier');
    var lvlEl = document.getElementById('selectedLevel');
    var clearBtn = document.getElementById('clearSelectionBtn');
    var timer = null;

    function escapeHtml(t) {
        if (!t) return '';
        var d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    function selectItem(item) {
        selectedId.value = item.id;
        input.value = '';
        resultsDiv.style.display = 'none';
        titleEl.textContent = item.value || '';
        idEl.textContent = item.identifier || '';
        lvlEl.textContent = item.level || '';
        lvlEl.style.display = item.level ? 'inline-block' : 'none';
        card.style.display = 'block';
        addBtn.disabled = false;
    }

    function clearSelection() {
        selectedId.value = '';
        card.style.display = 'none';
        addBtn.disabled = true;
        input.focus();
    }

    if (clearBtn) clearBtn.addEventListener('click', clearSelection);

    if (input) {
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 2) { resultsDiv.style.display = 'none'; return; }
            timer = setTimeout(function () {
                fetch('/api/autocomplete/glam?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!Array.isArray(data) || data.length === 0) {
                            resultsDiv.innerHTML = '<div class="autocomplete-item text-muted">{{ __('No results found') }}</div>';
                            resultsDiv.style.display = 'block';
                            return;
                        }
                        resultsDiv.innerHTML = '';
                        data.forEach(function (item) {
                            var div = document.createElement('div');
                            div.className = 'autocomplete-item';
                            div.innerHTML = '<div class="item-title">' + escapeHtml(item.value) + '</div>'
                                + '<div class="item-meta">'
                                + (item.identifier ? '<code>' + escapeHtml(item.identifier) + '</code> • ' : '')
                                + (item.level ? '<span class="badge bg-secondary">' + escapeHtml(item.level) + '</span>' : '')
                                + '</div>';
                            div.addEventListener('click', function () { selectItem(item); });
                            resultsDiv.appendChild(div);
                        });
                        resultsDiv.style.display = 'block';
                    })
                    .catch(function () { resultsDiv.style.display = 'none'; });
            }, 300);
        });

        input.addEventListener('keydown', function (e) {
            var items = resultsDiv.querySelectorAll('.autocomplete-item:not(.text-muted)');
            var active = resultsDiv.querySelector('.autocomplete-item.active');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!active && items.length > 0) items[0].classList.add('active');
                else if (active && active.nextElementSibling) {
                    active.classList.remove('active');
                    active.nextElementSibling.classList.add('active');
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (active && active.previousElementSibling) {
                    active.classList.remove('active');
                    active.previousElementSibling.classList.add('active');
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (active) active.click();
            } else if (e.key === 'Escape') {
                resultsDiv.style.display = 'none';
            }
        });
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#glamAutocomplete') && !e.target.closest('#autocompleteResults')) {
            resultsDiv.style.display = 'none';
        }
    });
})();
</script>
