@extends('theme::layouts.1col')

@section('title', 'Provenance History — ' . ($io->title ?? ''))

@section('content')
<div class="container py-3">

  <h1>{{ __('Provenance History') }}</h1>

  <div class="object-info mb-3">
    <p>
      <strong>{{ $io->identifier ?? '' }}</strong> -
      {{ $io->title ?? '' }}
    </p>
  </div>

  {{-- Back button --}}
  <div class="provenance-navigation mb-4">
    <a href="{{ route('informationobject.show', $io->slug) }}" class="btn btn-outline-primary">
      <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Archival Description') }}
    </a>
  </div>

  {{-- Timeline Container (hidden if empty, D3 populates it) --}}
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
  <div class="provenance-table-section">
    <h2>{{ __('Ownership History') }}</h2>

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
      <div class="alert alert-info">
        No provenance information recorded for this object.
      </div>
    @endif

    @auth
      <div class="provenance-actions mt-3">
        <button class="btn btn-primary" id="add-entry">
          <i class="fas fa-plus"></i> {{ __('Add Provenance Entry') }}
        </button>
        <a href="{{ route('io.provenance.exportCsv', $io->slug) }}" class="btn btn-secondary">
          <i class="fas fa-download"></i> {{ __('Export CSV') }}
        </a>
      </div>
    @endauth
  </div>

  {{-- Add/Edit Entry Modal --}}
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
                  <option value="unknown">{{ __('Unknown') }}</option>
                  <option value="person">{{ __('Person') }}</option>
                  <option value="family">{{ __('Family') }}</option>
                  <option value="dealer">{{ __('Dealer') }}</option>
                  <option value="auction_house">{{ __('Auction House') }}</option>
                  <option value="museum">{{ __('Museum') }}</option>
                  <option value="corporate">{{ __('Corporate') }}</option>
                  <option value="government">{{ __('Government') }}</option>
                  <option value="religious">{{ __('Religious') }}</option>
                  <option value="artist">{{ __('Artist') }}</option>
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
                  <option value="certain">{{ __('Certain') }}</option>
                  <option value="probable">{{ __('Probable') }}</option>
                  <option value="possible">{{ __('Possible') }}</option>
                  <option value="uncertain">{{ __('Uncertain') }}</option>
                  <option value="unknown">{{ __('Unknown') }}</option>
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
                  <option value="unknown">{{ __('Unknown') }}</option>
                  <option value="sale">{{ __('Sale') }}</option>
                  <option value="auction">{{ __('Auction') }}</option>
                  <option value="gift">{{ __('Gift') }}</option>
                  <option value="bequest">{{ __('Bequest') }}</option>
                  <option value="inheritance">{{ __('Inheritance') }}</option>
                  <option value="commission">{{ __('Commission') }}</option>
                  <option value="exchange">{{ __('Exchange') }}</option>
                  <option value="transfer">{{ __('Transfer') }}</option>
                  <option value="found">{{ __('Found/Discovery') }}</option>
                  <option value="restitution">{{ __('Restitution') }}</option>
                  <option value="repatriation">{{ __('Repatriation') }}</option>
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
                  <option value="">--</option>
                  <option value="ZAR">{{ __('ZAR') }}</option>
                  <option value="USD">{{ __('USD') }}</option>
                  <option value="EUR">{{ __('EUR') }}</option>
                  <option value="GBP">{{ __('GBP') }}</option>
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
      var form = document.getElementById('entry-form');
      form.reset();
      document.getElementById('entry-id').value = '';
      document.querySelector('#entry-modal .modal-title').textContent = 'Add Provenance Entry';
      form.action = '{{ route('io.provenance.store', $io->slug) }}';
      // Remove _method=PUT if left over from a previous edit
      var methodInput = form.querySelector('input[name="_method"]');
      if (methodInput) methodInput.remove();
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
