{{-- ISAD(G) — Notes area (#98 Phase 1: extracted as a partial so DACS / RAD / MODS
     templates can include or omit it). Inherits $io / $notes / $alternativeIdentifiers
     from the parent show template's scope (Laravel @include inherits parent vars). --}}
@if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_notes_area'))
<section id="notesArea" class="border-bottom">
  <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <a class="text-decoration-none text-white" href="#notes-collapse">
      {{ __('Notes area') }}
    </a>
    @auth
      <a href="{{ route('informationobject.edit', $io->slug) }}#notes-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Notes area') }}">
        <i class="fas fa-pencil-alt"></i>
      </a>
    @endauth
  </h2>
  <div id="notes-collapse">

    {{-- General notes (type_id = 125) --}}
    @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_notes'))
    @if(isset($notes) && $notes->isNotEmpty())
      @foreach($notes->where('type_id', 125) as $note)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Note') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($note->content)) !!}</div>
        </div>
      @endforeach
    @endif
    @endif {{-- end isad_notes visibility --}}

    {{-- Alternative identifiers --}}
    @if(isset($alternativeIdentifiers) && (is_countable($alternativeIdentifiers) ? count($alternativeIdentifiers) > 0 : !empty($alternativeIdentifiers)))
      @foreach($alternativeIdentifiers as $altId)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">
            {{ $altId->label ?? 'Alternative identifier' }}
          </h3>
          <div class="col-9 p-2">{{ $altId->value ?? $altId->name ?? '' }}</div>
        </div>
      @endforeach
    @endif

  </div>
</section>
@endif {{-- end isad_notes_area visibility --}}
