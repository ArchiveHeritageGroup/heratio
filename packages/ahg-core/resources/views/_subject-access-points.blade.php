@php
use Illuminate\Support\Facades\DB;

$resourceId = is_object($resource) ? ($resource->id ?? null) : $resource;
if (!$resourceId) { return; }

$culture = app()->getLocale();
$subjectTaxonomyId = 35;

$subjects = DB::table('object_term_relation as otr')
    ->join('term as t', 'otr.term_id', '=', 't.id')
    ->leftJoin('term_i18n as ti', function ($join) use ($culture) {
        $join->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
    })
    ->leftJoin('term_i18n as ti_en', function ($join) {
        $join->on('t.id', '=', 'ti_en.id')->where('ti_en.culture', '=', 'en');
    })
    ->leftJoin('slug', 't.id', '=', 'slug.object_id')
    ->where('otr.object_id', $resourceId)
    ->where('t.taxonomy_id', $subjectTaxonomyId)
    ->select(['t.id', 't.code', 'slug.slug', DB::raw('COALESCE(ti.name, ti_en.name) as name')])
    ->orderBy(DB::raw('COALESCE(ti.name, ti_en.name)'))
    ->get()->toArray();

if (empty($subjects)) { return; }

// LCSH/LCNAF pass: when term.code is an id.loc.gov URI, resolve the localised
// prefLabel through VocabularyResolverService (vocabulary_label_cache; populated
// by ahg:vocabulary-mirror + ahg:vocabulary-import). Falls through to the
// locally-stored term_i18n name when no cache row exists for this culture.
$lcshUris = [];
foreach ($subjects as $s) {
    if (!empty($s->code) && preg_match('#^https?://id\.loc\.gov/#i', (string) $s->code)) {
        $lcshUris[] = $s->code;
    }
}
$resolved = [];
if (!empty($lcshUris) && class_exists(\AhgCore\Services\VocabularyResolverService::class)) {
    try {
        $resolver = app(\AhgCore\Services\VocabularyResolverService::class);
        $resolved = $resolver->resolveMany($lcshUris, $culture);
    } catch (\Throwable $e) {
        $resolved = [];
    }
}
foreach ($subjects as $s) {
    $s->lcsh = !empty($s->code) && isset($resolved[$s->code]) && $resolved[$s->code] !== '';
    if ($s->lcsh) {
        $s->name = $resolved[$s->code];
    }
}

$isSidebar = isset($sidebar) && $sidebar;
@endphp

@if($isSidebar)
  <section id="subjectAccessPointsSection">
    <h4>{{ __('Subject access points') }}</h4>
    <ul class="list-unstyled">
      @foreach($subjects as $subject)
        <li>
          @if($subject->slug)
            <a href="{{ route('term.show', $subject->slug) }}">{{ $subject->name ?? '' }}</a>
          @else
            {{ $subject->name ?? '' }}
          @endif
          @if(!empty($subject->lcsh))
            <a href="{{ $subject->code }}" target="_blank" rel="noopener"
               class="badge bg-light text-dark border ms-1"
               title="{{ __('Resolved from Library of Congress Subject Headings') }}: {{ $subject->code }}">LCSH</a>
          @endif
        </li>
      @endforeach
    </ul>
  </section>
@else
<div class="field">

  <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Subject access points') }}</h3>

  <div>
    <ul class="list-unstyled">
      @foreach($subjects as $subject)
        <li>
          @if($subject->slug)
            <a href="{{ route('term.show', $subject->slug) }}">{{ $subject->name ?? '' }}</a>
          @else
            {{ $subject->name ?? '' }}
          @endif
          @if(!empty($subject->lcsh))
            <a href="{{ $subject->code }}" target="_blank" rel="noopener"
               class="badge bg-light text-dark border ms-1"
               title="{{ __('Resolved from Library of Congress Subject Headings') }}: {{ $subject->code }}">LCSH</a>
          @endif
        </li>
      @endforeach
    </ul>
  </div>

</div>
@endif
