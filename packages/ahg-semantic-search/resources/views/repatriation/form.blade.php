{{--
  Repatriation claim form (create / edit) - admin (heratio#1207)

  Registers or amends a structured repatriation claim against a displaced-heritage
  item. On create, the form can be prefilled from a traced item (?item=). On edit,
  the item reference is fixed. Status is a VARCHAR-backed dropdown seeded from the
  canonical workflow values (Dropdown-Manager idiom, never an ENUM). Sensitive
  subject matter: helper copy frames a claim as a documented request and dialogue,
  never a legal outcome. Full server-side validation; errors surfaced inline.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $f = function ($key, $default = '') use ($prefill) {
        return old($key, $prefill[$key] ?? $default);
    };
    $currentStatus = old('claim_status', $prefill['claim_status'] ?? 'registered');
@endphp

@section('title', $isEdit ? __('Edit repatriation claim') : __('Register a repatriation claim'))

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-scale-balanced me-2"></i>{{ $isEdit ? __('Edit repatriation claim') : __('Register a repatriation claim') }}
            </h1>
            <p class="text-muted mb-0">
                {{ __('Record a claim against a traced displaced-heritage item and the stage its dialogue has reached.') }}
            </p>
        </div>
        <a href="{{ route('repatriation.claims.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back to claims') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <p class="mb-1 fw-semibold">{{ __('Please correct the following:') }}</p>
            <ul class="mb-0 small">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Standing disclaimer --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('A documented request and its status, not a determination.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    {{-- Traced-item context (read-only, when available) --}}
    @if(!empty($tracedItem))
        <div class="card border-info shadow-sm mb-4">
            <div class="card-body">
                <div class="text-uppercase text-muted small fw-semibold mb-1">
                    <i class="fas fa-route me-1"></i>{{ __('Traced from the displaced-heritage register') }}
                </div>
                <div class="row g-2 small">
                    <div class="col-md-6">
                        <span class="text-muted">{{ __('Recorded origin') }}:</span>
                        <strong>{{ $tracedItem['origin_region'] ?? '' }}</strong>
                        @if(!empty($tracedItem['origin']['value']))
                            <span class="text-muted">({{ $tracedItem['origin']['value'] }})</span>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted">{{ __('Current holding') }}:</span>
                        <strong>{{ $tracedItem['holding_region'] ?? '' }}</strong>
                        @if(!empty($tracedItem['holding']['value']))
                            <span class="text-muted">({{ $tracedItem['holding']['value'] }})</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <form method="POST"
          action="{{ $isEdit ? route('repatriation.claims.update', ['id' => $claim['id']]) : route('repatriation.claims.store') }}">
        @csrf

        <div class="card shadow-sm">
            <div class="card-body">

                {{-- Item reference --}}
                <div class="mb-3">
                    <label for="item_ref" class="form-label">
                        {{ __('Displaced-heritage item reference') }} <span class="text-danger">*</span>
                    </label>
                    @if($isEdit)
                        <input type="text" class="form-control" id="item_ref_display"
                               value="#{{ $prefill['item_ref'] ?? '' }}{{ !empty($prefill['item_title']) ? ' - '.$prefill['item_title'] : '' }}" disabled>
                        <div class="form-text">{{ __('The item a claim concerns cannot be changed after registration.') }}</div>
                    @else
                        <input type="number" min="1" name="item_ref" id="item_ref"
                               class="form-control @error('item_ref') is-invalid @enderror"
                               value="{{ $f('item_ref') }}" required>
                        <div class="form-text">{{ __('The information-object id of the traced item this claim concerns.') }}</div>
                        @error('item_ref')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endif
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="claimant_community" class="form-label">{{ __('Claimant community') }}</label>
                        <input type="text" name="claimant_community" id="claimant_community" maxlength="512"
                               class="form-control @error('claimant_community') is-invalid @enderror"
                               value="{{ $f('claimant_community') }}">
                        <div class="form-text">{{ __('The community, institution or nation making the claim.') }}</div>
                        @error('claimant_community')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="claim_status" class="form-label">{{ __('Status') }} <span class="text-danger">*</span></label>
                        <select name="claim_status" id="claim_status"
                                class="form-select @error('claim_status') is-invalid @enderror" required>
                            @foreach($statuses as $key => $meta)
                                <option value="{{ $key }}" {{ strcasecmp($currentStatus, $key) === 0 ? 'selected' : '' }}>
                                    {{ __($meta['label']) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text" id="status-help"></div>
                        @error('claim_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="origin_place" class="form-label">{{ __('Place / region of origin') }}</label>
                        <input type="text" name="origin_place" id="origin_place" maxlength="512"
                               class="form-control @error('origin_place') is-invalid @enderror"
                               value="{{ $f('origin_place') }}">
                        @error('origin_place')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="current_holder" class="form-label">{{ __('Current holder') }}</label>
                        <input type="text" name="current_holder" id="current_holder" maxlength="512"
                               class="form-control @error('current_holder') is-invalid @enderror"
                               value="{{ $f('current_holder') }}">
                        @error('current_holder')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="evidence_summary" class="form-label">{{ __('Evidence summary') }}</label>
                        <textarea name="evidence_summary" id="evidence_summary" rows="4" maxlength="20000"
                                  class="form-control @error('evidence_summary') is-invalid @enderror">{{ $f('evidence_summary') }}</textarea>
                        <div class="form-text">{{ __('A factual summary of the documented evidence the claim rests on. State sources; avoid asserting an outcome.') }}</div>
                        @error('evidence_summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="contact" class="form-label">{{ __('Point of contact') }}</label>
                        <input type="text" name="contact" id="contact" maxlength="512"
                               class="form-control @error('contact') is-invalid @enderror"
                               value="{{ $f('contact') }}">
                        @error('contact')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">{{ __('Curatorial / dialogue notes') }}</label>
                        <textarea name="notes" id="notes" rows="3" maxlength="20000"
                                  class="form-control @error('notes') is-invalid @enderror">{{ $f('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <a href="{{ route('repatriation.claims.index') }}" class="btn btn-outline-secondary">
                    {{ __('Cancel') }}
                </a>
                <div class="d-flex gap-2">
                    @if($isEdit && !empty($claim['id']))
                        <a href="{{ route('virtual-return.show', ['id' => $claim['id']]) }}"
                           class="btn btn-outline-dark" target="_blank" rel="noopener">
                            <i class="fas fa-person-walking-arrow-right me-1"></i>{{ __('View virtual return') }}
                        </a>
                    @endif
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>{{ $isEdit ? __('Save claim') : __('Register claim') }}
                    </button>
                </div>
            </div>
        </div>
    </form>

</div>

@php
    $statusHelp = [];
    foreach ($statuses as $k => $m) { $statusHelp[$k] = $m['help'] ?? ''; }
@endphp
<script>
(function () {
    var help = @json($statusHelp);
    var sel = document.getElementById('claim_status');
    var out = document.getElementById('status-help');
    if (!sel || !out) return;
    function sync() { out.textContent = help[sel.value] || ''; }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
