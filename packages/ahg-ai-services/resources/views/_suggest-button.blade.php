{{-- Partial: AI tool button --}}
@props(['objectId' => null, 'size' => 'sm'])
<button type="button" class="btn btn-{{ $size }} atom-btn-white ai-action-btn" data-object-id="{{ $objectId }}">
  <i class="fas fa-robot me-1"></i>AI Action
</button>
