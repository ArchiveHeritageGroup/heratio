{{--
  Heratio - Term related-authorities sidebar partial.

  Renders a compact actor/repository list for inclusion on the term show
  page. Expects $term (object with id, slug, taxonomy_id) and optionally
  $sidebarLimit (defaults to 5).

  Migrated from PSIS term/_sidebar.php (#743).

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  Licensed under AGPL-3.0-or-later.
--}}
@php
  $sidebarLimit = $sidebarLimit ?? 5;
  $culture = app()->getLocale();

  $sidebarTermIds = [$term->id];
  $sidebarNarrowerIds = \Illuminate\Support\Facades\DB::table('term')
      ->where('parent_id', $term->id)->pluck('id')->toArray();
  if (! empty($sidebarNarrowerIds)) {
      $sidebarTermIds = array_merge($sidebarTermIds, $sidebarNarrowerIds);
  }

  $sidebarActors = \Illuminate\Support\Facades\DB::table('object_term_relation')
      ->join('actor', 'object_term_relation.object_id', '=', 'actor.id')
      ->join('object', 'actor.id', '=', 'object.id')
      ->leftJoin('actor_i18n', function ($j) use ($culture) {
          $j->on('actor.id', '=', 'actor_i18n.id')
              ->where('actor_i18n.culture', '=', $culture);
      })
      ->leftJoin('slug', 'actor.id', '=', 'slug.object_id')
      ->whereIn('object_term_relation.term_id', $sidebarTermIds)
      ->where('object.class_name', 'QubitActor')
      ->select('actor.id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug')
      ->distinct()
      ->orderBy('actor_i18n.authorized_form_of_name')
      ->limit($sidebarLimit)
      ->get();

  $sidebarTotalActors = \Illuminate\Support\Facades\DB::table('object_term_relation')
      ->join('object', 'object_term_relation.object_id', '=', 'object.id')
      ->whereIn('object_term_relation.term_id', $sidebarTermIds)
      ->where('object.class_name', 'QubitActor')
      ->distinct()->count('object_term_relation.object_id');
@endphp

@if($sidebarTotalActors > 0)
  <div class="card mb-3" id="term-related-authorities-sidebar">
    <div class="card-header py-2">
      <strong>{{ __('Related authority records') }}</strong>
      <span class="badge atom-badge-secondary ms-1">{{ $sidebarTotalActors }}</span>
    </div>
    <ul class="list-group list-group-flush">
      @foreach($sidebarActors as $a)
        <li class="list-group-item py-2">
          <a href="{{ route('actor.show', $a->slug) }}">{{ $a->name ?? '(untitled)' }}</a>
        </li>
      @endforeach
      @if($sidebarTotalActors > $sidebarLimit)
        <li class="list-group-item py-2 text-end">
          <a href="{{ route('term.relatedAuthorities', $term->slug) }}" class="small">
            {{ __('Show all') }} ({{ $sidebarTotalActors }})
            <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
          </a>
        </li>
      @endif
    </ul>
  </div>
@endif
