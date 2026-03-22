{{-- Partial component --}}
@props(['models' => collect(), 'resource' => null])
@if($models->count())
<div class="model-3d-section mb-4">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="mb-0"><i class="fas fa-cube me-2"></i>3D Model{{ $models->count() > 1 ? 's' : '' }} <span class="badge bg-secondary">{{ $models->count() }}</span></h4>
    @auth
    <a href="{{ route('admin.3d-models.browse') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-plus me-1"></i>Add</a>
    @endauth
  </div>
  @foreach($models as $model)
  <div class="card mb-3">
    <div class="card-body p-0" style="height:400px;background:#f8f9fa;">
      <div class="d-flex align-items-center justify-content-center h-100 text-muted text-center">
        <div><i class="fas fa-cube fa-3x mb-2"></i><p>{{ $model->original_filename ?? 'Model' }}</p><small>{{ strtoupper($model->format ?? 'GLB') }} &bull; {{ number_format(($model->file_size ?? 0)/1048576, 2) }} MB</small></div>
      </div>
    </div>
  </div>
  @endforeach
</div>
@endif
