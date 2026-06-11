{{--
  Flag a record as at-risk - admin (heratio#1205)

  Records (or amends) an at-risk flag against a catalogue item for the North Star
  "race against loss". Risk category, urgency and capture status are all
  VARCHAR-backed dropdowns seeded from the canonical workflow values
  (Dropdown-Manager idiom, never an ENUM). When the form is opened from a record
  (?item=), the item reference and any existing flag are prefilled, so re-flagging
  amends rather than duplicates. Factual, non-alarmist helper copy. Full
  server-side validation; errors surfaced inline.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@php
    $isUpdate = !empty($existing);
    $f = function ($key, $default = '') use ($prefill) {
        return old($key, $prefill[$key] ?? $default);
    };
    $currentRisk = old('risk_category', $prefill['risk_category'] ?? 'other');
    $currentUrgency = old('urgency', $prefill['urgency'] ?? 'medium');
    $currentCapture = old('capture_status', $prefill['capture_status'] ?? 'flagged');
@endphp

@section('title', $isUpdate ? __('Update at-risk flag') : __('Flag a record as at-risk'))

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-flag me-2"></i>{{ $isUpdate ? __('Update at-risk flag') : __('Flag a record as at-risk') }}
            </h1>
            <p class="text-muted mb-0">
                {{ __('Record why this item should be captured sooner rather than later, and how urgent it is.') }}
            </p>
        </div>
        <a href="{{ route('endangered.priority') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back to worklist') }}
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
            <strong>{{ __('A prioritisation aid, not a prediction of loss.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    @if($isUpdate)
        <div class="alert alert-info small">
            <i class="fas fa-circle-info me-1"></i>
            {{ __('This item already has an at-risk flag. Saving will update the existing flag.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('endangered.flag') }}">
        @csrf

        <div class="card shadow-sm">
            <div class="card-body">

                {{-- Item reference --}}
                <div class="mb-3">
                    <label for="item_ref" class="form-label">
                        {{ __('Record reference') }} <span class="text-danger">*</span>
                    </label>
                    @if(!empty($itemTitle))
                        <input type="hidden" name="item_ref" value="{{ $f('item_ref') }}">
                        <input type="text" class="form-control" id="item_ref_display"
                               value="#{{ $f('item_ref') }} - {{ $itemTitle }}" disabled>
                    @else
                        <input type="number" min="1" name="item_ref" id="item_ref"
                               class="form-control @error('item_ref') is-invalid @enderror"
                               value="{{ $f('item_ref') }}" required>
                        <div class="form-text">{{ __('The information-object id of the record to flag.') }}</div>
                        @error('item_ref')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endif
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="risk_category" class="form-label">{{ __('Risk category') }} <span class="text-danger">*</span></label>
                        <select name="risk_category" id="risk_category"
                                class="form-select @error('risk_category') is-invalid @enderror" required>
                            @foreach($risks as $key => $meta)
                                <option value="{{ $key }}" {{ strcasecmp($currentRisk, $key) === 0 ? 'selected' : '' }}>
                                    {{ __($meta['label']) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text" id="risk-help"></div>
                        @error('risk_category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="urgency" class="form-label">{{ __('Urgency') }} <span class="text-danger">*</span></label>
                        <select name="urgency" id="urgency"
                                class="form-select @error('urgency') is-invalid @enderror" required>
                            @foreach($urgencies as $key => $meta)
                                <option value="{{ $key }}" {{ strcasecmp($currentUrgency, $key) === 0 ? 'selected' : '' }}>
                                    {{ __($meta['label']) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('How soon capture is needed. Critical items rise to the top of the worklist.') }}</div>
                        @error('urgency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="capture_status" class="form-label">{{ __('Capture status') }} <span class="text-danger">*</span></label>
                        <select name="capture_status" id="capture_status"
                                class="form-select @error('capture_status') is-invalid @enderror" required>
                            @foreach($statuses as $key => $meta)
                                <option value="{{ $key }}" {{ strcasecmp($currentCapture, $key) === 0 ? 'selected' : '' }}>
                                    {{ __($meta['label']) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text" id="capture-help"></div>
                        @error('capture_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="reason" class="form-label">{{ __('Reason') }}</label>
                        <textarea name="reason" id="reason" rows="4" maxlength="20000"
                                  class="form-control @error('reason') is-invalid @enderror">{{ $f('reason') }}</textarea>
                        <div class="form-text">{{ __('A factual note on why this item is at risk and why capture is a priority. State what is known; avoid alarmist or speculative claims.') }}</div>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <a href="{{ route('endangered.priority') }}" class="btn btn-outline-secondary">
                    {{ __('Cancel') }}
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>{{ $isUpdate ? __('Update flag') : __('Flag for capture') }}
                </button>
            </div>
        </div>
    </form>

</div>

@php
    $riskHelp = [];
    foreach ($risks as $k => $m) { $riskHelp[$k] = $m['help'] ?? ''; }
    $captureHelp = [];
    foreach ($statuses as $k => $m) { $captureHelp[$k] = $m['help'] ?? ''; }
@endphp
<script>
(function () {
    function wire(selectId, outId, map) {
        var sel = document.getElementById(selectId);
        var out = document.getElementById(outId);
        if (!sel || !out) return;
        function sync() { out.textContent = map[sel.value] || ''; }
        sel.addEventListener('change', sync);
        sync();
    }
    wire('risk_category', 'risk-help', @json($riskHelp));
    wire('capture_status', 'capture-help', @json($captureHelp));
})();
</script>
@endsection
