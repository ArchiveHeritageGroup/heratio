{{--
    auth-res::queue - Heratio authority-resolution review queue (Bootstrap 5).

    Pending NER mentions promoted into the authority-resolution workflow.
    Click any row to jump to /admin/authority-resolution/review/{id}.
--}}
@extends('theme::layouts.1col')

@section('title', 'Authority Resolution Queue')

@section('content')
@php
    $typeBadges = [
        'PERSON'     => 'primary',
        'ORG'        => 'info',
        'GPE'        => 'success',
        'LOC'        => 'success',
        'PLACE'      => 'success',
        'ISAD_PLACE' => 'success',
    ];
    $stateBadges = [
        'pending'             => 'warning',
        'linked'              => 'success',
        'parked'              => 'info',
        'rejected'            => 'secondary',
        'new_record_created'  => 'primary',
    ];
@endphp
<div class="container py-4">

    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">{{ __('Authority Resolution') }}</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">
            <i class="bi bi-people me-2"></i>{{ __('Authority Resolution Queue') }}
        </h1>
        <div class="btn-group">
            <a href="{{ route('auth-res.park.index') }}" class="btn btn-outline-info">
                <i class="bi bi-pause-circle me-1"></i>{{ __('Parked') }}
            </a>
            <a href="{{ route('auth-res.settings.show') }}" class="btn btn-outline-secondary">
                <i class="bi bi-sliders me-1"></i>{{ __('Lookup settings') }}
            </a>
        </div>
    </div>

    <p class="text-muted mb-3">
        {{ __('Pending NER mentions promoted into the authority-resolution workflow. Pick a mention to see its evidence packet and ranked candidates.') }}
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

    {{-- State KPIs --}}
    <div class="row g-2 mb-3">
        @foreach(['pending', 'linked', 'parked', 'rejected', 'new_record_created'] as $state)
            <div class="col-md col-sm-4">
                <a href="{{ route('auth-res.queue', ['state' => $state]) }}"
                   class="text-decoration-none">
                    <div class="card text-center border-{{ $stateBadges[$state] ?? 'secondary' }}">
                        <div class="card-body py-2">
                            <h4 class="mb-0">{{ number_format($counts[$state] ?? 0) }}</h4>
                            <small class="text-muted">{{ ucwords(str_replace('_', ' ', $state)) }}</small>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('auth-res.queue') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">{{ __('Entity type') }}</label>
                    <select name="entity_type" class="form-select form-select-sm">
                        <option value="">{{ __('All types') }}</option>
                        @foreach(['PERSON', 'ORG', 'GPE', 'LOC', 'PLACE'] as $et)
                            <option value="{{ $et }}" {{ $filterEntityType === $et ? 'selected' : '' }}>{{ $et }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">{{ __('State') }}</label>
                    <select name="state" class="form-select form-select-sm">
                        @foreach(['pending', 'linked', 'parked', 'rejected', 'new_record_created', 'any'] as $s)
                            <option value="{{ $s }}" {{ $filterState === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">{{ __('Object ID') }}</label>
                    <input type="number" name="object_id"
                           value="{{ $filterObjectId ?: '' }}"
                           class="form-control form-control-sm"
                           placeholder="{{ __('any') }}">
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i>{{ __('Filter') }}
                    </button>
                    <a href="{{ route('auth-res.queue') }}" class="btn btn-sm btn-link">
                        {{ __('Reset') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Result table --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>{{ trans_choice('{0} no mentions|{1} :count mention|[2,*] :count mentions', $rows->count(), ['count' => number_format($rows->count())]) }}</span>
            <small class="text-muted">{{ __('Sorted by mention id') }}</small>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-striped mb-0 align-middle" id="ar-queue-table">
                <thead>
                    <tr>
                        <th style="width: 2.5rem;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="ar-select-all-page"
                                   title="{{ __('Select all on this page') }}">
                        </th>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Mention') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Source') }}</th>
                        <th class="text-center">{{ __('Candidates') }}</th>
                        <th>{{ __('State') }}</th>
                        <th>{{ __('Assigned to') }}</th>
                        <th>{{ __('Promoted') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input ar-row-check"
                                       value="{{ (int) $r->id }}"
                                       data-mention-id="{{ (int) $r->id }}">
                            </td>
                            <td class="text-muted small">#{{ (int) $r->id }}</td>
                            <td><strong>{{ $r->entity_value }}</strong></td>
                            <td>
                                <span class="badge bg-{{ $typeBadges[$r->entity_type] ?? 'secondary' }}">
                                    {{ $r->entity_type }}
                                </span>
                            </td>
                            <td class="small">
                                @php
                                    $doMime  = (string) ($r->do_mime_type ?? '');
                                    $doIcon  = null;
                                    $doTitle = null;
                                    if ($doMime !== '') {
                                        if ($doMime === 'application/pdf') {
                                            $doIcon = 'bi-file-pdf';
                                            $doTitle = __('Attached PDF') . ($r->do_name ? ': ' . $r->do_name : '');
                                        } elseif (str_starts_with($doMime, 'image/')) {
                                            $doIcon = 'bi-file-image';
                                            $doTitle = __('Attached image') . ($r->do_name ? ': ' . $r->do_name : '');
                                        } else {
                                            $doIcon = 'bi-file-earmark';
                                            $doTitle = __('Attached file') . ' (' . $doMime . ')'
                                                . ($r->do_name ? ' - ' . $r->do_name : '');
                                        }
                                    }
                                    $srcLabel = $r->io_identifier ?: ('Object #' . (int) $r->object_id);
                                @endphp
                                @if(!empty($r->io_slug))
                                    <a href="{{ url('/' . $r->io_slug) }}" target="_blank" rel="noopener"
                                       title="{{ __('Open source information object') }}">
                                        {{ $srcLabel }}
                                        <i class="bi bi-box-arrow-up-right small ms-1"></i>
                                    </a>
                                @else
                                    <span class="text-muted">{{ $srcLabel }}</span>
                                @endif
                                @if($doIcon)
                                    <i class="bi {{ $doIcon }} ms-1 text-secondary" title="{{ $doTitle }}"></i>
                                @else
                                    <span class="text-muted ms-1" title="{{ __('No digital object attached') }}">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ ((int) $r->candidate_count) > 0 ? 'dark' : 'light text-dark border' }}">
                                    {{ (int) $r->candidate_count }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $stateBadges[$r->state] ?? 'secondary' }}">
                                    {{ $r->state }}
                                </span>
                            </td>
                            <td class="small">
                                @if(!empty($r->assigned_to_user_id))
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-person-check me-1"></i>{{ $assigneeNames[(int) $r->assigned_to_user_id] ?? ('User #' . (int) $r->assigned_to_user_id) }}
                                    </span>
                                @else
                                    <span class="text-muted">{{ __('-') }}</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $r->promoted_at }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('auth-res.review.show', ['mention' => $r->id]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-search me-1"></i>{{ __('Review') }}
                                </a>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary ar-row-assign-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#ar-queue-assign-modal"
                                        data-mention-id="{{ (int) $r->id }}"
                                        title="{{ __('Assign this mention') }}">
                                    <i class="bi bi-person-check me-1"></i>{{ __('Assign') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                {{ __('No mentions match the current filter.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->count() === 200)
            <div class="card-footer text-muted small">
                {{ __('Showing first 200 rows. Tighten the filters to narrow the list.') }}
            </div>
        @endif
    </div>
</div>

{{-- ================ BATCH ASSIGN: sticky action bar ================ --}}
<div id="ar-batch-bar" class="d-none fixed-bottom bg-dark text-white shadow-lg">
    <div class="container py-2">
        <form method="POST" action="{{ route('auth-res.queue.assign') }}" id="ar-batch-form"
              class="row g-2 align-items-center">
            @csrf
            <div class="col-auto">
                <span class="badge bg-primary fs-6">
                    <span id="ar-batch-count">0</span> {{ __('selected') }}
                </span>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-light" id="ar-select-all-filter">
                    {{ __('Select all') }} {{ number_format($filteredTotal) }} {{ __('matching filter') }}
                </button>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-link text-white" id="ar-batch-clear">
                    {{ __('Clear selection') }}
                </button>
            </div>
            <div class="col-md-3 ms-auto">
                <select name="archivist_user_id" class="form-select form-select-sm" required>
                    <option value="">{{ __('Select an archivist...') }}</option>
                    @foreach($archivists as $a)
                        <option value="{{ (int) $a['id'] }}">
                            {{ $a['name'] }}@if(!empty($a['username'])) ({{ $a['username'] }})@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="reason" class="form-control form-control-sm"
                       maxlength="2000"
                       placeholder="{{ __('Reason / message (optional)') }}"
                       title="{{ __('Optional - applied to every selected mention, recorded on the workflow task history.') }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary" @if(empty($archivists)) disabled @endif>
                    <i class="bi bi-person-check me-1"></i>{{ __('Assign selected') }}
                </button>
            </div>
            {{-- mention_ids[] hidden inputs are injected here by JS --}}
            <div id="ar-batch-hidden-ids"></div>
        </form>
    </div>
</div>

{{-- ================ SINGLE-ROW ASSIGN MODAL ================ --}}
<div class="modal fade" id="ar-queue-assign-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('auth-res.queue.assign') }}" id="ar-queue-assign-form">
                @csrf
                <input type="hidden" name="mention_ids[]" id="ar-queue-assign-mention-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-check me-2"></i>{{ __('Assign mention') }}
                        <span id="ar-queue-assign-mention-label" class="text-muted"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        {{ __('Assigning routes the mention through the Workflow plugin: a review task is created (or re-targeted) for the chosen archivist.') }}
                    </p>
                    <div class="mb-3">
                        <label for="ar-queue-assign-archivist" class="form-label">
                            {{ __('Archivist') }} <span class="text-danger">*</span>
                        </label>
                        <select name="archivist_user_id" id="ar-queue-assign-archivist"
                                class="form-select" required>
                            <option value="">{{ __('Select an archivist...') }}</option>
                            @foreach($archivists as $a)
                                <option value="{{ (int) $a['id'] }}">
                                    {{ $a['name'] }}@if(!empty($a['username'])) ({{ $a['username'] }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="ar-queue-assign-reason" class="form-label">
                            {{ __('Reason / message (optional)') }}
                        </label>
                        <textarea name="reason" id="ar-queue-assign-reason"
                                  class="form-control" rows="3" maxlength="2000"
                                  placeholder="{{ __('Add a note for the archivist - why this mention came to them, what to check, etc.') }}"></textarea>
                        <div class="form-text">
                            {{ __('Recorded on the workflow task history so the assignee sees it.') }}
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary" @if(empty($archivists)) disabled @endif>
                        <i class="bi bi-person-check me-1"></i>{{ __('Assign') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('js')
<script nonce="{{ function_exists('csp_nonce') ? csp_nonce() : '' }}">
document.addEventListener('DOMContentLoaded', function () {
    // All mention ids matching the current filter (for "select all N").
    var allFilteredIds = {!! json_encode($allFilteredIds) !!};

    var rowChecks   = Array.prototype.slice.call(document.querySelectorAll('.ar-row-check'));
    var selectAllPg = document.getElementById('ar-select-all-page');
    var batchBar    = document.getElementById('ar-batch-bar');
    var batchCount  = document.getElementById('ar-batch-count');
    var hiddenIds   = document.getElementById('ar-batch-hidden-ids');
    var clearBtn    = document.getElementById('ar-batch-clear');
    var selAllFltr  = document.getElementById('ar-select-all-filter');
    var batchForm   = document.getElementById('ar-batch-form');

    // Selection model: a Set of mention ids. The page checkboxes mirror it
    // where they overlap; "select all matching filter" adds ids that may not
    // be on the visible page.
    var selected = new Set();

    function syncRowChecks() {
        rowChecks.forEach(function (cb) {
            cb.checked = selected.has(parseInt(cb.value, 10));
        });
        if (selectAllPg) {
            var pageIds = rowChecks.map(function (cb) { return parseInt(cb.value, 10); });
            selectAllPg.checked = pageIds.length > 0 && pageIds.every(function (id) {
                return selected.has(id);
            });
        }
    }

    function render() {
        batchCount.textContent = String(selected.size);
        if (selected.size > 0) {
            batchBar.classList.remove('d-none');
            document.body.style.paddingBottom = '72px';
        } else {
            batchBar.classList.add('d-none');
            document.body.style.paddingBottom = '';
        }
        // Rebuild the hidden mention_ids[] inputs for the batch form.
        hiddenIds.innerHTML = '';
        selected.forEach(function (id) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'mention_ids[]';
            inp.value = String(id);
            hiddenIds.appendChild(inp);
        });
        syncRowChecks();
    }

    rowChecks.forEach(function (cb) {
        cb.addEventListener('change', function () {
            var id = parseInt(cb.value, 10);
            if (cb.checked) { selected.add(id); } else { selected.delete(id); }
            render();
        });
    });

    if (selectAllPg) {
        selectAllPg.addEventListener('change', function () {
            rowChecks.forEach(function (cb) {
                var id = parseInt(cb.value, 10);
                if (selectAllPg.checked) { selected.add(id); } else { selected.delete(id); }
            });
            render();
        });
    }

    if (selAllFltr) {
        selAllFltr.addEventListener('click', function () {
            allFilteredIds.forEach(function (id) { selected.add(parseInt(id, 10)); });
            render();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            selected.clear();
            render();
        });
    }

    if (batchForm) {
        batchForm.addEventListener('submit', function (e) {
            if (selected.size === 0) {
                e.preventDefault();
                return;
            }
            var sel = batchForm.querySelector('select[name="archivist_user_id"]');
            if (sel && !sel.value) {
                e.preventDefault();
                sel.focus();
            }
        });
    }

    // ---- Per-row Assign modal ----
    // The trigger button is fully declarative (data-bs-toggle="modal" +
    // data-bs-target) so Bootstrap auto-wires the open regardless of whether
    // window.bootstrap is exposed as a reachable global. We only need to
    // populate the per-row fields (mention id + label) when the modal opens:
    // the show.bs.modal event hands us the button via event.relatedTarget.
    var assignModalEl   = document.getElementById('ar-queue-assign-modal');
    var assignMentionId = document.getElementById('ar-queue-assign-mention-id');
    var assignLabel     = document.getElementById('ar-queue-assign-mention-label');

    if (assignModalEl) {
        assignModalEl.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            var id  = btn ? btn.getAttribute('data-mention-id') : '';
            if (assignMentionId) { assignMentionId.value = id || ''; }
            if (assignLabel) { assignLabel.textContent = id ? ('#' + id) : ''; }
        });
    }

    render();
});
</script>
@endpush
@endsection
