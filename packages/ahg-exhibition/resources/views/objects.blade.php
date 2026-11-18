{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Exhibition Objects')

@section('content')
@php
  $exhibition = $exhibition ?? (object) [];
  $exId = $exhibition->id ?? 0;
  $objects = $exhibition->objects ?? collect();
  $sections = $exhibition->sections ?? collect();
  $currentSection = $currentSection ?? request('section', '');
@endphp

<div class="row">
  <div class="col-md-8">
    <nav aria-label="{{ __('breadcrumb') }}">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('exhibition.index') }}">{{ __('Exhibitions') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('exhibition.show', ['id' => $exId]) }}">{{ $exhibition->title ?? '' }}</a></li>
        <li class="breadcrumb-item active">{{ __('Objects') }}</li>
      </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1>{{ __('Exhibition Objects') }}</h1>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addObjectModal">
        <i class="fas fa-plus"></i> {{ __('Add Object') }}
      </button>
    </div>

    @if(empty($objects) || (is_object($objects) && method_exists($objects, 'isEmpty') && $objects->isEmpty()))
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="fas fa-archive fa-3x text-muted mb-3"></i>
          <h5>{{ __('No objects added yet') }}</h5>
          <p class="text-muted">{{ __('Add objects from the collection to this exhibition.') }}</p>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addObjectModal">
            <i class="fas fa-plus"></i> {{ __('Add First Object') }}
          </button>
        </div>
      </div>
    @else
      @if(!empty($sections) && (!is_object($sections) || !method_exists($sections, 'isEmpty') || !$sections->isEmpty()))
        <div class="card mb-3">
          <div class="card-body py-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="small text-muted">{{ __('Filter by section:') }}</span>
              <a href="?" class="btn btn-sm {{ empty($currentSection) ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('All') }}</a>
              @foreach($sections as $section)
                @php $sec = (object) $section; @endphp
                <a href="?section={{ $sec->id ?? '' }}" class="btn btn-sm {{ $currentSection == ($sec->id ?? '') ? 'btn-primary' : 'btn-outline-secondary' }}">
                  {{ $sec->title ?? ($sec->name ?? '') }}
                </a>
              @endforeach
            </div>
          </div>
        </div>
      @endif

      <div class="card">
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th style="width: 60px;"></th>
                <th>{{ __('Object') }}</th>
                <th>{{ __('Section') }}</th>
                <th>{{ __('Location') }}</th>
                <th>{{ __('Loan') }}</th>
                <th>{{ __('Display Order') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody id="objectList">
              @foreach($objects as $object)
                @php $obj = (object) $object; @endphp
                <tr data-id="{{ $obj->id ?? '' }}">
                  <td class="text-center">
                    <i class="fas fa-grip-vertical text-muted drag-handle" style="cursor: move;"></i>
                  </td>
                  <td>
                    @if(!empty($obj->slug))
                      <a href="{{ url('/'.$obj->slug) }}">
                        <strong>{{ $obj->object_title ?? ($obj->identifier ?? '') }}</strong>
                      </a>
                    @else
                      <strong>{{ $obj->object_title ?? ($obj->identifier ?? '') }}</strong>
                    @endif
                    @if(!empty($obj->identifier))
                      <br><small class="text-muted">{{ $obj->identifier }}</small>
                    @endif
                  </td>
                  <td>
                    @if(!empty($obj->section_title))
                      {{ $obj->section_title }}
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td>
                    @if(!empty($obj->display_position))
                      {{ $obj->display_position }}
                    @else
                      <span class="text-muted">{{ __('Not assigned') }}</span>
                    @endif
                  </td>
                  <td>
                    @if(!empty($obj->requires_loan))
                      <span class="badge bg-warning text-dark">{{ __('Loan required') }}</span>
                      @if(!empty($obj->lender_institution))
                        <br><small class="text-muted">{{ $obj->lender_institution }}</small>
                      @endif
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td>
                    <span class="badge bg-secondary order-badge">{{ $obj->sequence_order ?? '-' }}</span>
                    @if(!empty($obj->status_label))
                      <br><small class="text-muted">{{ $obj->status_label }}</small>
                    @endif
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <button type="button" class="btn btn-outline-primary" title="{{ __('Edit') }}"
                              data-bs-toggle="modal" data-bs-target="#editObjectModal"
                              data-action="{{ route('exhibition.objects.update', ['id' => $exId, 'objectId' => $obj->id]) }}"
                              data-section="{{ $obj->section_id ?? '' }}"
                              data-location="{{ $obj->display_position ?? '' }}"
                              data-notes="{{ $obj->installation_notes ?? '' }}"
                              data-order="{{ $obj->sequence_order ?? '' }}"
                              data-insurance="{{ $obj->insurance_value ?? '' }}"
                              data-status="{{ $obj->status ?? '' }}"
                              data-requires-loan="{{ !empty($obj->requires_loan) ? '1' : '0' }}"
                              data-lender="{{ $obj->lender_institution ?? '' }}">
                        <i class="fas fa-edit"></i>
                      </button>
                      <form method="post" action="{{ route('exhibition.objects.remove', ['id' => $exId, 'objectId' => $obj->id]) }}" class="d-inline"
                            data-confirm="{{ __('Remove ":title" from this exhibition?', ['title' => $obj->object_title ?? ($obj->identifier ?? '')]) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Remove') }}"><i class="fas fa-times"></i></button>
                      </form>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="card-footer">
          <span class="text-muted">{{ __(':count objects in exhibition', ['count' => is_countable($objects) ? count($objects) : 0]) }}</span>
        </div>
      </div>
    @endif
  </div>

  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Exhibition Info') }}</h5>
      </div>
      <div class="card-body">
        <h6>{{ $exhibition->title ?? '' }}</h6>
        <p class="small text-muted mb-2">
          <span class="badge bg-secondary">{{ $exhibition->status ?? '' }}</span>
        </p>
        @if(!empty($exhibition->opening_date))
          <p class="small mb-1">
            <i class="fas fa-calendar me-1"></i>
            {{ $exhibition->opening_date }}
            @if(!empty($exhibition->closing_date)) - {{ $exhibition->closing_date }} @endif
          </p>
        @endif
      </div>
    </div>

    @if(!empty($sections) && (!is_object($sections) || !method_exists($sections, 'isEmpty') || !$sections->isEmpty()))
      <div class="card mb-3">
        <div class="card-header">
          <h5 class="mb-0">{{ __('Sections') }}</h5>
        </div>
        <ul class="list-group list-group-flush">
          @foreach($sections as $section)
            @php $sec = (object) $section; @endphp
            <li class="list-group-item d-flex justify-content-between align-items-center">
              {{ $sec->title ?? ($sec->name ?? '') }}
              <span class="badge bg-primary rounded-pill">{{ $sec->object_count ?? 0 }}</span>
            </li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Actions') }}</h5>
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('exhibition.objectList', ['id' => $exId]) }}" class="list-group-item list-group-item-action">
          <i class="fas fa-file-text me-2"></i> {{ __('Generate Object List') }}
        </a>
        <a href="{{ route('exhibition.sections', ['id' => $exId]) }}" class="list-group-item list-group-item-action">
          <i class="fas fa-th-large me-2"></i> {{ __('Manage Sections') }}
        </a>
        @php
          // Slugs of the objects placed in this exhibition. The batch label
          // printer already takes slugs and now renders a local QR per label;
          // this hands it the exhibition's objects so a curator does not have to
          // reselect them one by one to print case labels.
          $ahgLabelSlugs = collect($objects ?? [])->pluck('slug')->filter()->unique()->take(100)->values();
        @endphp
        @if($ahgLabelSlugs->isNotEmpty() && \Illuminate\Support\Facades\Route::has('ahglabel.batch'))
        <form method="POST" action="{{ route('ahglabel.batch') }}" target="_blank" class="m-0">
          @csrf
          @foreach($ahgLabelSlugs as $ahgSlug)
            <input type="hidden" name="slugs[]" value="{{ $ahgSlug }}">
          @endforeach
          <input type="hidden" name="show_qr" value="1">
          <button type="submit" class="list-group-item list-group-item-action w-100 text-start border-0">
            <i class="fas fa-qrcode me-2"></i> {{ __('Print object labels with QR') }}
            <span class="text-muted small">({{ $ahgLabelSlugs->count() }})</span>
          </button>
        </form>
        @endif
      </div>
    </div>
  </div>
</div>

@php
  $sectionOptions = collect($sections)->map(fn ($sec) => (object) $sec);
@endphp
<div class="modal fade" id="addObjectModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Add Object to Exhibition') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="{{ route('exhibition.objects.store', ['id' => $exId]) }}">
        @csrf
        <input type="hidden" name="_form" value="add_object">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="objectSearch">{{ __('Search Objects') }} <span class="text-danger">*</span></label>
            <input type="text" id="objectSearch" class="form-control" autocomplete="off" placeholder="{{ __('Search by title or identifier...') }}"
                   data-search-url="{{ route('exhibition.objects.search', ['id' => $exId]) }}">
            <div id="searchResults" class="list-group mt-2" style="max-height: 200px; overflow-y: auto;"></div>
            <input type="hidden" name="information_object_id" id="selectedObjectId" value="{{ old('information_object_id') }}">
            <div id="selectedObject" class="alert alert-info mt-2 d-none"></div>
          </div>

          @if($sectionOptions->isNotEmpty())
            <div class="mb-3">
              <label class="form-label" for="addSectionId">{{ __('Section') }}</label>
              <select name="section_id" id="addSectionId" class="form-select">
                <option value="">{{ __('-- No section --') }}</option>
                @foreach($sectionOptions as $sec)
                  <option value="{{ $sec->id }}" @selected(old('section_id') == $sec->id)>{{ $sec->title ?? '' }}</option>
                @endforeach
              </select>
            </div>
          @endif

          <div class="mb-3">
            <label class="form-label">{{ __('Display Location') }}</label>
            <input type="text" name="display_position" class="form-control" maxlength="100" value="{{ old('display_position') }}" placeholder="{{ __('e.g., Gallery A, Case 3') }}">
          </div>

          <div class="mb-3">
            <label class="form-label">{{ __('Display Notes') }}</label>
            <textarea name="installation_notes" class="form-control" rows="2" placeholder="{{ __('Special display requirements...') }}">{{ old('installation_notes') }}</textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">{{ __('Insurance Value') }}</label>
            <input type="number" name="insurance_value" class="form-control" min="0" step="0.01" value="{{ old('insurance_value') }}">
          </div>

          <h6 class="text-muted mt-4"><i class="fas fa-handshake me-1"></i> {{ __('Loan Details') }}</h6>
          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input js-toggle-lender" name="requires_loan" id="addRequiresLoan" value="1" data-target="#addLenderFields" @checked(old('requires_loan'))>
            <label class="form-check-label" for="addRequiresLoan">{{ __('Requires loan from another institution') }}</label>
          </div>
          <div id="addLenderFields" class="{{ old('requires_loan') ? '' : 'd-none' }}">
            <div class="mb-3">
              <label class="form-label">{{ __('Lender Institution') }}</label>
              <input type="text" name="lender_institution" class="form-control" maxlength="255" value="{{ old('lender_institution') }}" placeholder="{{ __('Institution lending the object') }}">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Add Object') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editObjectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Edit Object Placement') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="" id="editObjectForm">
        @csrf
        <div class="modal-body">
          @if($sectionOptions->isNotEmpty())
            <div class="mb-3">
              <label class="form-label" for="editSectionId">{{ __('Section') }}</label>
              <select name="section_id" id="editSectionId" class="form-select">
                <option value="">{{ __('-- No section --') }}</option>
                @foreach($sectionOptions as $sec)
                  <option value="{{ $sec->id }}">{{ $sec->title ?? '' }}</option>
                @endforeach
              </select>
            </div>
          @endif

          <div class="mb-3">
            <label class="form-label" for="editStatus">{{ __('Status') }}</label>
            <select name="status" id="editStatus" class="form-select">
              @foreach(($objectStatuses ?? []) as $code => $label)
                <option value="{{ $code }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editStatusNotes">{{ __('Status change note') }}</label>
            <input type="text" name="status_notes" id="editStatusNotes" class="form-control" placeholder="{{ __('Added to the installation notes when the status changes') }}">
          </div>

          <div class="mb-3">
            <label class="form-label" for="editLocation">{{ __('Display Location') }}</label>
            <input type="text" name="display_position" id="editLocation" class="form-control" maxlength="100">
          </div>

          <div class="mb-3">
            <label class="form-label" for="editNotes">{{ __('Display Notes') }}</label>
            <textarea name="installation_notes" id="editNotes" class="form-control" rows="2"></textarea>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="editOrder">{{ __('Display Order') }}</label>
              <input type="number" name="sequence_order" id="editOrder" class="form-control" min="0">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="editInsurance">{{ __('Insurance Value') }}</label>
              <input type="number" name="insurance_value" id="editInsurance" class="form-control" min="0" step="0.01">
            </div>
          </div>

          <h6 class="text-muted mt-2"><i class="fas fa-handshake me-1"></i> {{ __('Loan Details') }}</h6>
          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input js-toggle-lender" name="requires_loan" id="editRequiresLoan" value="1" data-target="#editLenderFields">
            <label class="form-check-label" for="editRequiresLoan">{{ __('Requires loan from another institution') }}</label>
          </div>
          <div id="editLenderFields" class="d-none">
            <div class="mb-3">
              <label class="form-label" for="editLenderInstitution">{{ __('Lender Institution') }}</label>
              <input type="text" name="lender_institution" id="editLenderInstitution" class="form-control" maxlength="255" placeholder="{{ __('Institution lending the object') }}">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('js')
<script src="{{ asset('vendor/ahg-theme-b5/js/sortable.min.js') }}"></script>
<script nonce="{{ csp_nonce() }}">
document.addEventListener('DOMContentLoaded', function () {
  var i18n = {
    noResults: @json(__('No objects found')),
    orderError: @json(__('The new order could not be saved.')),
  };
  var csrf = document.querySelector('meta[name="csrf-token"]');
  csrf = csrf ? csrf.getAttribute('content') : '';

  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) { e.preventDefault(); } });
  });

  document.querySelectorAll('.js-toggle-lender').forEach(function (cb) {
    cb.addEventListener('change', function () {
      document.querySelector(cb.dataset.target).classList.toggle('d-none', !cb.checked);
    });
  });

  // Object picker
  var search = document.getElementById('objectSearch');
  var results = document.getElementById('searchResults');
  var timer;
  function label(obj) { return obj.title || obj.object_number || ('#' + obj.id); }
  search.addEventListener('input', function () {
    clearTimeout(timer);
    var q = search.value.trim();
    if (q.length < 2) { results.innerHTML = ''; return; }
    timer = setTimeout(function () {
      fetch(search.dataset.searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          results.innerHTML = '';
          var list = (data && data.objects) || [];
          if (!list.length) {
            var none = document.createElement('div');
            none.className = 'list-group-item text-muted';
            none.textContent = i18n.noResults;
            results.appendChild(none);
            return;
          }
          list.forEach(function (obj) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action';
            var strong = document.createElement('strong');
            strong.textContent = label(obj);
            item.appendChild(strong);
            if (obj.object_number) {
              var small = document.createElement('small');
              small.className = 'text-muted d-block';
              small.textContent = obj.object_number;
              item.appendChild(small);
            }
            item.addEventListener('click', function () {
              document.getElementById('selectedObjectId').value = obj.id;
              var sel = document.getElementById('selectedObject');
              sel.textContent = label(obj) + (obj.object_number ? ' (' + obj.object_number + ')' : '');
              sel.classList.remove('d-none');
              results.innerHTML = '';
              search.value = '';
            });
            results.appendChild(item);
          });
        });
    }, 300);
  });

  // Edit modal
  document.getElementById('editObjectModal').addEventListener('show.bs.modal', function (event) {
    var b = event.relatedTarget;
    document.getElementById('editObjectForm').action = b.dataset.action;
    var sec = document.getElementById('editSectionId');
    if (sec) { sec.value = b.dataset.section || ''; }
    document.getElementById('editStatus').value = b.dataset.status || '';
    document.getElementById('editStatusNotes').value = '';
    document.getElementById('editLocation').value = b.dataset.location || '';
    document.getElementById('editNotes').value = b.dataset.notes || '';
    document.getElementById('editOrder').value = b.dataset.order || '';
    document.getElementById('editInsurance').value = b.dataset.insurance || '';
    var loan = b.dataset.requiresLoan === '1';
    document.getElementById('editRequiresLoan').checked = loan;
    document.getElementById('editLenderFields').classList.toggle('d-none', !loan);
    document.getElementById('editLenderInstitution').value = b.dataset.lender || '';
  });

  // Drag-and-drop order
  var list = document.getElementById('objectList');
  if (list && window.Sortable) {
    new Sortable(list, {
      handle: '.drag-handle',
      animation: 150,
      ghostClass: 'table-warning',
      onEnd: function () {
        var order = [];
        list.querySelectorAll('tr[data-id]').forEach(function (row, i) {
          order.push({ id: parseInt(row.dataset.id, 10), sequence_order: i + 1 });
          var badge = row.querySelector('.order-badge');
          if (badge) { badge.textContent = i + 1; }
        });
        fetch(@json(route('exhibition.objects.reorder', ['id' => $exId])), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
          body: JSON.stringify({ order: order })
        }).then(function (r) { if (!r.ok) { throw new Error(); } })
          .catch(function () { alert(i18n.orderError); });
      }
    });
  }

  @if($errors->any() && old('_form') === 'add_object')
    if (window.bootstrap) { bootstrap.Modal.getOrCreateInstance(document.getElementById('addObjectModal')).show(); }
  @endif
});
</script>
@endpush
