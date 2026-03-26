@php /**
 * Name Access Points Partial - Laravel Version
 *
 * Shows actors related to the information object via events and relations.
 *
 * @package    ahg-core
 * @subpackage templates
 */

use Illuminate\Support\Facades\DB;

// Get resource ID
$resourceId = is_object($resource) ? ($resource->id ?? null) : $resource;

if (!$resourceId) {
    return;
}

$culture = app()->getLocale();

// Get actor events (creators, contributors, etc.)
$actorEvents = DB::table('event as e')
    ->join('actor as a', 'e.actor_id', '=', 'a.id')
    ->leftJoin('actor_i18n as ai', function ($join) use ($culture) {
        $join->on('a.id', '=', 'ai.id')->where('ai.culture', '=', $culture);
    })
    ->leftJoin('actor_i18n as ai_en', function ($join) {
        $join->on('a.id', '=', 'ai_en.id')->where('ai_en.culture', '=', 'en');
    })
    ->leftJoin('term_i18n as ti', function ($join) use ($culture) {
        $join->on('e.type_id', '=', 'ti.id')->where('ti.culture', '=', $culture);
    })
    ->leftJoin('term_i18n as ti_en', function ($join) {
        $join->on('e.type_id', '=', 'ti_en.id')->where('ti_en.culture', '=', 'en');
    })
    ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
    ->where('e.object_id', $resourceId)
    ->whereNotNull('e.actor_id')
    ->select(['a.id', 'e.type_id', 'slug.slug',
        DB::raw('COALESCE(ai.authorized_form_of_name, ai_en.authorized_form_of_name) as name'),
        DB::raw('COALESCE(ti.name, ti_en.name) as event_type')])
    ->orderBy(DB::raw('COALESCE(ai.authorized_form_of_name, ai_en.authorized_form_of_name)'))
    ->get()->toArray();

// Get name access points via relations
$nameAccessPoints = DB::table('relation as r')
    ->join('actor as a', 'r.object_id', '=', 'a.id')
    ->leftJoin('actor_i18n as ai', function ($join) use ($culture) {
        $join->on('a.id', '=', 'ai.id')->where('ai.culture', '=', $culture);
    })
    ->leftJoin('actor_i18n as ai_en', function ($join) {
        $join->on('a.id', '=', 'ai_en.id')->where('ai_en.culture', '=', 'en');
    })
    ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
    ->where('r.subject_id', $resourceId)
    ->where('r.type_id', 161)
    ->select(['a.id', 'slug.slug',
        DB::raw('COALESCE(ai.authorized_form_of_name, ai_en.authorized_form_of_name) as name')])
    ->orderBy(DB::raw('COALESCE(ai.authorized_form_of_name, ai_en.authorized_form_of_name)'))
    ->get()->toArray();

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
$isSidebar = isset($sidebar) && $sidebar;
@endphp

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
<div class="field">

  @if(isset($mods))
    <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Names') }}</h3>
  @else
    <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Name access points') }}</h3>
  @endif

  <div>
    <ul class="list-unstyled">
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
