@extends('theme::layouts.1col')

@section('title', 'Edit Provenance — ' . ($io->title ?? ''))

@section('content')
<div class="container py-3">
  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="{{ route('informationobject.show', $io->slug) }}">{{ $io->title ?? $io->slug }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('io.provenance', $io->slug) }}">Provenance</a></li>
      <li class="breadcrumb-item active">Edit</li>
    </ol>
  </nav>

  <form method="post" id="provenanceForm" enctype="multipart/form-data">
    @csrf
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1"><i class="bi bi-clock-history me-2"></i>Edit Provenance</h4>
        <p class="text-muted mb-0">{{ $io->title ?? $io->slug }}</p>
      </div>
      <div>
        <a href="{{ route('informationobject.show', $io->slug) }}" class="btn atom-btn-white me-2"><i class="bi bi-arrow-left me-1"></i>Back to Record</a>
        <a href="{{ route('io.provenance', $io->slug) }}" class="btn atom-btn-white me-2">Cancel</a>
        <button type="submit" class="btn atom-btn-outline-success">
          <i class="bi bi-check-lg me-1"></i> Save Provenance
        </button>
      </div>
    </div>

    <div class="row">
      <!-- Main Form -->
      <div class="col-lg-8">

        <!-- Provenance Summary -->
        <div class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h6 class="mb-0"><i class="bi bi-file-text me-2"></i>Provenance Summary</h6>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Provenance Statement</label>
              <textarea name="provenance_summary" class="form-control" rows="4" placeholder="Enter a human-readable summary of the item's provenance...">{{ old('provenance_summary', $io->provenance_summary ?? '') }}</textarea>
              <small class="text-muted">This summary will be displayed publicly. Leave blank to auto-generate from events.</small>
            </div>
          </div>
        </div>

        <!-- Acquisition Details -->
        <div class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h6 class="mb-0"><i class="bi bi-cart-check me-2"></i>Acquisition Details</h6>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Acquisition Type</label>
                <select name="acquisition_type" class="form-select">
                  <option value="">-- Select --</option>
                  <option value="purchase" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'purchase')>Purchase</option>
                  <option value="gift" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'gift')>Gift</option>
                  <option value="donation" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'donation')>Donation</option>
                  <option value="bequest" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'bequest')>Bequest</option>
                  <option value="transfer" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'transfer')>Transfer</option>
                  <option value="deposit" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'deposit')>Deposit</option>
                  <option value="loan" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'loan')>Loan</option>
                  <option value="exchange" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'exchange')>Exchange</option>
                  <option value="commission" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'commission')>Commission</option>
                  <option value="field_collection" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'field_collection')>Field Collection</option>
                  <option value="salvage" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'salvage')>Salvage</option>
                  <option value="repatriation" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'repatriation')>Repatriation</option>
                  <option value="unknown" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'unknown')>Unknown</option>
                  <option value="other" @selected(old('acquisition_type', $io->acquisition_type ?? '') === 'other')>Other</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Acquisition Date</label>
                <input type="date" name="acquisition_date" class="form-control" value="{{ old('acquisition_date', $io->acquisition_date ?? '') }}">
              </div>
              <div class="col-md-4">
                <label class="form-label">Date (Text)</label>
                <input type="text" name="acquisition_date_text" class="form-control" placeholder="e.g., circa 1950" value="{{ old('acquisition_date_text', $io->acquisition_date_text ?? '') }}">
              </div>
              <div class="col-md-4">
                <label class="form-label">Price</label>
                <input type="number" name="acquisition_price" class="form-control" step="0.01" value="{{ old('acquisition_price', $io->acquisition_price ?? '') }}">
              </div>
              <div class="col-md-4">
                <label class="form-label">Currency</label>
                <select name="acquisition_currency" class="form-select">
                  <option value="">-- Select --</option>
                  <option value="ZAR" @selected(old('acquisition_currency', $io->acquisition_currency ?? '') === 'ZAR')>ZAR - South African Rand</option>
                  <option value="USD" @selected(old('acquisition_currency', $io->acquisition_currency ?? '') === 'USD')>USD - US Dollar</option>
                  <option value="GBP" @selected(old('acquisition_currency', $io->acquisition_currency ?? '') === 'GBP')>GBP - British Pound</option>
                  <option value="EUR" @selected(old('acquisition_currency', $io->acquisition_currency ?? '') === 'EUR')>EUR - Euro</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Acquisition Notes</label>
                <textarea name="acquisition_notes" class="form-control" rows="2">{{ old('acquisition_notes', $io->acquisition_notes ?? '') }}</textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- Chain of Custody Events -->
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
            <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Chain of Custody Events</h6>
            <button type="button" class="btn btn-sm atom-btn-outline-success" id="addEventBtn">
              <i class="bi bi-plus-lg me-1"></i> Add Event
            </button>
          </div>
          <div class="card-body" id="eventsContainer">
            @if($events->isNotEmpty())
              @foreach($events as $i => $event)
              <div class="event-entry card bg-light mb-3">
                <div class="card-body">
                  <div class="row g-2">
                    <div class="col-md-3">
                      <label class="form-label small">Event Type</label>
                      <select name="event_type[]" class="form-select form-select-sm">
                        <optgroup label="Ownership">
                          <option value="creation" @selected(($event->event_type ?? '') === 'creation')>Creation</option>
                          <option value="commission" @selected(($event->event_type ?? '') === 'commission')>Commission</option>
                          <option value="purchase" @selected(($event->event_type ?? '') === 'purchase')>Purchase</option>
                          <option value="sale" @selected(($event->event_type ?? '') === 'sale')>Sale</option>
                          <option value="gift" @selected(($event->event_type ?? '') === 'gift')>Gift/Donation</option>
                          <option value="bequest" @selected(($event->event_type ?? '') === 'bequest')>Bequest</option>
                          <option value="inheritance" @selected(($event->event_type ?? '') === 'inheritance')>Inheritance</option>
                          <option value="exchange" @selected(($event->event_type ?? '') === 'exchange')>Exchange</option>
                        </optgroup>
                        <optgroup label="Transfer">
                          <option value="transfer" @selected(($event->event_type ?? '') === 'transfer')>Transfer</option>
                          <option value="deposit" @selected(($event->event_type ?? '') === 'deposit')>Deposit</option>
                          <option value="loan" @selected(($event->event_type ?? '') === 'loan')>Loan</option>
                          <option value="return" @selected(($event->event_type ?? '') === 'return')>Return</option>
                          <option value="repatriation" @selected(($event->event_type ?? '') === 'repatriation')>Repatriation</option>
                        </optgroup>
                        <optgroup label="Market">
                          <option value="auction" @selected(($event->event_type ?? '') === 'auction')>Auction</option>
                          <option value="dealer" @selected(($event->event_type ?? '') === 'dealer')>Dealer</option>
                          <option value="appraisal" @selected(($event->event_type ?? '') === 'appraisal')>Appraisal</option>
                        </optgroup>
                        <optgroup label="Loss/Recovery">
                          <option value="theft" @selected(($event->event_type ?? '') === 'theft')>Theft</option>
                          <option value="confiscation" @selected(($event->event_type ?? '') === 'confiscation')>Confiscation</option>
                          <option value="looting" @selected(($event->event_type ?? '') === 'looting')>Looting</option>
                          <option value="recovery" @selected(($event->event_type ?? '') === 'recovery')>Recovery</option>
                          <option value="restitution" @selected(($event->event_type ?? '') === 'restitution')>Restitution</option>
                        </optgroup>
                        <optgroup label="Other">
                          <option value="exhibition" @selected(($event->event_type ?? '') === 'exhibition')>Exhibition</option>
                          <option value="conservation" @selected(($event->event_type ?? '') === 'conservation')>Conservation</option>
                          <option value="other" @selected(($event->event_type ?? '') === 'other')>Other</option>
                        </optgroup>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label small">Date</label>
                      <input type="date" name="event_date[]" class="form-control form-control-sm" value="{{ $event->event_date ?? '' }}">
                    </div>
                    <div class="col-md-2">
                      <label class="form-label small">Date Text</label>
                      <input type="text" name="event_date_text[]" class="form-control form-control-sm" placeholder="circa 1920" value="{{ $event->event_date_text ?? '' }}">
                    </div>
                    <div class="col-md-2">
                      <label class="form-label small">Certainty</label>
                      <select name="event_certainty[]" class="form-select form-select-sm">
                        <option value="certain" @selected(($event->certainty ?? '') === 'certain')>Certain</option>
                        <option value="probable" @selected(($event->certainty ?? '') === 'probable')>Probable</option>
                        <option value="possible" @selected(($event->certainty ?? '') === 'possible')>Possible</option>
                        <option value="uncertain" @selected(($event->certainty ?? '') === 'uncertain')>Uncertain</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label small">From (Agent)</label>
                      <input type="text" name="from_agent[]" class="form-control form-control-sm agent-autocomplete" placeholder="Previous owner..." value="{{ $event->from_agent_name ?? '' }}">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label small">To (Agent)</label>
                      <input type="text" name="to_agent[]" class="form-control form-control-sm agent-autocomplete" placeholder="New owner..." value="{{ $event->to_agent_name ?? '' }}">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label small">Location</label>
                      <input type="text" name="event_location[]" class="form-control form-control-sm" placeholder="City, Country" value="{{ $event->event_location ?? '' }}">
                    </div>
                    <div class="col-md-5">
                      <label class="form-label small">Notes</label>
                      <input type="text" name="event_notes[]" class="form-control form-control-sm" value="{{ $event->notes ?? $event->notes_i18n ?? '' }}">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                      <button type="button" class="btn btn-sm atom-btn-outline-danger remove-event-btn w-100">
                        X
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              @endforeach
            @endif
          </div>
        </div>

        <!-- Research Notes -->
        <div class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>Research Notes</h6>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Research Status</label>
              <select name="research_status" class="form-select">
                <option value="not_started" @selected(old('research_status', $io->research_status ?? '') === 'not_started')>Not Started</option>
                <option value="in_progress" @selected(old('research_status', $io->research_status ?? '') === 'in_progress')>In Progress</option>
                <option value="complete" @selected(old('research_status', $io->research_status ?? '') === 'complete')>Complete</option>
                <option value="inconclusive" @selected(old('research_status', $io->research_status ?? '') === 'inconclusive')>Inconclusive</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Research Notes</label>
              <textarea name="research_notes" class="form-control" rows="3" placeholder="Document your research findings, sources consulted, etc.">{{ old('research_notes', $io->research_notes ?? $io->research_notes_i18n ?? '') }}</textarea>
            </div>
            <div class="form-check mb-3">
              <input type="checkbox" name="has_gaps" class="form-check-input" id="hasGaps" value="1" @checked(old('has_gaps', $io->has_gaps ?? 0))>
              <label class="form-check-label" for="hasGaps">There are gaps in the provenance chain</label>
            </div>
            <div class="mb-0" id="gapDescriptionGroup" style="{{ old('has_gaps', $io->has_gaps ?? 0) ? '' : 'display:none' }}">
              <label class="form-label">Gap Description</label>
              <textarea name="gap_description" class="form-control" rows="2" placeholder="Describe the gaps in provenance...">{{ old('gap_description', $io->gap_description ?? '') }}</textarea>
            </div>
          </div>
        </div>

        <!-- Supporting Documents -->
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
            <h6 class="mb-0"><i class="bi bi-file-earmark me-2"></i>Supporting Documents</h6>
            <button type="button" class="btn btn-sm atom-btn-outline-success" id="addDocumentBtn">
              <i class="bi bi-plus me-1"></i>Add Document
            </button>
          </div>
          <div class="card-body">
            <!-- Existing Documents -->
            @if(!empty($documents) && count($documents) > 0)
            <div class="mb-3">
              <label class="form-label text-muted small">Existing Documents</label>
              @foreach($documents as $doc)
              <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2">
                <div>
                  <i class="bi bi-file-earmark me-2"></i>
                  <strong>{{ $doc->title ?: $doc->original_filename }}</strong>
                  <span class="badge bg-secondary ms-2">{{ ucfirst(str_replace('_', ' ', $doc->document_type)) }}</span>
                </div>
                <div>
                  @if($doc->file_path)
                  <a href="{{ $doc->file_path }}" class="btn btn-sm atom-btn-white" target="_blank"><i class="bi bi-download"></i> View</a>
                  @endif
                  <button type="button" class="btn btn-sm atom-btn-outline-danger delete-doc-btn" data-doc-id="{{ $doc->id }}"><i class="bi bi-trash"></i> Delete</button>
                </div>
              </div>
              @endforeach
            </div>
            @endif

            <!-- New Documents Container -->
            <div id="documentsContainer"></div>
            <p class="text-muted small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Click "Add Document" to add supporting documents. Documents will be uploaded when you save the form.</p>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">

        <!-- Status -->
        <div class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h6 class="mb-0"><i class="bi bi-sliders me-2"></i>Status</h6>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Current Status</label>
              <select name="current_status" class="form-select">
                <option value="owned" @selected(old('current_status', $io->current_status ?? '') === 'owned')>Owned</option>
                <option value="on_loan" @selected(old('current_status', $io->current_status ?? '') === 'on_loan')>On Loan</option>
                <option value="deposited" @selected(old('current_status', $io->current_status ?? '') === 'deposited')>Deposited</option>
                <option value="unknown" @selected(old('current_status', $io->current_status ?? '') === 'unknown')>Unknown</option>
                <option value="disputed" @selected(old('current_status', $io->current_status ?? '') === 'disputed')>Disputed</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Custody Type</label>
              <select name="custody_type" class="form-select">
                <option value="permanent" @selected(old('custody_type', $io->custody_type ?? '') === 'permanent')>Permanent</option>
                <option value="temporary" @selected(old('custody_type', $io->custody_type ?? '') === 'temporary')>Temporary</option>
                <option value="loan" @selected(old('custody_type', $io->custody_type ?? '') === 'loan')>Loan</option>
                <option value="deposit" @selected(old('custody_type', $io->custody_type ?? '') === 'deposit')>Deposit</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Certainty Level</label>
              <select name="certainty_level" class="form-select">
                <option value="verified" @selected(old('certainty_level', $io->certainty_level ?? '') === 'verified')>Verified</option>
                <option value="reliable" @selected(old('certainty_level', $io->certainty_level ?? '') === 'reliable')>Reliable</option>
                <option value="probable" @selected(old('certainty_level', $io->certainty_level ?? '') === 'probable')>Probable</option>
                <option value="possible" @selected(old('certainty_level', $io->certainty_level ?? '') === 'possible')>Possible</option>
                <option value="uncertain" @selected(old('certainty_level', $io->certainty_level ?? '') === 'uncertain')>Uncertain</option>
                <option value="unknown" @selected(old('certainty_level', $io->certainty_level ?? '') === 'unknown')>Unknown</option>
              </select>
            </div>
            <div class="form-check mb-2">
              <input type="checkbox" name="is_complete" class="form-check-input" id="isComplete" value="1" @checked(old('is_complete', $io->is_complete ?? 0))>
              <label class="form-check-label" for="isComplete">Provenance research is complete</label>
            </div>
            <div class="form-check">
              <input type="checkbox" name="is_public" class="form-check-input" id="isPublic" value="1" @checked(old('is_public', $io->is_public ?? 1))>
              <label class="form-check-label" for="isPublic">Display provenance publicly</label>
            </div>
          </div>
        </div>

        <!-- Current Owner -->
        <div class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h6 class="mb-0"><i class="bi bi-person me-2"></i>Current Owner/Holder</h6>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Name</label>
              <input type="text" name="current_agent_name" class="form-control agent-autocomplete" value="{{ old('current_agent_name', $io->current_agent_name ?? '') }}">
            </div>
            <div class="mb-0">
              <label class="form-label">Type</label>
              <select name="current_agent_type" class="form-select">
                <option value="person" @selected(old('current_agent_type', $io->current_agent_type ?? '') === 'person')>Person</option>
                <option value="organization" @selected(old('current_agent_type', $io->current_agent_type ?? '') === 'organization')>Organization</option>
                <option value="family" @selected(old('current_agent_type', $io->current_agent_type ?? '') === 'family')>Family</option>
                <option value="unknown" @selected(old('current_agent_type', $io->current_agent_type ?? '') === 'unknown')>Unknown</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Nazi-Era Provenance -->
        <div class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h6 class="mb-0"><i class="bi bi-shield-check me-2"></i>Nazi-Era Provenance</h6>
          </div>
          <div class="card-body">
            <div class="form-check mb-3">
              <input type="checkbox" name="nazi_era_provenance_checked" class="form-check-input" id="naziEraChecked" value="1" @checked(old('nazi_era_provenance_checked', $io->nazi_era_provenance_checked ?? 0))>
              <label class="form-check-label" for="naziEraChecked">Nazi-era provenance has been checked</label>
            </div>
            <div id="naziEraClearGroup" style="{{ old('nazi_era_provenance_checked', $io->nazi_era_provenance_checked ?? 0) ? '' : 'display:none' }}">
              <div class="mb-3">
                <label class="form-label">Result</label>
                <select name="nazi_era_provenance_clear" class="form-select">
                  <option value="">-- Select --</option>
                  <option value="1" @selected(old('nazi_era_provenance_clear', $io->nazi_era_provenance_clear ?? '') === '1')>Clear - No issues found</option>
                  <option value="0" @selected(old('nazi_era_provenance_clear', $io->nazi_era_provenance_clear ?? '') === '0')>Requires investigation</option>
                </select>
              </div>
              <div class="mb-0">
                <label class="form-label">Notes</label>
                <textarea name="nazi_era_notes" class="form-control" rows="2">{{ old('nazi_era_notes', $io->nazi_era_notes ?? '') }}</textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- Cultural Property -->
        <div class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h6 class="mb-0"><i class="bi bi-globe me-2"></i>Cultural Property</h6>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select name="cultural_property_status" class="form-select">
                <option value="none" @selected(old('cultural_property_status', $io->cultural_property_status ?? '') === 'none')>None / Not Applicable</option>
                <option value="claimed" @selected(old('cultural_property_status', $io->cultural_property_status ?? '') === 'claimed')>Claimed</option>
                <option value="disputed" @selected(old('cultural_property_status', $io->cultural_property_status ?? '') === 'disputed')>Disputed</option>
                <option value="repatriated" @selected(old('cultural_property_status', $io->cultural_property_status ?? '') === 'repatriated')>Repatriated</option>
                <option value="cleared" @selected(old('cultural_property_status', $io->cultural_property_status ?? '') === 'cleared')>Cleared</option>
              </select>
            </div>
            <div class="mb-0">
              <label class="form-label">Notes</label>
              <textarea name="cultural_property_notes" class="form-control" rows="2">{{ old('cultural_property_notes', $io->cultural_property_notes ?? '') }}</textarea>
            </div>
          </div>
        </div>

      </div>
    </div>
  </form>
</div>

<!-- Event Template -->
<template id="eventTemplate">
  <div class="event-entry card bg-light mb-3">
    <div class="card-body">
      <div class="row g-2">
        <div class="col-md-3">
          <label class="form-label small">Event Type</label>
          <select name="event_type[]" class="form-select form-select-sm">
            <optgroup label="Ownership">
              <option value="creation">Creation</option>
              <option value="commission">Commission</option>
              <option value="purchase">Purchase</option>
              <option value="sale">Sale</option>
              <option value="gift">Gift/Donation</option>
              <option value="bequest">Bequest</option>
              <option value="inheritance">Inheritance</option>
              <option value="exchange">Exchange</option>
            </optgroup>
            <optgroup label="Transfer">
              <option value="transfer">Transfer</option>
              <option value="deposit">Deposit</option>
              <option value="loan">Loan</option>
              <option value="return">Return</option>
              <option value="repatriation">Repatriation</option>
            </optgroup>
            <optgroup label="Market">
              <option value="auction">Auction</option>
              <option value="dealer">Dealer</option>
              <option value="appraisal">Appraisal</option>
            </optgroup>
            <optgroup label="Loss/Recovery">
              <option value="theft">Theft</option>
              <option value="confiscation">Confiscation</option>
              <option value="looting">Looting</option>
              <option value="recovery">Recovery</option>
              <option value="restitution">Restitution</option>
            </optgroup>
            <optgroup label="Other">
              <option value="exhibition">Exhibition</option>
              <option value="conservation">Conservation</option>
              <option value="other">Other</option>
            </optgroup>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small">Date</label>
          <input type="date" name="event_date[]" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <label class="form-label small">Date Text</label>
          <input type="text" name="event_date_text[]" class="form-control form-control-sm" placeholder="circa 1920">
        </div>
        <div class="col-md-2">
          <label class="form-label small">Certainty</label>
          <select name="event_certainty[]" class="form-select form-select-sm">
            <option value="certain">Certain</option>
            <option value="probable">Probable</option>
            <option value="possible">Possible</option>
            <option value="uncertain" selected>Uncertain</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small">From (Agent)</label>
          <input type="text" name="from_agent[]" class="form-control form-control-sm agent-autocomplete" placeholder="Previous owner...">
        </div>
        <div class="col-md-3">
          <label class="form-label small">To (Agent)</label>
          <input type="text" name="to_agent[]" class="form-control form-control-sm agent-autocomplete" placeholder="New owner...">
        </div>
        <div class="col-md-3">
          <label class="form-label small">Location</label>
          <input type="text" name="event_location[]" class="form-control form-control-sm" placeholder="City, Country">
        </div>
        <div class="col-md-5">
          <label class="form-label small">Notes</label>
          <input type="text" name="event_notes[]" class="form-control form-control-sm">
        </div>
        <div class="col-md-1 d-flex align-items-end">
          <button type="button" class="btn btn-sm atom-btn-outline-danger remove-event-btn w-100">
            X
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<template id="documentTemplate">
  <div class="document-entry border rounded p-3 mb-2">
    <div class="row g-2">
      <div class="col-md-4">
        <label class="form-label small">Document Type</label>
        <select name="doc_type[]" class="form-select form-select-sm">
          <option value="deed_of_gift">Deed of Gift</option>
          <option value="bill_of_sale">Bill of Sale</option>
          <option value="invoice">Invoice</option>
          <option value="receipt">Receipt</option>
          <option value="auction_catalog">Auction Catalog</option>
          <option value="exhibition_catalog">Exhibition Catalog</option>
          <option value="inventory">Inventory</option>
          <option value="insurance_record">Insurance Record</option>
          <option value="photograph">Photograph</option>
          <option value="correspondence">Correspondence</option>
          <option value="certificate">Certificate</option>
          <option value="customs_document">Customs Document</option>
          <option value="export_license">Export License</option>
          <option value="import_permit">Import Permit</option>
          <option value="appraisal">Appraisal</option>
          <option value="condition_report">Condition Report</option>
          <option value="newspaper_clipping">Newspaper Clipping</option>
          <option value="publication">Publication</option>
          <option value="oral_history">Oral History</option>
          <option value="affidavit">Affidavit</option>
          <option value="legal_document">Legal Document</option>
          <option value="other" selected>Other</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small">Title</label>
        <input type="text" name="doc_title[]" class="form-control form-control-sm" placeholder="Document title...">
      </div>
      <div class="col-md-3">
        <label class="form-label small">Date</label>
        <input type="date" name="doc_date[]" class="form-control form-control-sm">
      </div>
      <div class="col-md-1 d-flex align-items-end">
        <button type="button" class="btn btn-sm atom-btn-outline-danger remove-doc-btn w-100">
          X
        </button>
      </div>
      <div class="col-md-6">
        <label class="form-label small">File Upload</label>
        <input type="file" name="doc_file[]" class="form-control form-control-sm">
      </div>
      <div class="col-md-6">
        <label class="form-label small">Or External URL</label>
        <input type="text" name="doc_url[]" class="form-control form-control-sm" placeholder="https://...">
      </div>
      <div class="col-12">
        <label class="form-label small">Description</label>
        <input type="text" name="doc_description[]" class="form-control form-control-sm" placeholder="Brief description...">
      </div>
    </div>
  </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Add event
  document.getElementById('addEventBtn').addEventListener('click', function() {
    var template = document.getElementById('eventTemplate');
    var clone = template.content.cloneNode(true);
    document.getElementById('eventsContainer').appendChild(clone);
  });

  // Remove event
  document.getElementById('eventsContainer').addEventListener('click', function(e) {
    if (e.target.closest('.remove-event-btn')) {
      e.target.closest('.event-entry').remove();
    }
  });

  // Toggle gap description
  document.getElementById('hasGaps').addEventListener('change', function() {
    document.getElementById('gapDescriptionGroup').style.display = this.checked ? '' : 'none';
  });

  // Toggle Nazi-era clear
  document.getElementById('naziEraChecked').addEventListener('change', function() {
    document.getElementById('naziEraClearGroup').style.display = this.checked ? '' : 'none';
  });

  // Add document
  document.getElementById('addDocumentBtn').addEventListener('click', function() {
    var template = document.getElementById('documentTemplate');
    var clone = template.content.cloneNode(true);
    document.getElementById('documentsContainer').appendChild(clone);
  });

  // Remove document
  document.getElementById('documentsContainer').addEventListener('click', function(e) {
    if (e.target.closest('.remove-doc-btn')) {
      e.target.closest('.document-entry').remove();
    }
  });

  // Delete existing document
  document.querySelectorAll('.delete-doc-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      if (confirm('Delete this document?')) {
        var docId = this.dataset.docId;
        fetch('/provenance/deleteDocument/' + docId, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        }).then(function() { btn.closest('.d-flex').remove(); });
      }
    });
  });
});
</script>
@endsection
