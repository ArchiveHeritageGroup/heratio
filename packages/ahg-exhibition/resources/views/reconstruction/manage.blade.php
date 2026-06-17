{{--
  heratio#1206 - "walk through what no longer exists": admin manage page.
  Link a catalogue record (a lost / destroyed place) to a walkable reconstruction
  space, and remove existing links.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3.0 or later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Reconstructions'))
@section('body-class', 'exhibition-space reconstructions-manage')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1">
      <i class="fas fa-archway me-2"></i>{{ __('Reconstructions') }}
      <small class="text-muted">{{ __('walk through what no longer exists') }}</small>
    </h1>
    <a href="{{ route('exhibition-space.reconstructions') }}" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-eye me-1"></i>{{ __('View public gallery') }}
    </a>
  </div>

  <p class="text-muted small mb-3" style="max-width: 60rem;">
    {{ __('Link a record about a place or building that is lost, destroyed or no longer standing to a walkable exhibition-space twin that serves as its virtual reconstruction. Visitors can then walk through the reconstruction from the public gallery.') }}
    <br>
    <em>{{ __('A reconstruction is a virtual reconstruction for interpretation; it is not a claim about the original\'s exact appearance.') }}</em>
  </p>

  @if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger py-2">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card shadow-sm mb-4">
    <div class="card-header">{{ __('Link a record to a reconstruction') }}</div>
    <div class="card-body">
      <form method="POST" action="{{ route('exhibition-space.reconstructions.store') }}" class="row g-3">
        @csrf
        <div class="col-md-4">
          <label for="information_object_id" class="form-label">{{ __('Record ID (the lost place)') }}</label>
          <input type="number" min="1" name="information_object_id" id="information_object_id"
                 class="form-control" value="{{ old('information_object_id') }}" required>
          <div class="form-text">{{ __('The information-object ID of the record describing the lost place.') }}</div>
        </div>
        <div class="col-md-4">
          <label for="exhibition_space_id" class="form-label">{{ __('Reconstruction space') }}</label>
          <select name="exhibition_space_id" id="exhibition_space_id" class="form-select" required>
            <option value="">{{ __('Choose a walkable space…') }}</option>
            @foreach($spaces as $s)
              <option value="{{ $s->id }}" @selected((string) old('exhibition_space_id') === (string) $s->id)>
                {{ $s->name }}
              </option>
            @endforeach
          </select>
          <div class="form-text">{{ __('Any exhibition-space twin can stand as a reconstruction.') }}</div>
        </div>
        <div class="col-md-4">
          <label for="note" class="form-label">{{ __('Note (optional)') }}</label>
          <input type="text" name="note" id="note" class="form-control"
                 maxlength="5000" value="{{ old('note') }}"
                 placeholder="{{ __('e.g. based on 1890s survey plans and photographs') }}">
          <div class="form-text">{{ __('Sources or interpretive caveats shown in the public gallery.') }}</div>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-link me-1"></i>{{ __('Link reconstruction') }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <h2 class="h5 mb-2">{{ __('Linked reconstructions') }}</h2>
  @if(empty($reconstructions))
    <p class="text-muted">{{ __('No reconstructions have been linked yet.') }}</p>
  @else
    @foreach($reconstructions as $r)
      @php
        $stages = $stagesByRecon[$r->id] ?? [];
        $style = $styleByRecon[$r->id] ?? 'assembly';
      @endphp
      <div class="card shadow-sm mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
          <div class="flex-grow-1">
            <strong>{{ $r->record_title ?: __('Untitled record') }}</strong>
            <span class="text-muted small">#{{ $r->information_object_id }}</span>
            <span class="text-muted small">&middot; {{ $r->space_name ?: __('(missing space)') }}</span>
            @if($r->note)
              <div class="small text-muted fst-italic">{{ $r->note }}</div>
            @endif
          </div>
          <a href="{{ route('reconstruction.show', $r->id) }}"
             class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
            <i class="fas fa-play me-1"></i>{{ __('Play montage') }}
          </a>
          @if($r->space_slug)
            <a href="{{ route('exhibition-space.walkthrough', $r->space_slug) }}"
               class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
              <i class="fas fa-walking me-1"></i>{{ __('Walk') }}
            </a>
          @endif
          <form method="POST" action="{{ route('exhibition-space.reconstructions.delete', $r->id) }}"
                class="d-inline" onsubmit="return confirm('{{ __('Remove this reconstruction link? Its rebuild stages will also be removed.') }}');">
            @csrf
            <button type="submit" class="btn btn-outline-danger btn-sm">
              <i class="fas fa-unlink me-1"></i>{{ __('Remove') }}
            </button>
          </form>
        </div>
        <div class="card-body">

          {{-- Montage style (Dropdown-Manager-sourced options) --}}
          <form method="POST" action="{{ route('exhibition-space.reconstructions.style', $r->id) }}"
                class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-auto">
              <label for="montage_style_{{ $r->id }}" class="form-label small mb-1">{{ __('Default montage style') }}</label>
              <select name="montage_style" id="montage_style_{{ $r->id }}" class="form-select form-select-sm">
                @foreach($styleOptions as $opt)
                  <option value="{{ $opt['code'] }}" @selected($style === $opt['code'])>{{ $opt['label'] }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-auto">
              <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Save style') }}</button>
            </div>
          </form>

          {{-- Existing rebuild stages --}}
          <h3 class="h6">{{ __('Rebuild stages') }}</h3>
          @if(empty($stages))
            <p class="text-muted small">{{ __('No rebuild stages yet. Add evidence layers below - they assemble into the structure on the public montage.') }}</p>
          @else
            <form method="POST" action="{{ route('exhibition-space.reconstructions.stages.reorder', $r->id) }}" class="mb-2">
              @csrf
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th style="width:8rem;">{{ __('Order') }}</th>
                      <th style="width:7rem;">{{ __('Layer') }}</th>
                      <th>{{ __('Caption') }}</th>
                      <th style="width:8rem;">{{ __('Date') }}</th>
                      <th style="width:6rem;">{{ __('Opacity') }}</th>
                      <th class="text-end" style="width:7rem;">{{ __('Actions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($stages as $s)
                      <tr>
                        <td>
                          <input type="number" name="order[]" value="{{ $s->id }}" class="d-none">
                          <input type="number" form="stage-edit-{{ $s->id }}" name="sort_order"
                                 value="{{ $loop->iteration * 10 }}" min="0" class="form-control form-control-sm" style="width:5rem;">
                        </td>
                        <td>
                          @if($s->src)
                            <img src="{{ $s->src }}" alt="" style="height:42px;width:auto;background:#0f1115;border-radius:.2rem;">
                          @else
                            <span class="text-muted small">{{ __('none') }}</span>
                          @endif
                        </td>
                        <td>
                          <input type="text" form="stage-edit-{{ $s->id }}" name="caption"
                                 value="{{ $s->caption }}" class="form-control form-control-sm" maxlength="255">
                          <input type="text" form="stage-edit-{{ $s->id }}" name="body"
                                 value="{{ $s->body }}" class="form-control form-control-sm mt-1"
                                 placeholder="{{ __('Body / sources (optional)') }}">
                        </td>
                        <td>
                          <input type="text" form="stage-edit-{{ $s->id }}" name="date_display"
                                 value="{{ $s->date_display }}" class="form-control form-control-sm" maxlength="64"
                                 placeholder="{{ __('e.g. 1931') }}">
                        </td>
                        <td>
                          <input type="number" form="stage-edit-{{ $s->id }}" name="opacity"
                                 value="{{ rtrim(rtrim(number_format($s->opacity, 2, '.', ''), '0'), '.') ?: '0' }}"
                                 min="0" max="1" step="0.05" class="form-control form-control-sm">
                        </td>
                        <td class="text-end text-nowrap">
                          <button type="submit" form="stage-edit-{{ $s->id }}" class="btn btn-outline-primary btn-sm" title="{{ __('Save') }}">
                            <i class="fas fa-save"></i>
                          </button>
                          <button type="submit" form="stage-del-{{ $s->id }}" class="btn btn-outline-danger btn-sm" title="{{ __('Delete') }}"
                                  onclick="return confirm('{{ __('Delete this rebuild stage?') }}');">
                            <i class="fas fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
              <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-sort me-1"></i>{{ __('Save order') }}
              </button>
            </form>

            {{-- heratio#1206 - optional AI evidence-layer annotator, one panel per stage. --}}
            <div class="accordion accordion-flush recon-meta-accordion mb-2" id="reconMeta-{{ $r->id }}">
              @foreach($stages as $s)
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2 small" type="button"
                            data-bs-toggle="collapse" data-bs-target="#reconMetaBody-{{ $s->id }}"
                            aria-expanded="false" aria-controls="reconMetaBody-{{ $s->id }}">
                      <i class="fas fa-flask me-2"></i>
                      {{ __('Evidence layer') }}: {{ $s->caption ?: __('Stage') }} #{{ $s->id }}
                      @if(!empty($s->metadata))
                        <span class="badge bg-info-subtle text-info-emphasis ms-2">{{ __('annotated') }}</span>
                      @endif
                    </button>
                  </h2>
                  <div id="reconMetaBody-{{ $s->id }}" class="accordion-collapse collapse"
                       data-bs-parent="#reconMeta-{{ $r->id }}">
                    <div class="accordion-body py-2">
                      @include('ahg-exhibition::reconstruction._stage-metadata-form', ['r' => $r, 's' => $s, 'metadataOptions' => $metadataOptions])
                    </div>
                  </div>
                </div>
              @endforeach
            </div>

            {{-- Per-stage edit + delete forms (referenced by the table inputs above) --}}
            @foreach($stages as $s)
              <form id="stage-edit-{{ $s->id }}" method="POST"
                    action="{{ route('exhibition-space.reconstructions.stages.update', ['id' => $r->id, 'stageId' => $s->id]) }}"
                    enctype="multipart/form-data" class="d-none">
                @csrf
                @method('PUT')
                @if($s->image_url)
                  <input type="hidden" name="image_url" value="{{ $s->image_url }}">
                @endif
              </form>
              <form id="stage-del-{{ $s->id }}" method="POST"
                    action="{{ route('exhibition-space.reconstructions.stages.delete', ['id' => $r->id, 'stageId' => $s->id]) }}"
                    class="d-none">
                @csrf
                @method('DELETE')
              </form>
            @endforeach
          @endif

          {{-- Add a rebuild stage --}}
          <div class="border rounded p-2 mt-2 bg-light">
            <form method="POST" action="{{ route('exhibition-space.reconstructions.stages.add', $r->id) }}"
                  enctype="multipart/form-data" class="row g-2 align-items-end">
              @csrf
              <div class="col-md-3">
                <label class="form-label small mb-1">{{ __('Caption') }}</label>
                <input type="text" name="caption" class="form-control form-control-sm" maxlength="255">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">{{ __('Body (optional)') }}</label>
                <input type="text" name="body" class="form-control form-control-sm">
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">{{ __('Date label') }}</label>
                <input type="text" name="date_display" class="form-control form-control-sm" maxlength="64" placeholder="{{ __('e.g. 1931') }}">
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">{{ __('Opacity') }}</label>
                <input type="number" name="opacity" class="form-control form-control-sm" min="0" max="1" step="0.05" value="1">
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">{{ __('Order') }}</label>
                <input type="number" name="sort_order" class="form-control form-control-sm" min="0" placeholder="{{ __('auto') }}">
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1">{{ __('Evidence image (upload)') }}</label>
                <input type="file" name="image" class="form-control form-control-sm" accept="image/*">
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1">{{ __('or Image URL') }}</label>
                <input type="url" name="image_url" class="form-control form-control-sm" maxlength="1024"
                       placeholder="https://...">
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-sm btn-primary">
                  <i class="fas fa-plus me-1"></i>{{ __('Add rebuild stage') }}
                </button>
                <span class="form-text ms-2">{{ __('Upload an evidence image or paste an image URL. Upload wins if both are given.') }}</span>
              </div>
            </form>
          </div>

        </div>
      </div>
    @endforeach
  @endif

  {{-- heratio#1206 - "Suggest with AI" handler for the evidence-layer panels.
       Calls the annotate route (gateway-backed) and fills the form fields with the
       suggestion for the curator to review. Fail-soft: any error shows inline. --}}
  <script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    'use strict';
    var CSRF = '{{ csrf_token() }}';

    function setVal(el, sel, val) {
      var f = el.querySelector(sel);
      if (f && typeof val !== 'undefined' && val !== null) { f.value = String(val); }
    }

    document.querySelectorAll('.recon-meta-suggest').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var form = btn.closest('.recon-meta-form');
        if (!form) { return; }
        var url = form.getAttribute('data-annotate-url');
        var msg = form.querySelector('.recon-meta-msg');
        var original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>{{ __('Thinking...') }}';
        if (msg) { msg.classList.add('d-none'); msg.textContent = ''; }

        fetch(url, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json().catch(function () { return { ok: false, error: '{{ __('Unexpected response.') }}' }; }); })
        .then(function (data) {
          if (!data || !data.ok || !data.metadata) {
            if (msg) { msg.textContent = (data && data.error) ? data.error : '{{ __('No suggestion available.') }}'; msg.classList.remove('d-none'); }
            return;
          }
          var m = data.metadata;
          setVal(form, '.recon-meta-date', m.date_estimate);
          setVal(form, '.recon-meta-type', m.evidence_type);
          setVal(form, '.recon-meta-conf', m.confidence);
          setVal(form, '.recon-meta-cred', m.source_credibility);
          setVal(form, '.recon-meta-why', m.rationale);
        })
        .catch(function () {
          if (msg) { msg.textContent = '{{ __('The AI service could not be reached.') }}'; msg.classList.remove('d-none'); }
        })
        .finally(function () {
          btn.disabled = false;
          btn.innerHTML = original;
        });
      });
    });
  })();
  </script>
@endsection
