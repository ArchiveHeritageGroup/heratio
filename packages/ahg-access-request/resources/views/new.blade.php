@extends('theme::layouts.1col')

@section('title', 'New Access Request')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('homepage') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('accessRequest.myRequests') }}">My Requests</a></li>
                    <li class="breadcrumb-item active">New Request</li>
                </ol>
            </nav>

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header" style="background-color: var(--ahg-primary); color: #fff;">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i>{{ __('New Access Request') }}</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('accessRequest.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                            <input type="text" class="form-control" id="subject" name="subject" required value="{{ old('subject') }}">
                        </div>

                        <div class="mb-3">
                            <label for="request_type" class="form-label">Request Type <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                            <select class="form-select" id="request_type" name="request_type" required>
                                <option value="">-- Select type --</option>
                                <option value="view">{{ __('View restricted material') }}</option>
                                <option value="copy">{{ __('Request copies') }}</option>
                                <option value="publish">{{ __('Permission to publish') }}</option>
                                <option value="research">{{ __('Research access') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                        </div>

                        {{-- Scope: what the request covers. The satellite table
                             access_request_scope already carried object_id +
                             include_descendants; this is the form finally
                             letting a requester say which they mean. --}}
                        @php $scope = old('scope_type', 'all'); @endphp
                        <fieldset class="mb-3">
                            <legend class="form-label fs-6 mb-2">{{ __('What do you need access to?') }} <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></legend>

                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="scope_type" id="scope_all" value="all" {{ $scope === 'all' ? 'checked' : '' }}>
                                <label class="form-check-label" for="scope_all">
                                    {{ __('Everything') }}
                                    <span class="d-block form-text">{{ __('All holdings covered by the classification level you request below.') }}</span>
                                </label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="scope_type" id="scope_collection" value="collection" {{ $scope === 'collection' ? 'checked' : '' }}>
                                <label class="form-check-label" for="scope_collection">
                                    {{ __('A collection') }}
                                    <span class="d-block form-text">{{ __('One collection and everything catalogued inside it.') }}</span>
                                </label>
                            </div>

                            <div class="ms-4 mb-2 ahg-scope-target" id="scope_collection_wrap">
                                @if($collections->isNotEmpty())
                                    <select class="form-select @error('scope_collection_id') is-invalid @enderror" name="scope_collection_id" id="scope_collection_id">
                                        <option value="">{{ __('-- Select a collection --') }}</option>
                                        @foreach($collections as $c)
                                            <option value="{{ $c->id }}" {{ (int) old('scope_collection_id') === (int) $c->id ? 'selected' : '' }}>
                                                {{ $c->title }} ({{ $c->level_name }})
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <div class="form-text text-warning">{{ __('No collection-level records were found, so this option has nothing to offer. Request a single item or everything instead.') }}</div>
                                @endif
                                @error('scope_collection_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="scope_type" id="scope_item" value="item" {{ $scope === 'item' ? 'checked' : '' }}>
                                <label class="form-check-label" for="scope_item">
                                    {{ __('A single item') }}
                                    <span class="d-block form-text">{{ __('One record only - its children are not included.') }}</span>
                                </label>
                            </div>

                            <div class="ms-4 ahg-scope-target" id="scope_item_wrap">
                                <input type="text" class="form-control" id="scope_item_search" list="scope_item_options"
                                       placeholder="{{ __('Start typing a title or reference code...') }}"
                                       autocomplete="off" value="{{ old('scope_item_title') }}">
                                <datalist id="scope_item_options"></datalist>
                                <input type="hidden" name="scope_item_id" id="scope_item_id" value="{{ old('scope_item_id') }}">
                                <input type="hidden" name="scope_item_title" id="scope_item_title" value="{{ old('scope_item_title') }}">
                                <div class="form-text" id="scope_item_chosen">
                                    @if(old('scope_item_id'))
                                        {{ __('Selected:') }} {{ old('scope_item_title') }}
                                    @else
                                        {{ __('Pick a record from the suggestions so the reviewer knows exactly which one you mean.') }}
                                    @endif
                                </div>
                                @error('scope_item_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </fieldset>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                            <textarea class="form-control" id="description" name="description" rows="5" required>{{ old('description') }}</textarea>
                            <div class="form-text">Describe the materials you need access to and the purpose of your request.</div>
                        </div>

                        <div class="mb-3">
                            <label for="justification" class="form-label">Justification <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <textarea class="form-control" id="justification" name="justification" rows="3">{{ old('justification') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="urgency" class="form-label">Urgency <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <select class="form-select" id="urgency" name="urgency">
                                <option value="low" {{ old('urgency') === 'low' ? 'selected' : '' }}>{{ __('Low — no fixed deadline') }}</option>
                                <option value="normal" {{ (old('urgency') ?? 'normal') === 'normal' ? 'selected' : '' }}>{{ __('Normal — within standard turnaround') }}</option>
                                <option value="high" {{ old('urgency') === 'high' ? 'selected' : '' }}>{{ __('High — needed soon') }}</option>
                                <option value="urgent" {{ old('urgency') === 'urgent' ? 'selected' : '' }}>{{ __('Urgent — needed ASAP') }}</option>
                            </select>
                            <div class="form-text">Helps reviewers prioritise the queue.</div>
                        </div>

                        @if(isset($classifications) && $classifications->isNotEmpty())
                        <div class="mb-3">
                            <label for="requested_classification_id" class="form-label">Requested classification level <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <select class="form-select" id="requested_classification_id" name="requested_classification_id">
                                <option value="">-- Default (lowest level) --</option>
                                @foreach($classifications as $cls)
                                    <option value="{{ $cls->id }}" {{ (int) old('requested_classification_id') === (int) $cls->id ? 'selected' : '' }}>
                                        {{ $cls->name }} (level {{ $cls->level }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">The security classification level you need access to.</div>
                        </div>
                        @endif

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('accessRequest.myRequests') }}" class="atom-btn-white">Cancel</a>
                            <button type="submit" class="atom-btn-white">
                                <i class="fas fa-paper-plane me-1"></i>{{ __('Submit Request') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
(function () {
  var wraps = { collection: document.getElementById('scope_collection_wrap'),
                item:       document.getElementById('scope_item_wrap') };
  var radios = document.querySelectorAll('input[name="scope_type"]');

  function sync() {
    var chosen = document.querySelector('input[name="scope_type"]:checked');
    var value  = chosen ? chosen.value : 'all';
    Object.keys(wraps).forEach(function (k) {
      if (wraps[k]) { wraps[k].style.display = (value === k) ? '' : 'none'; }
    });
  }
  radios.forEach(function (r) { r.addEventListener('change', sync); });
  sync();

  // Item picker. Uses the existing informationobject/autocomplete endpoint,
  // which answers [{id,name,slug}]. A datalist keeps this dependency-free;
  // the hidden id is what the server trusts, and it is cleared whenever the
  // text no longer matches a suggestion so a half-typed title cannot be
  // submitted as if a record had been chosen.
  var search = document.getElementById('scope_item_search');
  var list   = document.getElementById('scope_item_options');
  var idFld  = document.getElementById('scope_item_id');
  var ttlFld = document.getElementById('scope_item_title');
  var chosenNote = document.getElementById('scope_item_chosen');
  if (!search) { return; }

  var matches = {}, timer = null;

  function clearChoice() {
    idFld.value = ''; ttlFld.value = '';
    chosenNote.textContent = @json(__('Pick a record from the suggestions so the reviewer knows exactly which one you mean.'));
  }

  search.addEventListener('input', function () {
    var text = search.value.trim();

    if (matches[text]) {
      idFld.value = matches[text].id;
      ttlFld.value = matches[text].name;
      chosenNote.textContent = @json(__('Selected:')) + ' ' + matches[text].name;
      return;
    }
    clearChoice();
    if (text.length < 2) { return; }

    clearTimeout(timer);
    timer = setTimeout(function () {
      fetch('{{ url('informationobject/autocomplete') }}?query=' + encodeURIComponent(text), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (rows) {
        list.innerHTML = '';
        (rows || []).forEach(function (row) {
          matches[row.name] = row;
          var o = document.createElement('option');
          o.value = row.name;
          list.appendChild(o);
        });
      })
      .catch(function () { /* leave the previous suggestions in place */ });
    }, 250);
  });
})();
</script>
@endpush
@endsection
