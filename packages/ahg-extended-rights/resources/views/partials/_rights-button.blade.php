{{--
  Rights action button for display pages
  Include with: @include('ahg-extended-rights::partials._rights-button', ['slug' => $slug])
--}}
@php
  $slug = $slug ?? '';
  $hasRights = false;
  $isRestricted = false;

  if (!empty($slug)) {
      $objectId = \Illuminate\Support\Facades\DB::table('slug')->where('slug', $slug)->value('object_id');
      if ($objectId) {
          $service = app(\AhgExtendedRights\Services\ExtendedRightsService::class);
          $hasRights = $service->getRightsForObject($objectId)->count() > 0;
          $embargo = $service->getEmbargo($objectId);
          $isRestricted = $embargo !== null;
      }
  }
@endphp
<a href="{{ route('ext-rights.index', $slug) }}"
   class="btn btn-{{ $isRestricted ? 'warning' : ($hasRights ? 'outline-primary' : 'outline-secondary') }} btn-sm me-1"
   title="{{ __('View rights') }}">
    <i class="fas fa-{{ $isRestricted ? 'lock' : 'balance-scale' }} me-1"></i>
    Rights
    @if($isRestricted)
        <span class="badge bg-danger ms-1">!</span>
    @endif
</a>
