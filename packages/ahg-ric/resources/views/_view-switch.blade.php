{{-- Heratio / RiC View Toggle — only rendered when ahgRicExplorerPlugin is enabled.
     The flat-view button label reflects the description standard used for the
     current entity type (ISAD(G) for archival descriptions, ISAAR(CPF) for
     actors, ISDIAH for repositories, etc.). Pass via:
       @include('ahg-ric::_view-switch', ['standard' => 'ISAD(G)', 'entityType' => 'actor', 'objectId' => $entity->id])

     #1425 tail: when an `entityType` + `objectId` pair is supplied the toggle
     reads AND writes a PERSISTENT per-record preference (RicViewModeService,
     keyed on (entity_type, entity_id)) - the choice survives the session and
     does not bleed onto other records. Without the pair it falls back to the
     old session-global `ric_view_mode` key so any un-migrated include still
     works. Falls back to a generic 'Record' label if no standard is supplied. --}}
@if(\AhgCore\Services\MenuService::isPluginEnabled('ahgRicExplorerPlugin'))
  @php
    $objectId = $objectId ?? null;
    $entityType = $entityType ?? null;
    $perRecord = $entityType && $objectId;
    $viewMode = $perRecord
        ? \AhgRic\Services\RicViewModeService::mode((string) $entityType, (int) $objectId)
        : session('ric_view_mode', config('ric.default_view', 'heratio'));
    $flatLabel = $standard ?? 'Record';
  @endphp

  <div class="ric-view-switch d-flex align-items-center gap-2 mb-3">
    <span class="small text-muted">{{ __('View:') }}</span>
    <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('View mode') }}">
      <form method="POST" action="{{ route('ric.set-view-mode') }}" style="display:inline;">
        @csrf
        <input type="hidden" name="mode" value="heratio">
        @if($perRecord)<input type="hidden" name="entity_type" value="{{ $entityType }}"><input type="hidden" name="object_id" value="{{ (int) $objectId }}">@endif
        <button type="submit" class="btn {{ $viewMode === 'heratio' ? 'btn-primary' : 'btn-outline-secondary' }}">
          <i class="fas fa-list-alt me-1"></i>{{ __($flatLabel) }}
        </button>
      </form>
      <form method="POST" action="{{ route('ric.set-view-mode') }}" style="display:inline;">
        @csrf
        <input type="hidden" name="mode" value="ric">
        @if($perRecord)<input type="hidden" name="entity_type" value="{{ $entityType }}"><input type="hidden" name="object_id" value="{{ (int) $objectId }}">@endif
        <button type="submit" class="btn {{ $viewMode === 'ric' ? 'btn-success' : 'btn-outline-secondary' }}">
          <i class="fas fa-project-diagram me-1"></i>{{ __('RiC') }}
        </button>
      </form>
    </div>
  </div>
@endif
