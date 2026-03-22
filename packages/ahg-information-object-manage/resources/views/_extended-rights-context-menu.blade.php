@php /**
 * Extended Rights Context Menu Items
 */
if (!isset($resource) || !$resource->id) {
    return;
}

$canEdit = $sf_user->isAuthenticated() && \AtomExtensions\Services\AclService::check($resource, 'update');

// Check if has extended rights
$hasRights = \Illuminate\Database\Capsule\Manager::table('extended_rights')
    ->where('object_id', $resource->id)
    ->exists();

// Check if has active embargo and get its ID
$activeEmbargo = \Illuminate\Database\Capsule\Manager::table('rights_embargo')
    ->where('object_id', $resource->id)
    ->where('status', 'active')
    ->first();

$hasEmbargo = $activeEmbargo !== null; @endphp

@if($canEdit)
<section id="extended-rights-menu">
  <h4>{{ __('Rights') }}</h4>
  <ul class="list-unstyled">
    <li>
      <a href="@php echo url_for(['module' => 'extendedRights', 'action' => 'edit', 'slug' => $resource->slug]); @endphp">
        <i class="fas fa-copyright fa-fw me-1"></i>
        @php echo $hasRights ? __('Edit extended rights') : __('Add extended rights'); @endphp
      </a>
    </li>
    @if($hasRights)
    <li>
      <a href="@php echo url_for(['module' => 'extendedRights', 'action' => 'clear', 'slug' => $resource->slug]); @endphp" class="text-danger">
        <i class="fas fa-eraser fa-fw me-1"></i>
        {{ __('Clear extended rights') }}
      </a>
    </li>
    @endif
    @if($hasEmbargo)
    <li>
      <a href="@php echo url_for(['module' => 'embargo', 'action' => 'edit', 'id' => $activeEmbargo->id]); @endphp">
        <i class="fas fa-edit fa-fw me-1"></i>
        {{ __('Edit embargo') }}
      </a>
    </li>
    <li>
      <a href="@php echo url_for(['module' => 'embargo', 'action' => 'lift', 'id' => $activeEmbargo->id]); @endphp" class="text-success">
        <i class="fas fa-unlock fa-fw me-1"></i>
        {{ __('Lift embargo') }}
      </a>
    </li>
    @else
    <li>
      <a href="@php echo url_for(['module' => 'embargo', 'action' => 'add', 'objectId' => $resource->id]); @endphp">
        <i class="fas fa-lock fa-fw me-1"></i>
        {{ __('Add embargo') }}
      </a>
    </li>
    @endif
    <li>
      <a href="@php echo url_for(['module' => 'extendedRights', 'action' => 'export', 'id' => $resource->id]); @endphp">
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