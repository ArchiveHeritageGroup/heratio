@extends('theme::layouts.1col')

@section('title', 'Provenance History — ' . ($io->title ?? ''))

@section('content')
<div class="container py-3">

  <h1>Provenance History</h1>

  <div class="object-info mb-3">
    <p>
      <strong>{{ $io->identifier ?? '' }}</strong> -
      {{ $io->title ?? '' }}
    </p>
  </div>

  {{-- Back button --}}
  <div class="provenance-navigation mb-4">
    <a href="{{ route('informationobject.show', $io->slug) }}" class="btn btn-outline-primary">
      <i class="fas fa-arrow-left me-1"></i> Back to Archival Description
    </a>
  </div>

  {{-- Timeline Container (hidden if empty, D3 populates it) --}}
  <div id="provenance-timeline"></div>

  {{-- Visual Chain Diagram --}}
  @if($events->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-link me-1"></i> Chain of Custody
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
                      'sale' => '💰', 'auction' => '🔨', 'gift' => '🎁', 'bequest' => '📜',
                      'inheritance' => '📜', 'commission' => '📋', 'exchange' => '🔄',
                      'transfer' => '➡️', 'found' => '🔍', 'restitution' => '⚖️', 'repatriation' => '🏠',
                    ];
                  @endphp
                  <span style="font-size:1.2rem;">{{ $transferIcons[$entry->transfer_type] ?? '➡️' }}</span>
                </div>
                <small class="text-muted text-center" style="font-size:0.65rem;line-height:1.1;">{{ ucfirst(str_replace('_', ' ', $entry->transfer_type ?? '')) }}</small>
              </div>
            @endif
            {{-- Owner node --}}
            <div class="text-center" style="min-width:100px;max-width:130px;">
              @php
                $typeIcons = [
                  'person' => '👤', 'family' => '👨‍👩‍👧‍👦', 'dealer' => '🏪', 'auction_house' => '🔨',
                  'museum' => '🏛️', 'corporate' => '🏢', 'government' => '🏛️', 'religious' => '⛪',
                  'artist' => '🎨',
                ];
                $bgColors = [
                  'person' => '#dc3545', 'family' => '#dc3545', 'dealer' => '#fd7e14',
                  'auction_house' => '#fd7e14', 'museum' => '#0d6efd', 'corporate' => '#6c757d',
                  'government' => '#198754', 'religious' => '#6f42c1', 'artist' => '#d63384',
                ];
                $icon = $typeIcons[$entry->owner_type] ?? '❓';
                $bg = $bgColors[$entry->owner_type] ?? '#6c757d';
              @endphp
              <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                   style="width:50px;height:50px;background:{{ $bg }};color:#fff;font-size:1.3rem;">
                {{ $icon }}
              </div>
              <strong class="small d-block" style="font-size:0.75rem;line-height:1.2;" title="{{ $entry->owner_name }}">
                {{ \Illuminate\Support\Str::limit($entry->owner_name, 20) }}
              </strong>
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
  <div class="provenance-table-section">
    <h2>Ownership History</h2>

    @if($events->isNotEmpty())
      <table class="table table-striped provenance-table">
        <thead>
          <tr>
            <th width="5%">#</th>
            <th width="25%">Owner</th>
            <th width="15%">Location</th>
            <th width="15%">Period</th>
            <th width="15%">Transfer</th>
            <th width="10%">Certainty</th>
            @auth
              <th width="15%">Actions</th>
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
      <div class="alert alert-info">
        No provenance information recorded for this object.
      </div>
    @endif

    @auth
      <div class="provenance-actions mt-3">
        <button class="btn btn-primary" id="add-entry">
          <i class="fas fa-plus"></i> Add Provenance Entry
        </button>
      </div>
    @endauth
  </div>

  {{-- Add/Edit Entry Modal --}}
  @auth
  <div class="modal fade" id="entry-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Provenance Entry</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="entry-form" method="POST" action="{{ route('io.provenance.store', $io->slug) }}">
            @csrf
            <input type="hidden" name="entry_id" id="entry-id">

            <div class="row mb-3">
              <div class="col-md-8">
                <label for="owner_name" class="form-label">Owner Name *</label>
                <input type="text" class="form-control" name="owner_name" id="owner_name" required>
              </div>
              <div class="col-md-4">
                <label for="owner_type" class="form-label">Owner Type</label>
                <select class="form-select" name="owner_type" id="owner_type">
                  <option value="unknown">Unknown</option>
                  <option value="person">Person</option>
                  <option value="family">Family</option>
                  <option value="dealer">Dealer</option>
                  <option value="auction_house">Auction House</option>
                  <option value="museum">Museum</option>
                  <option value="corporate">Corporate</option>
                  <option value="government">Government</option>
                  <option value="religious">Religious</option>
                  <option value="artist">Artist</option>
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-8">
                <label for="owner_location" class="form-label">Location</label>
                <input type="text" class="form-control" name="owner_location" id="owner_location" placeholder="City, Country">
              </div>
              <div class="col-md-4">
                <label for="certainty" class="form-label">Certainty</label>
                <select class="form-select" name="certainty" id="certainty">
                  <option value="certain">Certain</option>
                  <option value="probable">Probable</option>
                  <option value="possible">Possible</option>
                  <option value="uncertain">Uncertain</option>
                  <option value="unknown">Unknown</option>
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="text" class="form-control" name="start_date" id="start_date" placeholder="YYYY or text">
              </div>
              <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="text" class="form-control" name="end_date" id="end_date" placeholder="YYYY or text">
              </div>
              <div class="col-md-6">
                <label for="transfer_type" class="form-label">Transfer Method</label>
                <select class="form-select" name="transfer_type" id="transfer_type">
                  <option value="unknown">Unknown</option>
                  <option value="sale">Sale</option>
                  <option value="auction">Auction</option>
                  <option value="gift">Gift</option>
                  <option value="bequest">Bequest</option>
                  <option value="inheritance">Inheritance</option>
                  <option value="commission">Commission</option>
                  <option value="exchange">Exchange</option>
                  <option value="transfer">Transfer</option>
                  <option value="found">Found/Discovery</option>
                  <option value="restitution">Restitution</option>
                  <option value="repatriation">Repatriation</option>
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-4">
                <label for="sale_price" class="form-label">Sale Price</label>
                <input type="number" class="form-control" name="sale_price" id="sale_price" step="0.01">
              </div>
              <div class="col-md-2">
                <label for="sale_currency" class="form-label">Currency</label>
                <select class="form-select" name="sale_currency" id="sale_currency">
                  <option value="">--</option>
                  <option value="ZAR">ZAR</option>
                  <option value="USD">USD</option>
                  <option value="EUR">EUR</option>
                  <option value="GBP">GBP</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="auction_house" class="form-label">Auction House</label>
                <input type="text" class="form-control" name="auction_house" id="auction_house">
              </div>
              <div class="col-md-2">
                <label for="auction_lot" class="form-label">Lot #</label>
                <input type="text" class="form-control" name="auction_lot" id="auction_lot">
              </div>
            </div>

            <div class="mb-3">
              <label for="sources" class="form-label">Sources/Documentation</label>
              <textarea class="form-control" name="sources" id="sources" rows="2"></textarea>
            </div>

            <div class="mb-3">
              <label for="notes" class="form-label">Notes</label>
              <textarea class="form-control" name="notes" id="notes" rows="2"></textarea>
            </div>

            <div class="form-check">
              <input type="checkbox" class="form-check-input" name="is_gap" id="is_gap" value="1">
              <label class="form-check-label" for="is_gap">Mark as provenance gap</label>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="save-entry">Save</button>
        </div>
      </div>
    </div>
  </div>
  @endauth

</div>

{{-- D3.js + Provenance Timeline --}}
<script src="https://d3js.org/d3.v7.min.js"></script>
<script src="{{ asset('vendor/ahg-theme-b5/js/provenance-timeline.js') }}"></script>

<script>
@php $defaultTimeline = ['nodes' => [], 'links' => [], 'events' => [], 'dateRange' => ['min' => 1900, 'max' => 2026]]; @endphp
var timelineData = {!! json_encode($timelineData ?? $defaultTimeline) !!};
var objectSlug = '{{ $io->slug }}';

document.addEventListener('DOMContentLoaded', function() {
  var container = document.getElementById('provenance-timeline');

  // Initialize timeline
  if (timelineData.nodes && timelineData.nodes.length > 0) {
    var timeline = new ProvenanceTimeline('#provenance-timeline', {
      data: timelineData,
      width: container.offsetWidth || 800,
      height: 300,
      onNodeClick: function(node) {
        console.log('Clicked:', node);
      }
    });

    window.addEventListener('resize', function() {
      timeline.resize(container.offsetWidth, 300);
    });
  }

  @auth
  // Modal instance
  var entryModalEl = document.getElementById('entry-modal');
  var entryModal = entryModalEl ? new bootstrap.Modal(entryModalEl) : null;

  // Add entry button
  var addBtn = document.getElementById('add-entry');
  if (addBtn && entryModal) {
    addBtn.addEventListener('click', function() {
      document.getElementById('entry-form').reset();
      document.getElementById('entry-id').value = '';
      document.querySelector('#entry-modal .modal-title').textContent = 'Add Provenance Entry';
      document.getElementById('entry-form').action = '{{ route('io.provenance.store', $io->slug) }}';
      entryModal.show();
    });
  }

  // Edit entry buttons
  document.querySelectorAll('.edit-entry').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = this.getAttribute('data-id');
      var data = this.dataset;

      // Populate form fields from data attributes
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

      document.querySelector('#entry-modal .modal-title').textContent = 'Edit Provenance Entry';

      // Set form action to update route
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

  // Save entry
  var saveBtn = document.getElementById('save-entry');
  if (saveBtn) {
    saveBtn.addEventListener('click', function() {
      document.getElementById('entry-form').submit();
    });
  }
  @endauth
});
</script>

<style>
.provenance-timeline-container {
  border-radius: 4px;
  margin-bottom: 15px;
}
.provenance-table-section { margin-bottom: 30px; }
.provenance-actions .btn { margin-right: 10px; }
</style>
@endsection
