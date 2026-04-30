{{--
  AI-disclosure badge — renders next to a record field whose value was
  machine-translated and not yet human-verified. Mirrors the proposal in
  issue #36 Phase 4.

  Two ways to use, depending on whether you've already pre-loaded provenance
  for the record (recommended for show pages with many fields):

  1) Pre-loaded — controller passes $translationSources (associative array
     ['title' => 'machine', 'scope_and_content' => 'human', ...]) into the
     view. In each field row:

       @include('ahg-translation::components.badge', ['source' => $translationSources['title'] ?? null])

  2) Lazy lookup per field — costs one DB query per render. Use only on
     pages with few fields:

       @include('ahg-translation::components.badge', ['objectId' => $io->id, 'culture' => app()->getLocale(), 'field' => 'title'])

  Renders nothing for human-translated or untranslated fields.
--}}
@php
    if (! isset($source)) {
        $source = null;
        if (isset($objectId, $field)) {
            $source = \AhgTranslation\Helpers\TranslationProvenance::source(
                (int) $objectId,
                $culture ?? app()->getLocale(),
                (string) $field
            );
        }
    }
@endphp

@if($source === 'machine')
    <span class="badge bg-info text-dark ms-1 align-middle"
          style="font-size:.7rem;font-weight:500;cursor:help;"
          title="{{ __('Auto-translated by machine. A human reviewer has not yet verified this translation.') }}"
          data-bs-toggle="tooltip">
        <i class="fas fa-robot me-1" aria-hidden="true"></i>{{ __('auto-translated') }}
    </span>
@endif
