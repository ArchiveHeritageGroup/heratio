@php /**
 * Extended Rights Context Menu Items
 */
if (!isset($resource) || !$resource->id) {
    return;
}

$canEdit = auth()->check() && \AtomExtensions\Services\AclService::check($resource, 'update');

// Check if has extended rights
$hasRights = \Illuminate\Support\Facades\DB::table('extended_rights')
    ->where('object_id', $resource->id)
    ->exists();

// Check if has active embargo and get its ID
$activeEmbargo = \Illuminate\Support\Facades\DB::table('rights_embargo')
    ->where('object_id', $resource->id)
    ->where('status', 'active')
    ->first();

$hasEmbargo = $activeEmbargo !== null; @endphp

@if($canEdit)
<section id="extended-rights-menu">
  <h4>{{ __('Rights') }}</h4>
  <ul class="list-unstyled">
    <li>
      <a href="{{ route('extendedRights.edit', ['slug' => $resource->slug]) }}">
        <i class="fas fa-copyright fa-fw me-1"></i>
        {{ $hasRights ? __('Edit extended rights') : __('Add extended rights') }}
      </a>
    </li>
    @if($hasRights)
    <li>
      <a href="{{ route('extendedRights.clear', ['slug' => $resource->slug]) }}" class="text-danger">
        <i class="fas fa-eraser fa-fw me-1"></i>
        {{ __('Clear extended rights') }}
      </a>
    </li>
    @endif
    @if($hasEmbargo)
    <li>
      <a href="{{ route('embargo.edit', ['id' => $activeEmbargo->id]) }}">
        <i class="fas fa-edit fa-fw me-1"></i>
        {{ __('Edit embargo') }}
      </a>
    </li>
    <li>
      <a href="{{ route('embargo.lift', ['id' => $activeEmbargo->id]) }}" class="text-success">
        <i class="fas fa-unlock fa-fw me-1"></i>
        {{ __('Lift embargo') }}
      </a>
    </li>
    @else
    <li>
      <a href="{{ route('embargo.add', ['objectId' => $resource->id]) }}">
        <i class="fas fa-lock fa-fw me-1"></i>
        {{ __('Add embargo') }}
      </a>
    </li>
    @endif
    <li>
      <a href="{{ route('extendedRights.export', ['id' => $resource->id]) }}">
        <i class="fas fa-download fa-fw me-1"></i>
        {{ __('Export rights (JSON-LD)') }}
      </a>
    </li>
  </ul>
</section>
@endif

@if($hasRights || $hasEmbargo)
<section id="rights-status-menu">
  <h4>{{ __('Rights Status') }}</h4>
  <ul class="list-unstyled">
    @if($hasRights)
    <li><i class="fas fa-copyright text-info fa-fw me-1"></i>{{ __('Extended rights applied') }}</li>
    @endif
    @if($hasEmbargo)
    <li><i class="fas fa-lock text-warning fa-fw me-1"></i>{{ __('Under embargo') }}</li>
    @endif
  </ul>
</section>
@endif
