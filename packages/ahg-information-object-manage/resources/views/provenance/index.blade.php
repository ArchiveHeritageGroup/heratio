@extends('theme::layouts.1col')

@section('title', 'Provenance History — ' . ($io->title ?? ''))

@section('content')
@php $ov = $overview ?? null; @endphp
<div class="container py-3">

  {{-- Breadcrumb --}}
  <nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="{{ route('informationobject.show', $io->slug) }}">{{ $io->title ?? $io->slug }}</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ __('Provenance') }}</li>
    </ol>
  </nav>

  {{-- Header --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h1 class="h4 mb-1"><i class="fas fa-clock-rotate-left me-2"></i>{{ __('Provenance History') }}</h1>
      <p class="text-muted mb-0"><strong>{{ $io->identifier ?? '' }}</strong> {{ $io->title ?? '' }}</p>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('informationobject.show', $io->slug) }}" class="btn btn-outline-primary">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Record') }}
      </a>
      @auth
        <a href="{{ route('io.provenance.exportCsv', $io->slug) }}" class="btn btn-outline-secondary">
          <i class="fas fa-download me-1"></i>{{ __('Export CSV') }}
        </a>
      @endauth
    </div>
  </div>

  {{-- Overview at-a-glance (read badges) --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <span><i class="fas fa-shield-halved me-1"></i>{{ __('Provenance Overview') }}</span>
    </div>
    <div class="card-body">
      @if($ov)
        @if($ov->provenance_summary)<p class="mb-2">{{ $ov->provenance_summary }}</p>@endif
        <div class="d-flex flex-wrap gap-1">
          @if($ov->current_status)<span class="badge bg-info">{{ ucfirst(str_replace('_',' ',$ov->current_status)) }}</span>@endif
          @if($ov->acquisition_type)<span class="badge bg-secondary">{{ ucfirst(str_replace('_',' ',$ov->acquisition_type)) }}</span>@endif
          @if($ov->research_status)<span class="badge bg-light text-dark">{{ __('Research') }}: {{ ucfirst(str_replace('_',' ',$ov->research_status)) }}</span>@endif
          @if($ov->is_complete)<span class="badge bg-success">{{ __('Complete') }}</span>@endif
          @if(!$ov->is_public)<span class="badge bg-warning text-dark"><i class="fas fa-eye-slash me-1"></i>{{ __('Not public') }}</span>@endif
          @if($ov->nazi_era_provenance_checked)
            <span class="badge {{ $ov->nazi_era_provenance_clear ? 'bg-success' : 'bg-danger' }}">
              <i class="fas fa-gavel me-1"></i>{{ __('Nazi-era') }}: {{ $ov->nazi_era_provenance_clear ? __('clear') : __('flagged') }}
            </span>
          @endif
          @if($ov->cultural_property_status && $ov->cultural_property_status !== 'none')
            <span class="badge bg-danger"><i class="fas fa-landmark me-1"></i>{{ __('Cultural property') }}: {{ ucfirst(str_replace('_',' ',$ov->cultural_property_status)) }}</span>
          @endif
          @if($ov->has_gaps)<span class="badge bg-warning text-dark"><i class="fas fa-triangle-exclamation me-1"></i>{{ __('Has gaps') }}</span>@endif
        </div>
      @else
        <p class="text-muted small mb-0">{{ __('No provenance overview recorded yet.') }}</p>
      @endif
    </div>
  </div>

  {{-- Timeline Container (D3 populates it when data exists) --}}
  <div id="provenance-timeline"></div>

  {{-- Visual Chain Diagram --}}
  @if($events->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-link me-1"></i> {{ __('Chain of Custody') }}
      </div>
      <div class="card-body">
        <div class="d-flex flex-wrap align-items-start justify-content-center gap-0">
          @foreach($events as $i => $entry)
            @if($i > 0)
              {{-- Transfer arrow --}}
              <div class="d-flex flex-column align-items-center mx-1" style="min-width:50px;">
                <div class="text-center mb-1">
                  @php
                    $transferIcons = [
                      'sale' => 'fas fa-coins', 'auction' => 'fas fa-gavel', 'gift' => 'fas fa-gift',
                      'bequest' => 'fas fa-scroll', 'inheritance' => 'fas fa-scroll',
                      'commission' => 'fas fa-clipboard-list', 'exchange' => 'fas fa-exchange-alt',
                      'transfer' => 'fas fa-arrow-right', 'found' => 'fas fa-search',
                      'restitution' => 'fas fa-balance-scale', 'repatriation' => 'fas fa-home',
                      'seizure' => 'fas fa-hand-paper', 'created' => 'fas fa-paint-brush',
                    ];
                  @endphp
                  <i class="{{ $transferIcons[$entry->transfer_type] ?? 'fas fa-arrow-right' }}" style="font-size:1.2rem;color:var(--ahg-primary,#2c6b4f);"></i>
                </div>
                <small class="text-muted text-center" style="font-size:0.65rem;line-height:1.1;">{{ ucfirst(str_replace('_', ' ', $entry->transfer_type ?? '')) }}</small>
              </div>
            @endif
            {{-- Owner node --}}
            <div class="text-center" style="min-width:100px;max-width:130px;">
              @php
                $typeIcons = [
                  'person' => 'fas fa-user', 'family' => 'fas fa-users',
                  'dealer' => 'fas fa-store', 'auction_house' => 'fas fa-gavel',
                  'museum' => 'fas fa-landmark', 'corporate' => 'fas fa-building',
                  'government' => 'fas fa-university', 'religious' => 'fas fa-church',
                  'artist' => 'fas fa-palette',
                ];
                $bgColors = [
                  'person' => '#dc3545', 'family' => '#dc3545', 'dealer' => '#fd7e14',
                  'auction_house' => '#fd7e14', 'museum' => '#0d6efd', 'corporate' => '#6c757d',
                  'government' => '#198754', 'religious' => '#6f42c1', 'artist' => '#d63384',
                ];
                $iconClass = $typeIcons[$entry->owner_type] ?? 'fas fa-user-circle';
                $bg = $bgColors[$entry->owner_type] ?? '#6c757d';
              @endphp
              @php
                $dateLabel = '';
                if ($entry->start_date && $entry->end_date) {
                    $dateLabel = $entry->start_date . ' - ' . $entry->end_date;
                } elseif ($entry->start_date) {
                    $dateLabel = $entry->start_date . ' - present';
                } elseif ($entry->end_date) {
                    $dateLabel = 'until ' . $entry->end_date;
                }
              @endphp
              @if($dateLabel)
                <small class="text-muted d-block mb-1" style="font-size:0.65rem;line-height:1.1;">{{ $dateLabel }}</small>
              @endif
              <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                   style="width:50px;height:50px;background:{{ $bg }};color:#fff;font-size:1.3rem;">
                <i class="{{ $iconClass }}"></i>
              </div>
              <strong class="small d-block" style="font-size:0.75rem;line-height:1.2;" title="{{ $entry->owner_name }}">
                {{ \Illuminate\Support\Str::limit($entry->owner_name, 20) }}
              </strong>
              <small class="text-muted d-block" style="font-size:0.65rem;">{{ ucfirst(str_replace('_', ' ', $entry->owner_type ?? 'unknown')) }}</small>
              @if($entry->owner_location)
                <small class="text-muted d-block" style="font-size:0.65rem;">{{ \Illuminate\Support\Str::limit($entry->owner_location, 15) }}</small>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- Ownership History Table --}}
  <div class="provenance-table-section mb-4">
    <h2 class="h5">{{ __('Ownership History') }}</h2>

    @if($events->isNotEmpty())
      <table class="table table-striped provenance-table">
        <thead>
          <tr>
            <th width="5%">#</th>
            <th width="25%">{{ __('Owner') }}</th>
            <th width="15%">{{ __('Location') }}</th>
            <th width="15%">{{ __('Period') }}</th>
            <th width="15%">{{ __('Transfer') }}</th>
            <th width="10%">{{ __('Certainty') }}</th>
            @auth
              <th width="15%">{{ __('Actions') }}</th>
            @endauth
          </tr>
        </thead>
        <tbody>
          @foreach($events as $entry)
            <tr class="{{ $entry->is_gap ? 'table-warning' : '' }}">
              <td>{{ $entry->sequence }}</td>
              <td>
                <strong>{{ $entry->owner_name }}</strong>
                @if($entry->owner_type && $entry->owner_type !== 'unknown')
                  <br><small class="text-muted">{{ ucfirst(str_replace('_', ' ', $entry->owner_type)) }}</small>
                @endif
              </td>
              <td>
                @if($entry->owner_location)
                  {{ $entry->owner_location }}
                  @if($entry->owner_location_tgn)
                    <br><a href="{{ $entry->owner_location_tgn }}" target="_blank" class="small">
                      <i class="fas fa-external-link-alt"></i> TGN
                    </a>
                  @endif
                @endif
              </td>
              <td>
                @if($entry->start_date && $entry->end_date)
                  {{ $entry->start_date }} - {{ $entry->end_date }}
                @elseif($entry->start_date)
                  {{ $entry->start_date }} - present
                @elseif($entry->end_date)
                  until {{ $entry->end_date }}
                @else
                  Unknown
                @endif
              </td>
              <td>{{ ucfirst(str_replace('_', ' ', $entry->transfer_type ?? '')) }}</td>
              <td>
                <span class="badge bg-secondary">{{ ucfirst($entry->certainty ?? 'unknown') }}</span>
              </td>
              @auth
                <td>
                  <button class="btn btn-sm btn-outline-primary edit-entry" data-id="{{ $entry->id }}"
                    data-owner_name="{{ $entry->owner_name }}"
                    data-owner_type="{{ $entry->owner_type }}"
                    data-owner_location="{{ $entry->owner_location }}"
                    data-owner_location_tgn="{{ $entry->owner_location_tgn ?? '' }}"
                    data-start_date="{{ $entry->start_date }}"
                    data-end_date="{{ $entry->end_date }}"
                    data-transfer_type="{{ $entry->transfer_type }}"
                    data-certainty="{{ $entry->certainty }}"
                    data-sale_price="{{ $entry->sale_price ?? '' }}"
                    data-sale_currency="{{ $entry->sale_currency ?? '' }}"
                    data-auction_house="{{ $entry->auction_house ?? '' }}"
                    data-auction_lot="{{ $entry->auction_lot ?? '' }}"
                    data-sources="{{ $entry->sources ?? '' }}"
                    data-notes="{{ $entry->notes ?? '' }}"
                    data-is_gap="{{ $entry->is_gap ? '1' : '0' }}">
                    <i class="fas fa-edit"></i>
                  </button>
                  <form method="POST" action="{{ route('io.provenance.delete', $entry->id) }}" class="d-inline" onsubmit="return confirm('Delete this entry?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                  </form>
                </td>
              @endauth
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="alert alert-info">{{ __('No provenance information recorded for this object.') }}</div>
    @endif

    @auth
      <div class="provenance-actions">
        <button class="btn btn-primary" id="add-entry">
          <i class="fas fa-plus me-1"></i>{{ __('Add Custody Entry') }}
        </button>
      </div>
    @endauth
  </div>

  {{-- ===================== AUTH: two-column editor ===================== --}}
  @auth
  <hr class="my-4">
  <h2 class="h5 mb-3"><i class="fas fa-pen-to-square me-2"></i>{{ __('Edit Provenance Details') }}</h2>

  <form method="POST" action="{{ route('io.provenance.overview', $io->slug) }}">
    @csrf
    <div class="row">
      {{-- Main column --}}
      <div class="col-lg-8">

        {{-- Provenance Summary --}}
        <div class="card mb-4">
          <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="fas fa-file-lines me-2"></i>{{ __('Provenance Summary') }}</h6>
          </div>
          <div class="card-body">
            <label class="form-label">{{ __('Provenance Statement') }}</label>
            <textarea name="provenance_summary" rows="4" class="form-control" placeholder="{{ __('A human-readable summary of the object\'s provenance…') }}">{{ old('provenance_summary', $ov->provenance_summary ?? '') }}</textarea>
            <small class="text-muted">{{ __('Shown publicly when the record is public.') }}</small>
          </div>
        </div>

        {{-- Acquisition Details --}}
        <div class="card mb-4">
          <div class="card-header"><h6 class="mb-0"><i class="fas fa-cart-shopping me-2"></i>{{ __('Acquisition Details') }}</h6></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">{{ __('Acquisition Type') }}</label>
                <select name="acquisition_type" class="form-select">
                  @foreach($acquisitionTypes as $k => $label)
                    <option value="{{ $k }}" @selected(old('acquisition_type', $ov->acquisition_type ?? '') === $k)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ __('Acquisition Date') }}</label>
                <input type="date" name="acquisition_date" class="form-control" value="{{ old('acquisition_date', isset($ov->acquisition_date) ? \Illuminate\Support\Str::substr((string) $ov->acquisition_date, 0, 10) : '') }}">
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ __('Date (text)') }}</label>
                <input type="text" name="acquisition_date_text" class="form-control" placeholder="{{ __('e.g. circa 1950') }}" value="{{ old('acquisition_date_text', $ov->acquisition_date_text ?? '') }}">
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ __('Price') }}</label>
                <input type="number" step="0.01" name="acquisition_price" class="form-control" value="{{ old('acquisition_price', $ov->acquisition_price ?? '') }}">
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ __('Currency') }}</label>
                <select name="acquisition_currency" class="form-select">
                  @foreach($currencies as $k => $label)
                    <option value="{{ $k }}" @selected(old('acquisition_currency', $ov->acquisition_currency ?? '') === $k)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
        </div>

        {{-- Research & Gaps --}}
        <div class="card mb-4">
          <div class="card-header"><h6 class="mb-0"><i class="fas fa-book me-2"></i>{{ __('Research & Gaps') }}</h6></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">{{ __('Research Status') }}</label>
              <select name="research_status" class="form-select">
                <option value="">—</option>
                @foreach($researchStatuses as $k => $label)
                  <option value="{{ $k }}" @selected(old('research_status', $ov->research_status ?? '') === $k)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Research Notes') }}</label>
              <textarea name="research_notes" rows="3" class="form-control" placeholder="{{ __('Findings, sources consulted, open questions…') }}">{{ old('research_notes', $ov->research_notes ?? '') }}</textarea>
            </div>
            <div class="form-check mb-2">
              <input type="checkbox" class="form-check-input" id="has_gaps" name="has_gaps" value="1" @checked(old('has_gaps', $ov->has_gaps ?? 0))>
              <label class="form-check-label" for="has_gaps">{{ __('There are gaps in the provenance chain') }}</label>
            </div>
            <div id="gapDescriptionGroup" class="mb-0" style="{{ old('has_gaps', $ov->has_gaps ?? 0) ? '' : 'display:none' }}">
              <label class="form-label">{{ __('Gap Description') }}</label>
              <textarea name="gap_description" rows="2" class="form-control" placeholder="{{ __('Describe the gaps…') }}">{{ old('gap_description', $ov->gap_description ?? '') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      {{-- Sidebar --}}
      <div class="col-lg-4">

        {{-- Status --}}
        <div class="card mb-4">
          <div class="card-header"><h6 class="mb-0"><i class="fas fa-sliders me-2"></i>{{ __('Status') }}</h6></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">{{ __('Current Status') }}</label>
              <select name="current_status" class="form-select">
                <option value="">—</option>
                @foreach($currentStatuses as $k => $label)
                  <option value="{{ $k }}" @selected(old('current_status', $ov->current_status ?? '') === $k)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Custody Type') }}</label>
              <select name="custody_type" class="form-select">
                <option value="">—</option>
                @foreach($custodyTypes as $k => $label)
                  <option value="{{ $k }}" @selected(old('custody_type', $ov->custody_type ?? '') === $k)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Certainty Level') }}</label>
              <select name="certainty_level" class="form-select">
                <option value="">—</option>
                @foreach($certaintyLevels as $k => $label)
                  <option value="{{ $k }}" @selected(old('certainty_level', $ov->certainty_level ?? '') === $k)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-check mb-2">
              <input type="checkbox" class="form-check-input" id="is_complete" name="is_complete" value="1" @checked(old('is_complete', $ov->is_complete ?? 0))>
              <label class="form-check-label" for="is_complete">{{ __('Research is complete') }}</label>
            </div>
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="is_public" name="is_public" value="1" @checked(old('is_public', $ov->is_public ?? 1))>
              <label class="form-check-label" for="is_public">{{ __('Display provenance publicly') }}</label>
            </div>
          </div>
        </div>

        {{-- Nazi-era due diligence --}}
        <div class="card mb-4">
          <div class="card-header bg-warning"><h6 class="mb-0"><i class="fas fa-gavel me-2"></i>{{ __('Nazi-Era Provenance') }}</h6></div>
          <div class="card-body">
            <div class="form-check mb-3">
              <input type="checkbox" class="form-check-input" id="nazi_checked" name="nazi_era_provenance_checked" value="1" @checked(old('nazi_era_provenance_checked', $ov->nazi_era_provenance_checked ?? 0))>
              <label class="form-check-label" for="nazi_checked">{{ __('Nazi-era provenance has been checked') }}</label>
            </div>
            <div id="naziEraClearGroup" style="{{ old('nazi_era_provenance_checked', $ov->nazi_era_provenance_checked ?? 0) ? '' : 'display:none' }}">
              <div class="mb-3">
                <label class="form-label">{{ __('Result') }}</label>
                <select name="nazi_era_provenance_clear" class="form-select">
                  <option value="">{{ __('— Select —') }}</option>
                  <option value="1" @selected((string) old('nazi_era_provenance_clear', $ov->nazi_era_provenance_clear ?? '') === '1')>{{ __('Clear — no issues found') }}</option>
                  <option value="0" @selected((string) old('nazi_era_provenance_clear', $ov->nazi_era_provenance_clear ?? '') === '0')>{{ __('Requires investigation') }}</option>
                </select>
              </div>
              <div class="mb-0">
                <label class="form-label">{{ __('Notes') }}</label>
                <textarea name="nazi_era_notes" rows="2" class="form-control">{{ old('nazi_era_notes', $ov->nazi_era_notes ?? '') }}</textarea>
              </div>
            </div>
          </div>
        </div>

        {{-- Cultural property --}}
        <div class="card mb-4">
          <div class="card-header"><h6 class="mb-0"><i class="fas fa-globe me-2"></i>{{ __('Cultural Property') }}</h6></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">{{ __('Status') }}</label>
              <select name="cultural_property_status" class="form-select">
                @foreach($culturalPropertyStatuses as $k => $label)
                  <option value="{{ $k }}" @selected(old('cultural_property_status', $ov->cultural_property_status ?? 'none') === $k)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-0">
              <label class="form-label">{{ __('Notes') }}</label>
              <textarea name="cultural_property_notes" rows="2" class="form-control">{{ old('cultural_property_notes', $ov->cultural_property_notes ?? '') }}</textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mb-4">
      <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>{{ __('Save Provenance Details') }}</button>
    </div>
  </form>

  {{-- ===================== Supporting Documents ===================== --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h6 class="mb-0"><i class="fas fa-file-earmark me-2"></i>{{ __('Supporting Documents') }}</h6>
    </div>
    <div class="card-body">
      {{-- Existing documents --}}
      @if($documents->isNotEmpty())
        <div class="mb-3">
          @foreach($documents as $doc)
            <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2">
              <div class="me-2">
                <i class="fas fa-file me-2"></i>
                <strong>{{ $doc->title ?: $doc->original_filename ?: __('Untitled') }}</strong>
                <span class="badge bg-secondary ms-2">{{ $documentTypes[$doc->document_type] ?? ucfirst(str_replace('_',' ',$doc->document_type)) }}</span>
                @unless($doc->is_public)<span class="badge bg-warning text-dark ms-1"><i class="fas fa-eye-slash"></i></span>@endunless
                @if($doc->document_date || $doc->document_date_text)
                  <small class="text-muted ms-2">{{ $doc->document_date_text ?: \Illuminate\Support\Str::substr((string) $doc->document_date, 0, 10) }}</small>
                @endif
                @if($doc->description)<div class="small text-muted mt-1">{{ $doc->description }}</div>@endif
              </div>
              <div class="text-nowrap">
                @if($doc->file_path)
                  <a href="{{ route('io.provenance.document.download', $doc->id) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i></a>
                @elseif($doc->external_url)
                  <a href="{{ $doc->external_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt"></i></a>
                @endif
                <form method="POST" action="{{ route('io.provenance.document.delete', $doc->id) }}" class="d-inline" onsubmit="return confirm('Delete this document?')">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </div>
          @endforeach
        </div>
      @else
        <p class="text-muted small">{{ __('No supporting documents attached yet.') }}</p>
      @endif

      {{-- Add document --}}
      <form method="POST" action="{{ route('io.provenance.document.store', $io->slug) }}" enctype="multipart/form-data" class="border-top pt-3">
        @csrf
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label small">{{ __('Document Type') }}</label>
            <select name="document_type" class="form-select form-select-sm">
              @foreach($documentTypes as $k => $label)
                <option value="{{ $k }}" @selected($k === 'other')>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label small">{{ __('Title') }}</label>
            <input type="text" name="title" class="form-control form-control-sm" placeholder="{{ __('Document title…') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label small">{{ __('Date') }}</label>
            <input type="date" name="document_date" class="form-control form-control-sm">
          </div>
          <div class="col-md-6">
            <label class="form-label small">{{ __('File Upload') }}</label>
            <input type="file" name="file" class="form-control form-control-sm">
          </div>
          <div class="col-md-6">
            <label class="form-label small">{{ __('Or External URL') }}</label>
            <input type="url" name="external_url" class="form-control form-control-sm" placeholder="https://…">
          </div>
          <div class="col-md-8">
            <label class="form-label small">{{ __('Description') }}</label>
            <input type="text" name="description" class="form-control form-control-sm" placeholder="{{ __('Brief description…') }}">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="doc_is_public" name="is_public" value="1">
              <label class="form-check-label small" for="doc_is_public">{{ __('Public') }}</label>
            </div>
          </div>
        </div>
        <div class="mt-2">
          <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>{{ __('Add Document') }}</button>
          <small class="text-muted ms-2">{{ __('Provide a file, an external URL, or an archive reference.') }}</small>
        </div>
      </form>
    </div>
  </div>
  @endauth

  {{-- Add/Edit Custody Entry Modal --}}
  @auth
  <div class="modal fade" id="entry-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ __('Provenance Entry') }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="entry-form" method="POST" action="{{ route('io.provenance.store', $io->slug) }}">
            @csrf
            <input type="hidden" name="entry_id" id="entry-id">

            <div class="row mb-3">
              <div class="col-md-8">
                <label for="owner_name" class="form-label">{{ __('Owner Name *') }}</label>
                <input type="text" class="form-control" name="owner_name" id="owner_name" required>
              </div>
              <div class="col-md-4">
                <label for="owner_type" class="form-label">{{ __('Owner Type') }}</label>
                <select class="form-select" name="owner_type" id="owner_type">
                  @foreach($ownerTypes as $k => $label)
                    <option value="{{ $k }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-8">
                <label for="owner_location" class="form-label">{{ __('Location') }}</label>
                <input type="text" class="form-control" name="owner_location" id="owner_location" placeholder="{{ __('City, Country') }}">
              </div>
              <div class="col-md-4">
                <label for="certainty" class="form-label">{{ __('Certainty') }}</label>
                <select class="form-select" name="certainty" id="certainty">
                  @foreach($certaintyLevels as $k => $label)
                    <option value="{{ $k }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-3">
                <label for="start_date" class="form-label">{{ __('Start Date') }}</label>
                <input type="text" class="form-control" name="start_date" id="start_date" placeholder="{{ __('YYYY or text') }}">
              </div>
              <div class="col-md-3">
                <label for="end_date" class="form-label">{{ __('End Date') }}</label>
                <input type="text" class="form-control" name="end_date" id="end_date" placeholder="{{ __('YYYY or text') }}">
              </div>
              <div class="col-md-6">
                <label for="transfer_type" class="form-label">{{ __('Transfer Method') }}</label>
                <select class="form-select" name="transfer_type" id="transfer_type">
                  @foreach($transferTypes as $k => $label)
                    <option value="{{ $k }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-4">
                <label for="sale_price" class="form-label">{{ __('Sale Price') }}</label>
                <input type="number" class="form-control" name="sale_price" id="sale_price" step="0.01">
              </div>
              <div class="col-md-2">
                <label for="sale_currency" class="form-label">{{ __('Currency') }}</label>
                <select class="form-select" name="sale_currency" id="sale_currency">
                  @foreach($currencies as $k => $label)
                    <option value="{{ $k }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4">
                <label for="auction_house" class="form-label">{{ __('Auction House') }}</label>
                <input type="text" class="form-control" name="auction_house" id="auction_house">
              </div>
              <div class="col-md-2">
                <label for="auction_lot" class="form-label">{{ __('Lot #') }}</label>
                <input type="text" class="form-control" name="auction_lot" id="auction_lot">
              </div>
            </div>

            <div class="mb-3">
              <label for="sources" class="form-label">{{ __('Sources/Documentation') }}</label>
              <textarea class="form-control" name="sources" id="sources" rows="2"></textarea>
            </div>

            <div class="mb-3">
              <label for="notes" class="form-label">{{ __('Notes') }}</label>
              <textarea class="form-control" name="notes" id="notes" rows="2"></textarea>
            </div>

            <div class="form-check">
              <input type="checkbox" class="form-check-input" name="is_gap" id="is_gap" value="1">
              <label class="form-check-label" for="is_gap">{{ __('Mark as provenance gap') }}</label>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="button" class="btn btn-primary" id="save-entry">{{ __('Save') }}</button>
        </div>
      </div>
    </div>
  </div>
  @endauth

</div>

{{-- D3.js + Provenance Timeline --}}
{{-- d3 from jsdelivr: d3js.org is not in the CSP script-src allowlist
     and jsdelivr is - see App\Csp\HeratioCspPreset. Loaded here rather
     than relying on the layout because this view's d3 code runs inside
     @section('content'), which renders before the layout's own d3 tag. --}}
<script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
<script src="{{ asset('vendor/ahg-theme-b5/js/provenance-timeline.js') }}"></script>

<script>
@php $defaultTimeline = ['nodes' => [], 'links' => [], 'events' => [], 'dateRange' => ['min' => 1900, 'max' => 2026]]; @endphp
var timelineData = {!! json_encode($timelineData ?? $defaultTimeline) !!};
var objectSlug = '{{ $io->slug }}';

document.addEventListener('DOMContentLoaded', function() {
  var container = document.getElementById('provenance-timeline');

  // Initialize timeline
  if (container && timelineData.nodes && timelineData.nodes.length > 0) {
    var timeline = new ProvenanceTimeline('#provenance-timeline', {
      data: timelineData,
      width: container.offsetWidth || 800,
      height: 300,
      onNodeClick: function(node) { console.log('Clicked:', node); }
    });

    window.addEventListener('resize', function() {
      timeline.resize(container.offsetWidth, 300);
    });
  }

  @auth
  // Conditional reveals on the overview editor
  var hasGaps = document.getElementById('has_gaps');
  if (hasGaps) hasGaps.addEventListener('change', function() {
    document.getElementById('gapDescriptionGroup').style.display = this.checked ? '' : 'none';
  });
  var naziChecked = document.getElementById('nazi_checked');
  if (naziChecked) naziChecked.addEventListener('change', function() {
    document.getElementById('naziEraClearGroup').style.display = this.checked ? '' : 'none';
  });

  // Custody-entry modal
  var entryModalEl = document.getElementById('entry-modal');
  var entryModal = entryModalEl ? new bootstrap.Modal(entryModalEl) : null;

  var addBtn = document.getElementById('add-entry');
  if (addBtn && entryModal) {
    addBtn.addEventListener('click', function() {
      var form = document.getElementById('entry-form');
      form.reset();
      document.getElementById('entry-id').value = '';
      document.querySelector('#entry-modal .modal-title').textContent = 'Add Custody Entry';
      form.action = '{{ route('io.provenance.store', $io->slug) }}';
      var methodInput = form.querySelector('input[name="_method"]');
      if (methodInput) methodInput.remove();
      entryModal.show();
    });
  }

  document.querySelectorAll('.edit-entry').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = this.getAttribute('data-id');
      var data = this.dataset;
      document.getElementById('entry-id').value = id;
      document.getElementById('owner_name').value = data.owner_name || '';
      document.getElementById('owner_type').value = data.owner_type || 'unknown';
      document.getElementById('owner_location').value = data.owner_location || '';
      document.getElementById('start_date').value = data.start_date || '';
      document.getElementById('end_date').value = data.end_date || '';
      document.getElementById('transfer_type').value = data.transfer_type || 'unknown';
      document.getElementById('certainty').value = data.certainty || 'unknown';
      document.getElementById('sale_price').value = data.sale_price || '';
      document.getElementById('sale_currency').value = data.sale_currency || '';
      document.getElementById('auction_house').value = data.auction_house || '';
      document.getElementById('auction_lot').value = data.auction_lot || '';
      document.getElementById('sources').value = data.sources || '';
      document.getElementById('notes').value = data.notes || '';
      document.getElementById('is_gap').checked = data.is_gap === '1';
      document.querySelector('#entry-modal .modal-title').textContent = 'Edit Custody Entry';

      var form = document.getElementById('entry-form');
      form.action = '/provenance/' + id + '/update';
      var methodInput = form.querySelector('input[name="_method"]');
      if (!methodInput) {
        methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        form.appendChild(methodInput);
      }
      methodInput.value = 'PUT';
      entryModal.show();
    });
  });

  var saveBtn = document.getElementById('save-entry');
  if (saveBtn) saveBtn.addEventListener('click', function() {
    document.getElementById('entry-form').submit();
  });
  @endauth
});
</script>

<style>
.provenance-timeline-container { border-radius: 4px; margin-bottom: 15px; }
.provenance-table-section { margin-bottom: 30px; }
.provenance-actions .btn { margin-right: 10px; }
</style>
@endsection
