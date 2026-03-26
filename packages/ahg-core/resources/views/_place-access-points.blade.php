@php
use Illuminate\Support\Facades\DB;

$resourceId = is_object($resource) ? ($resource->id ?? null) : $resource;
if (!$resourceId) { return; }

$culture = app()->getLocale();
$placeTaxonomyId = 42;

$places = DB::table('object_term_relation as otr')
    ->join('term as t', 'otr.term_id', '=', 't.id')
    ->leftJoin('term_i18n as ti', function ($join) use ($culture) {
        $join->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
    })
    ->leftJoin('term_i18n as ti_en', function ($join) {
        $join->on('t.id', '=', 'ti_en.id')->where('ti_en.culture', '=', 'en');
    })
    ->leftJoin('slug', 't.id', '=', 'slug.object_id')
    ->where('otr.object_id', $resourceId)
    ->where('t.taxonomy_id', $placeTaxonomyId)
    ->select(['t.id', 'slug.slug', DB::raw('COALESCE(ti.name, ti_en.name) as name')])
    ->orderBy(DB::raw('COALESCE(ti.name, ti_en.name)'))
    ->get()->toArray();

if (empty($places)) { return; }
$isSidebar = isset($sidebar) && $sidebar;
@endphp

@if($isSidebar)
  <section id="placeAccessPointsSection">
    <h4>{{ __('Place access points') }}</h4>
    <ul class="list-unstyled">
      @foreach($places as $place)
        <li>
          @if($place->slug)
            <a href="{{ route('term.show', $place->slug) }}">{{ $place->name ?? '' }}</a>
          @else
            {{ $place->name ?? '' }}
          @endif
        </li>
      @endforeach
    </ul>
  </section>
@else
<div class="field">

  <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Place access points') }}</h3>

  <div>
    <ul class="list-unstyled">
      @foreach($places as $place)
        <li>
          @if($place->slug)
            <a href="{{ route('term.show', $place->slug) }}">{{ $place->name ?? '' }}</a>
          @else
            {{ $place->name ?? '' }}
          @endif
        </li>
      @endforeach
    </ul>
  </div>

</div>
@endif
