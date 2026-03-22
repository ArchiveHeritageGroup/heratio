{{-- Partial component --}}
@props(['models' => collect()])
@if($models->count() > 1)
<div class="multi-angle-gallery mb-4">
  <h5><i class="fas fa-images me-2"></i>Multi-Angle Views</h5>
  <div class="row g-2">
    @foreach($models as $model)
    <div class="col-4 col-md-3 col-lg-2">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center justify-content-center" style="min-height:120px;background:#f8f9fa;">
          @if($model->thumbnail)<img src="/uploads/{{ $model->thumbnail }}" class="img-fluid rounded">@else<i class="fas fa-cube fa-2x text-muted"></i>@endif
        </div>
        <div class="card-footer p-1 text-center"><small class="text-muted">{{ $model->angle_label ?? 'View' }}</small></div>
      </div>
    </div>
    @endforeach
  </div>
</div>
@endif
