{{--
  RiC entity-type pill — renders the preferred label for a rico:* type in the
  current culture, resolved via VocabularyResolverService against the RiC-O
  ontology loaded into Fuseki (issue #36 Phase 1+2). Falls back to the URI
  fragment if Fuseki is offline or the cache is empty.

  Usage:
    @include('ahg-ric::components.type-pill', ['type' => 'RecordResource'])
    @include('ahg-ric::components.type-pill', ['type' => 'Activity', 'qualifier' => 'Loan'])

  The optional `qualifier` is a domain-specific clarification (e.g. "Loan" for
  rico:Activity in the loan view). It's wrapped in __() so it translates via
  the standard lang/*.json layer.
--}}
@php
    $ricoUri = 'https://www.ica.org/standards/RiC/ontology#' . ltrim($type, '#');
    $localised = app(\AhgCore\Services\VocabularyResolverService::class)
        ->preferredLabel($ricoUri, app()->getLocale());
@endphp
<span class="ric-type-pill" style="background:#fff !important;color:#198754 !important;border:2px solid #198754;padding:.25em .6em;border-radius:.375em;font-size:.85em;font-weight:600;display:inline-block;">
    rico:{{ $localised }}@if(!empty($qualifier ?? null)) <span class="text-muted fw-normal">({{ __($qualifier) }})</span>@endif
</span>
