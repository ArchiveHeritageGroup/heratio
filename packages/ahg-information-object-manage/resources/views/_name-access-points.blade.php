@php
/**
 * Name Access Points Partial
 *
 * Shows actors related to the information object via events and relations.
 */

// Get resource ID
$resourceId = is_object($resource) ? ($resource->id ?? null) : $resource;

if (!$resourceId) {
    return;
}

// Get actor events (creators, contributors, etc.)
$actorEvents = [];
if (method_exists($resource, 'getActorEvents')) {
    $actorEvents = $resource->getActorEvents();
} elseif (function_exists('ahg_get_actor_events')) {
    $actorEvents = ahg_get_actor_events($resourceId);
}

// Get name access points via relations
$nameAccessPoints = [];
if (method_exists($resource, 'getNameAccessRelations')) {
    $nameAccessPoints = $resource->getNameAccessRelations();
} elseif (function_exists('ahg_get_name_access_relations')) {
    $nameAccessPoints = ahg_get_name_access_relations($resourceId);
}

// Combine and deduplicate by actor ID
$allActors = [];
$seenIds = [];

foreach ($actorEvents as $event) {
    if (!in_array($event->id, $seenIds)) {
        $seenIds[] = $event->id;
        $allActors[] = $event;
    }
}

foreach ($nameAccessPoints as $nap) {
    if (!in_array($nap->id, $seenIds)) {
        $seenIds[] = $nap->id;
        $nap->event_type = null;
        $allActors[] = $nap;
    }
}

if (empty($allActors)) {
    return;
}

// Check if sidebar display
$isSidebar = isset($sidebar) && $sidebar; @endphp

@if($isSidebar)
  <section id="nameAccessPointsSection">
    <h4>{{ __('Related people and organizations') }}</h4>
    <ul class="list-unstyled">
      @foreach($allActors as $actor)
        <li>
          @if($actor->slug)
            <a href="{{ route('actor.show', $actor->slug) }}">
              {{ $actor->name ?? '' }}
            </a>
          @else
            {{ $actor->name ?? '' }}
          @endif
          @if(!empty($actor->event_type))
            <span class="text-muted">({{ $actor->event_type }})</span>
          @endif
        </li>
      @endforeach
    </ul>
  </section>
@else
<div class="field{{ isset($sidebar) ? '' : ' mb-3' }}">

  @if(isset($mods))
    <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Names') }}</h3>
  @else
    <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Name access points') }}</h3>
  @endif

  <div{!! isset($sidebar) ? '' : ' class="ms-0"' !!}>
    <ul class="{{ isset($sidebar) ? 'list-unstyled' : 'list-unstyled ms-0' }}">
      @foreach($allActors as $actor)
        <li>
          @if($actor->slug)
            <a href="{{ route('actor.show', $actor->slug) }}">{{ $actor->name ?? '' }}</a>
          @else
            {{ $actor->name ?? '' }}
          @endif
          @if(!empty($actor->event_type))
            <span class="text-muted">({{ $actor->event_type }})</span>
          @endif
        </li>
      @endforeach
    </ul>
  </div>

</div>
@endif
