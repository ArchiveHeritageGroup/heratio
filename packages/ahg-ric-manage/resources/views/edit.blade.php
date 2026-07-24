{{--
  RiC-O (Records in Contexts) description editor - #1425.
  (c) 2026 Johan Pieterse / Plain Sailing iSystems. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', 'Edit description (RiC-O)')

@section('content')
@php
  $io = $io ?? (object) [];
  $levels = $levels ?? collect();
  $repositories = $repositories ?? collect();
  $descriptionStatuses = $descriptionStatuses ?? collect();
  $descriptionDetails = $descriptionDetails ?? collect();
  $displayStandards = $displayStandards ?? collect();
  $creators = $creators ?? collect();
  $subjects = $subjects ?? collect();
  $places = $places ?? collect();
  $genres = $genres ?? collect();
  $nameAccessPoints = $nameAccessPoints ?? collect();
  $publicationStatusId = $publicationStatusId ?? null;
  $parentTitle = $parentTitle ?? null;
  $parentSlug = $parentSlug ?? null;
  $ricJsonLd = $ricJsonLd ?? null;
  $ricValidation = $ricValidation ?? null;
@endphp

{{-- RiC-O conformance (SHACL + mandatory-field + referential checks, #1425 A3).
     Non-blocking: shown for guidance; the record still saves with violations. --}}
@if($ricValidation)
  @php
    $ricErrors = $ricValidation['errors'] ?? [];
    $ricWarnings = $ricValidation['warnings'] ?? [];
    $ricConforms = ($ricValidation['valid'] ?? false) && empty($ricErrors);
  @endphp
  <div class="alert {{ $ricConforms ? 'alert-success' : (count($ricErrors) ? 'alert-danger' : 'alert-warning') }} d-flex flex-column gap-1" role="status">
    <div>
      @if($ricConforms)
        <i class="fas fa-check-circle me-1"></i><strong>{{ __('Conforms to RiC-O') }}</strong>
        <span class="small">{{ __('SHACL shapes and mandatory RiC-O elements satisfied.') }}</span>
      @else
        <i class="fas fa-triangle-exclamation me-1"></i><strong>{{ __('RiC-O conformance') }}:</strong>
        <span class="small">{{ trans_choice('{1}:count issue|[2,*]:count issues', count($ricErrors) + count($ricWarnings), ['count' => count($ricErrors) + count($ricWarnings)]) }}</span>
      @endif
    </div>
    @if(count($ricErrors))
      <ul class="mb-0 ps-3 small">@foreach($ricErrors as $e)<li>{{ is_array($e) ? ($e['message'] ?? json_encode($e)) : $e }}</li>@endforeach</ul>
    @endif
    @if(count($ricWarnings))
      <details class="small"><summary>{{ trans_choice('{1}:count advisory warning|[2,*]:count advisory warnings', count($ricWarnings), ['count' => count($ricWarnings)]) }}</summary>
        <ul class="mb-0 ps-3">@foreach($ricWarnings as $w)<li>{{ is_array($w) ? ($w['message'] ?? json_encode($w)) : $w }}</li>@endforeach</ul>
      </details>
    @endif
  </div>
@endif

<h1>{{ __('Edit description') }}
  <small class="text-muted">(RiC-O 1.0 — Records in Contexts)</small>
</h1>

@if($parentTitle)
  <p class="text-muted">{{ __('Parent') }}:
    <a href="{{ url('/'.$parentSlug) }}">{{ $parentTitle }}</a>
  </p>
@endif

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(isset($errors) && $errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul></div>
@endif

<form method="post" action="{{ route('ahgricmanage.edit', ['slug' => $io->slug ?? '']) }}" autocomplete="off">
  @csrf

  @include('ric-manage::_fields', ['io' => $io, 'levels' => $levels ?? collect(), 'repositories' => $repositories ?? collect(), 'subjects' => $subjects ?? collect(), 'places' => $places ?? collect(), 'genres' => $genres ?? collect(), 'nameAccessPoints' => $nameAccessPoints ?? collect(), 'publicationStatusId' => $publicationStatusId ?? null, 'existingInstantiations' => $existingInstantiations ?? collect(), 'existingEvents' => $existingEvents ?? collect(), 'eventTypes' => $eventTypes ?? collect()])

  {{-- Display standard (standalone editor keeps its own selector; on the
       dynamic create/edit form the host owns this and the partial omits it) --}}
  <div class="mb-3">
    <label class="form-label">{{ __('Display standard') }}</label>
    <select name="display_standard_id" class="form-select">
      <option value="">- {{ __('Use global default') }} -</option>
      @foreach(($displayStandards ?? collect()) as $std)
        <option value="{{ $std->id }}" @selected(old('display_standard_id', $io->display_standard_id ?? '') == $std->id)>{{ $std->name }}</option>
      @endforeach
    </select>
  </div>

  <div class="d-flex gap-2 mb-4">
    <a href="{{ url('/'.($io->slug ?? '')) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save (RiC-O)') }}</button>
  </div>
</form>

{{-- RiC-O JSON-LD preview, straight from the ahg/ric engine. Omitted when the
     engine is absent (standalone install without it booted). --}}
@if($ricJsonLd)
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between" style="background:var(--ahg-primary,#10373E);color:#fff;">
      <span><i class="fas fa-project-diagram me-2"></i>{{ __('RiC-O JSON-LD') }}</span>
      <span class="badge bg-light text-dark">{{ $ricJsonLd['rico:type'] ?? 'Record' }}</span>
    </div>
    <div class="card-body p-0">
      <pre class="mb-0 p-3" style="max-height:420px;overflow:auto;font-size:.8rem;">{{ json_encode($ricJsonLd, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
  </div>
@endif
@endsection

@push('js')
<script nonce="{{ csp_nonce() }}">
{{-- #1425 tail: generic repeatable-group delegation (RiC instantiation editor). --}}
if (!window.__ahgRepeatBound) {
  window.__ahgRepeatBound = true;
  document.addEventListener('click', function(e) {
    var add = e.target.closest('[data-repeat-add]');
    if (add) {
      var tpl = document.getElementById(add.getAttribute('data-repeat-add'));
      var target = document.getElementById(add.getAttribute('data-repeat-target'));
      if (!tpl || !target) return;
      var idx = parseInt(add.getAttribute('data-repeat-index') || '0', 10);
      var wrap = document.createElement('div');
      wrap.innerHTML = tpl.innerHTML.replace(/__IDX__/g, idx).trim();
      while (wrap.firstChild) target.appendChild(wrap.firstChild);
      add.setAttribute('data-repeat-index', idx + 1);
      return;
    }
    var rm = e.target.closest('[data-repeat-remove]');
    if (rm) { var row = rm.closest('[data-repeat-row]'); if (row) row.remove(); }
  });
}
</script>
@endpush
