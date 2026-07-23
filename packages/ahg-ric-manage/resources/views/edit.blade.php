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

  <div class="accordion mb-3" id="ric-accordion">

    {{-- Identity: RiC Record identity (rico:identifier / rico:title / rico:hasRecordSetType) --}}
    <div class="accordion-item">
      <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#ric-identity">{{ __('Identity') }}</button></h2>
      <div id="ric-identity" class="accordion-collapse collapse show">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Identifier') }} <span class="text-muted small">(rico:identifier)</span></label>
            <input type="text" name="identifier" class="form-control" value="{{ old('identifier', $io->identifier ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span> <span class="text-muted small">(rico:title)</span></label>
            <input type="text" name="title" class="form-control" required value="{{ old('title', $io->title ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Level / RecordSet type') }} <span class="text-muted small">(rico:hasRecordSetType)</span></label>
            <select name="level_of_description_id" class="form-select">
              <option value="">-</option>
              @foreach($levels as $lvl)
                <option value="{{ $lvl->id }}" @selected(old('level_of_description_id', $io->level_of_description_id ?? '') == $lvl->id)>{{ $lvl->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Repository / holder') }} <span class="text-muted small">(rico:hasOrHadHolder)</span></label>
            <select name="repository_id" class="form-select">
              <option value="">-</option>
              @foreach($repositories as $repo)
                <option value="{{ $repo->id }}" @selected(old('repository_id', $io->repository_id ?? '') == $repo->id)>{{ $repo->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>
    </div>

    {{-- Content & structure (rico:description / rico:history / rico:hasExtent) --}}
    <div class="accordion-item">
      <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ric-content">{{ __('Content and structure') }}</button></h2>
      <div id="ric-content" class="accordion-collapse collapse">
        <div class="accordion-body">
          @foreach([
            ['scope_and_content', 'Scope and content', 'rico:description'],
            ['arrangement', 'Arrangement', 'rico:structure'],
            ['extent_and_medium', 'Extent and medium', 'rico:hasExtent'],
            ['archival_history', 'Archival / custodial history', 'rico:history'],
            ['acquisition', 'Immediate source of acquisition', 'rico:hasSourceOfAcquisition'],
            ['appraisal', 'Appraisal, destruction and scheduling', 'rico:descriptiveNote'],
            ['accruals', 'Accruals', 'rico:descriptiveNote'],
          ] as [$field, $label, $ric])
            <div class="mb-3">
              <label class="form-label">{{ __($label) }} <span class="text-muted small">({{ $ric }})</span></label>
              <textarea name="{{ $field }}" class="form-control" rows="3">{{ old($field, $io->$field ?? '') }}</textarea>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Conditions of access and use --}}
    <div class="accordion-item">
      <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ric-conditions">{{ __('Conditions of access and use') }}</button></h2>
      <div id="ric-conditions" class="accordion-collapse collapse">
        <div class="accordion-body">
          @foreach([
            ['access_conditions', 'Conditions governing access', 'rico:conditionsOfAccess'],
            ['reproduction_conditions', 'Conditions governing reproduction', 'rico:conditionsOfUse'],
            ['physical_characteristics', 'Physical characteristics / technical requirements', 'rico:physicalCharacteristics'],
            ['finding_aids', 'Finding aids', 'rico:hasInstantiation'],
          ] as [$field, $label, $ric])
            <div class="mb-3">
              <label class="form-label">{{ __($label) }} <span class="text-muted small">({{ $ric }})</span></label>
              <textarea name="{{ $field }}" class="form-control" rows="2">{{ old($field, $io->$field ?? '') }}</textarea>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Related material & allied materials --}}
    <div class="accordion-item">
      <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ric-related">{{ __('Related materials') }}</button></h2>
      <div id="ric-related" class="accordion-collapse collapse">
        <div class="accordion-body">
          @foreach([
            ['location_of_originals', 'Existence and location of originals', 'rico:hasInstantiation'],
            ['location_of_copies', 'Existence and location of copies', 'rico:hasCopy'],
            ['related_units_of_description', 'Related units of description', 'rico:isRelatedTo'],
          ] as [$field, $label, $ric])
            <div class="mb-3">
              <label class="form-label">{{ __($label) }} <span class="text-muted small">({{ $ric }})</span></label>
              <textarea name="{{ $field }}" class="form-control" rows="2">{{ old($field, $io->$field ?? '') }}</textarea>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Access points (existing, read-only summary in A1; full widgets follow) --}}
    <div class="accordion-item">
      <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ric-access">{{ __('Access points') }}</button></h2>
      <div id="ric-access" class="accordion-collapse collapse">
        <div class="accordion-body">
          @foreach([['Subjects', $subjects], ['Places', $places], ['Genres', $genres]] as [$label, $coll])
            <div class="mb-2">
              <strong>{{ __($label) }}:</strong>
              @forelse($coll as $t)<span class="badge bg-secondary">{{ $t->name }}</span>@empty<span class="text-muted small">{{ __('none') }}</span>@endforelse
            </div>
          @endforeach
          <div class="mb-2">
            <strong>{{ __('Name access points') }}:</strong>
            @forelse($nameAccessPoints as $n)<span class="badge bg-info text-dark">{{ $n->name }}</span>@empty<span class="text-muted small">{{ __('none') }}</span>@endforelse
          </div>
        </div>
      </div>
    </div>

    {{-- Description control --}}
    <div class="accordion-item">
      <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ric-control">{{ __('Description control') }}</button></h2>
      <div id="ric-control" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Description identifier') }}</label>
            <input type="text" name="description_identifier" class="form-control" value="{{ old('description_identifier', $io->description_identifier ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Rules or conventions') }}</label>
            <textarea name="rules" class="form-control" rows="2">{{ old('rules', $io->rules ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Sources') }}</label>
            <textarea name="sources" class="form-control" rows="2">{{ old('sources', $io->sources ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Display standard') }}</label>
            <select name="display_standard_id" class="form-select">
              <option value="">- {{ __('Use global default') }} -</option>
              @foreach($displayStandards as $std)
                <option value="{{ $std->id }}" @selected(old('display_standard_id', $io->display_standard_id ?? '') == $std->id)>{{ $std->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Publication status') }}</label>
            <select name="publication_status_id" class="form-select">
              <option value="159" @selected($publicationStatusId == 159)>{{ __('Draft') }}</option>
              <option value="160" @selected($publicationStatusId == 160)>{{ __('Published') }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

  </div>

  <div class="d-flex gap-2 mb-4">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save (RiC-O)') }}</button>
    <a href="{{ url('/'.($io->slug ?? '')) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
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
