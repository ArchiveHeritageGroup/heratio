{{-- Add to Collection Button - Migrated from AtoM: _addToCollectionButton.php --}}
@auth
@php
  $showButton = false;
  $collections = [];
  try {
      $researcher = \Illuminate\Support\Facades\DB::table('research_researcher')
          ->where('user_id', Auth::id())
          ->where('status', 'approved')
          ->first();
      if ($researcher) {
          $showButton = true;
          $collections = \Illuminate\Support\Facades\DB::table('research_collection')
              ->where('researcher_id', $researcher->id)
              ->orderBy('name')
              ->get();
      }
  } catch (\Exception $e) {}
@endphp
@if($showButton)
<div class="dropdown d-inline-block">
  <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Add to my collection">
    <i class="fas fa-folder-plus"></i>
  </button>
  <ul class="dropdown-menu dropdown-menu-end">
    <li><h6 class="dropdown-header">Add to Collection</h6></li>
    @if(count($collections) > 0)
      @foreach($collections as $col)
        <li>
          <a class="dropdown-item add-to-collection-btn" href="#"
             data-object-id="{{ $objectId ?? 0 }}"
             data-collection-id="{{ $col->id }}">
            <i class="fas fa-folder me-2"></i>{{ e($col->name) }}
          </a>
        </li>
      @endforeach
      <li><hr class="dropdown-divider"></li>
    @endif
    <li>
      <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#newCollectionModal" data-object-id="{{ $objectId ?? 0 }}">
        <i class="fas fa-plus me-2 text-success"></i>New Collection...
      </a>
    </li>
  </ul>
</div>
@endif
@endauth
