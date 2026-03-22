{{-- Partial: Actor search result --}}
@props(['actor' => null])
@if($actor)
<div class="search-result actor-result d-flex align-items-start py-2 border-bottom">
  <div class="flex-shrink-0 me-3"><div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:48px;height:48px"><i class="fas fa-user text-muted"></i></div></div>
  <div class="flex-grow-1">
    <h6 class="mb-1"><a href="{{ route('actor.show', $actor->slug ?? $actor->id) }}">{{ $actor->authorized_form_of_name ?? '[Untitled]' }}</a>
    @if($actor->entity_type_id ?? false)<span class="badge bg-info ms-1">{{ $actor->entity_type_label ?? 'Actor' }}</span>@endif</h6>
    @if($actor->dates_of_existence ?? false)<small class="text-muted"><i class="fas fa-calendar me-1"></i>{{ $actor->dates_of_existence }}</small>@endif
    @if($actor->history ?? false)<p class="small text-muted mb-0 mt-1">{{ Str::limit(strip_tags($actor->history), 150) }}</p>@endif
  </div>
</div>
@endif
