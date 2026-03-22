@php
$provenance = $provenance ?? [];
$agreements = $agreements ?? [];
@endphp
@if(count($provenance) > 0 || count($agreements) > 0)
<section id="provenance-area" class="card mb-3">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h4 class="mb-0"><i class="fas fa-history me-2"></i>Provenance</h4>
  </div>
  <div class="card-body">

    @if(count($agreements) > 0)
    <h6 class="text-muted">Donor Agreements</h6>
    <ul class="list-unstyled mb-3">
      @foreach($agreements as $agreement)
        <li class="mb-2">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              {{ $agreement->agreement_title ?? $agreement->agreement_number ?? '' }}
              <span class="badge bg-secondary ms-1">{{ ucfirst(str_replace('_', ' ', $agreement->relationship_type ?? '')) }}</span>
              <br><small class="text-muted">Donor: {{ $agreement->donor_name ?? '' }}</small>
            </div>
            <small class="text-muted">{{ $agreement->agreement_date ?? '' }}</small>
          </div>
        </li>
      @endforeach
    </ul>
    @endif

    @if(count($provenance) > 0)
    <h6 class="text-muted">Custody History</h6>
    <div class="provenance-timeline">
      @foreach($provenance as $i => $record)
        <div class="provenance-item d-flex mb-2">
          <div class="provenance-marker me-3">
            <span class="badge rounded-pill bg-{{ $i === 0 ? 'primary' : 'secondary' }}">{{ count($provenance) - $i }}</span>
          </div>
          <div class="provenance-content flex-grow-1">
            <div class="d-flex justify-content-between">
              <div>
                <strong>{{ $record->donor_name ?? '' }}</strong>
                <span class="badge bg-info ms-1">{{ ucfirst($record->relationship_type ?? '') }}</span>
              </div>
              <small class="text-muted">{{ $record->provenance_date ?? '' }}</small>
            </div>
            @if($record->notes ?? null)
              <p class="small text-muted mb-0 mt-1">{{ $record->notes }}</p>
            @endif
          </div>
        </div>
      @endforeach
    </div>
    @endif

  </div>
</section>
@endif
