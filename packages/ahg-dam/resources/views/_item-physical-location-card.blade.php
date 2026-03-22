{{-- Partial: Item physical location card --}}
@props(['item' => null])
@if($item)
<div class="card mb-2"><div class="card-body p-2"><div class="d-flex justify-content-between">
  <div><i class="fas fa-map-marker-alt me-1 text-muted"></i><small>{{ $item->location ?? 'No location' }}</small></div>
  <div><span class="badge bg-secondary">{{ $item->type ?? 'Physical' }}</span></div>
</div></div></div>
@endif
