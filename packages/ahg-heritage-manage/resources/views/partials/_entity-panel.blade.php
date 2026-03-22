{{-- Entity Panel Partial --}}
<div class="card heritage-entity-panel mb-3">
  <div class="card-body">
    @if(isset($entity))
      <h5>{{ $entity->title ?? $entity->name ?? 'Untitled' }}</h5>
      @if($entity->description ?? null)
        <p class="text-muted">{{ Str::limit($entity->description, 200) }}</p>
      @endif
      @if($entity->thumbnail ?? null)
        <img src="{{ $entity->thumbnail }}" alt="" class="img-fluid rounded mb-2" style="max-height:150px">
      @endif
    @endif
  </div>
</div>