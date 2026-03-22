@extends('theme::layouts.print')

@section('title', ($io->title ?? 'Archival description'))
@section('record-title', $io->title ?? '[Untitled]')
@section('record-type')
  @if($levelName) {{ $levelName }} @endif
  @if($io->identifier) &mdash; {{ $io->identifier }} @endif
@endsection

@section('content')

  {{-- Breadcrumb / hierarchy context --}}
  @if(!empty($breadcrumbs))
    <div style="font-size: 10pt; color: #666; margin-bottom: 12px;">
      <strong>Part of:</strong>
      @foreach($breadcrumbs as $bc)
        {{ $bc->title ?? '[Untitled]' }}@if(!$loop->last) &raquo; @endif
      @endforeach
    </div>
  @endif

  {{-- ===== 1. Identity area ===== --}}
  <h2 class="section-heading">Identity area</h2>

  @if($io->identifier)
    <div class="field-row">
      <div class="field-label">Reference code</div>
      <div class="field-value">{{ $io->identifier }}</div>
    </div>
  @endif

  @if($io->title)
    <div class="field-row">
      <div class="field-label">Title</div>
      <div class="field-value">{{ $io->title }}</div>
    </div>
  @endif

  @if(isset($events) && $events->isNotEmpty())
    <div class="field-row">
      <div class="field-label">Date(s)</div>
      <div class="field-value">
        <ul>
          @foreach($events as $event)
            <li>
              {{ $event->date_display ?? '' }}
              @if($event->start_date || $event->end_date)
                @if(!$event->date_display)({{ $event->start_date ?? '?' }} - {{ $event->end_date ?? '?' }})@endif
              @endif
              @if($event->type_id && isset($eventTypeNames[$event->type_id]))
                ({{ $eventTypeNames[$event->type_id] }})
              @endif
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif

  @if($levelName)
    <div class="field-row">
      <div class="field-label">Level of description</div>
      <div class="field-value">{{ $levelName }}</div>
    </div>
  @endif

  @if($io->extent_and_medium)
    <div class="field-row">
      <div class="field-label">Extent and medium</div>
      <div class="field-value">{!! nl2br(e($io->extent_and_medium)) !!}</div>
    </div>
  @endif

  {{-- ===== 2. Context area ===== --}}
  <h2 class="section-heading">Context area</h2>

  @if(isset($creators) && $creators->isNotEmpty())
    @foreach($creators as $creator)
      <div class="field-row">
        <div class="field-label">Name of creator(s)</div>
        <div class="field-value">{{ $creator->name }}</div>
      </div>

      @if($creator->dates_of_existence)
        <div class="field-row">
          <div class="field-label">Dates of existence</div>
          <div class="field-value">{{ $creator->dates_of_existence }}</div>
        </div>
      @endif

      @if($creator->history)
        <div class="field-row">
          <div class="field-label">
            @if(isset($creator->entity_type_id) && $creator->entity_type_id == 131)
              Administrative history
            @else
              Biographical history
            @endif
          </div>
          <div class="field-value">{!! nl2br(e($creator->history)) !!}</div>
        </div>
      @endif
    @endforeach
  @endif

  @if(isset($functionRelations) && (is_countable($functionRelations) ? count($functionRelations) > 0 : !empty($functionRelations)))
    @foreach($functionRelations as $item)
      <div class="field-row">
        <div class="field-label">Related function</div>
        <div class="field-value">{{ $item->name ?? $item->title ?? '[Untitled]' }}</div>
      </div>
    @endforeach
  @endif

  @if($repository)
    <div class="field-row">
      <div class="field-label">Repository</div>
      <div class="field-value">{{ $repository->name }}</div>
    </div>
  @endif

  @if($io->archival_history)
    <div class="field-row">
      <div class="field-label">Archival history</div>
      <div class="field-value">{!! nl2br(e($io->archival_history)) !!}</div>
    </div>
  @endif

  @if($io->acquisition)
    <div class="field-row">
      <div class="field-label">Immediate source of acquisition or transfer</div>
      <div class="field-value">{!! nl2br(e($io->acquisition)) !!}</div>
    </div>
  @endif

  {{-- ===== 3. Content and structure area ===== --}}
  <h2 class="section-heading">Content and structure area</h2>

  @if($io->scope_and_content)
    <div class="field-row">
      <div class="field-label">Scope and content</div>
      <div class="field-value">{!! nl2br(e($io->scope_and_content)) !!}</div>
    </div>
  @endif

  @if($io->appraisal)
    <div class="field-row">
      <div class="field-label">Appraisal, destruction and scheduling</div>
      <div class="field-value">{!! nl2br(e($io->appraisal)) !!}</div>
    </div>
  @endif

  @if($io->accruals)
    <div class="field-row">
      <div class="field-label">Accruals</div>
      <div class="field-value">{!! nl2br(e($io->accruals)) !!}</div>
    </div>
  @endif

  @if($io->arrangement)
    <div class="field-row">
      <div class="field-label">System of arrangement</div>
      <div class="field-value">{!! nl2br(e($io->arrangement)) !!}</div>
    </div>
  @endif

  {{-- ===== 4. Conditions of access and use area ===== --}}
  <h2 class="section-heading">Conditions of access and use area</h2>

  @if($io->access_conditions)
    <div class="field-row">
      <div class="field-label">Conditions governing access</div>
      <div class="field-value">{!! nl2br(e($io->access_conditions)) !!}</div>
    </div>
  @endif

  @if($io->reproduction_conditions)
    <div class="field-row">
      <div class="field-label">Conditions governing reproduction</div>
      <div class="field-value">{!! nl2br(e($io->reproduction_conditions)) !!}</div>
    </div>
  @endif

  @if(isset($languages) && $languages->isNotEmpty())
    <div class="field-row">
      <div class="field-label">Language of material</div>
      <div class="field-value">
        @foreach($languages as $lang)
          {{ $lang->name }}@if(!$loop->last), @endif
        @endforeach
      </div>
    </div>
  @endif

  @if(isset($materialScripts) && (is_countable($materialScripts) ? count($materialScripts) > 0 : !empty($materialScripts)))
    <div class="field-row">
      <div class="field-label">Script of material</div>
      <div class="field-value">
        @foreach($materialScripts as $script)
          {{ $script }}@if(!$loop->last), @endif
        @endforeach
      </div>
    </div>
  @endif

  @if(isset($notes) && $notes->isNotEmpty())
    @foreach($notes->where('type_id', 174) as $lnote)
      <div class="field-row">
        <div class="field-label">Language and script notes</div>
        <div class="field-value">{!! nl2br(e($lnote->content)) !!}</div>
      </div>
    @endforeach
  @endif

  @if($io->physical_characteristics)
    <div class="field-row">
      <div class="field-label">Physical characteristics and technical requirements</div>
      <div class="field-value">{!! nl2br(e($io->physical_characteristics)) !!}</div>
    </div>
  @endif

  @if($io->finding_aids)
    <div class="field-row">
      <div class="field-label">Finding aids</div>
      <div class="field-value">{!! nl2br(e($io->finding_aids)) !!}</div>
    </div>
  @endif

  {{-- ===== 5. Allied materials area ===== --}}
  <h2 class="section-heading">Allied materials area</h2>

  @if($io->location_of_originals)
    <div class="field-row">
      <div class="field-label">Existence and location of originals</div>
      <div class="field-value">{!! nl2br(e($io->location_of_originals)) !!}</div>
    </div>
  @endif

  @if($io->location_of_copies)
    <div class="field-row">
      <div class="field-label">Existence and location of copies</div>
      <div class="field-value">{!! nl2br(e($io->location_of_copies)) !!}</div>
    </div>
  @endif

  @if($io->related_units_of_description)
    <div class="field-row">
      <div class="field-label">Related units of description</div>
      <div class="field-value">{!! nl2br(e($io->related_units_of_description)) !!}</div>
    </div>
  @endif

  @if(isset($notes) && $notes->isNotEmpty())
    @foreach($notes->where('type_id', 141) as $note)
      <div class="field-row">
        <div class="field-label">Publication note</div>
        <div class="field-value">{!! nl2br(e($note->content)) !!}</div>
      </div>
    @endforeach
  @endif

  {{-- ===== 6. Notes area ===== --}}
  <h2 class="section-heading">Notes area</h2>

  @if(isset($notes) && $notes->isNotEmpty())
    @foreach($notes->where('type_id', 137) as $note)
      <div class="field-row">
        <div class="field-label">Note</div>
        <div class="field-value">{!! nl2br(e($note->content)) !!}</div>
      </div>
    @endforeach
  @endif

  @if(isset($alternativeIdentifiers) && (is_countable($alternativeIdentifiers) ? count($alternativeIdentifiers) > 0 : !empty($alternativeIdentifiers)))
    @foreach($alternativeIdentifiers as $altId)
      <div class="field-row">
        <div class="field-label">{{ $altId->label ?? 'Alternative identifier' }}</div>
        <div class="field-value">{{ $altId->value ?? $altId->name ?? '' }}</div>
      </div>
    @endforeach
  @endif

  {{-- ===== 7. Access points ===== --}}
  <h2 class="section-heading">Access points</h2>

  @if(isset($subjects) && $subjects->isNotEmpty())
    <div class="field-row">
      <div class="field-label">Subject access points</div>
      <div class="field-value">
        @foreach($subjects as $subject)
          {{ $subject->name }}@if(!$loop->last), @endif
        @endforeach
      </div>
    </div>
  @endif

  @if(isset($places) && $places->isNotEmpty())
    <div class="field-row">
      <div class="field-label">Place access points</div>
      <div class="field-value">
        @foreach($places as $place)
          {{ $place->name }}@if(!$loop->last), @endif
        @endforeach
      </div>
    </div>
  @endif

  @if(isset($nameAccessPoints) && $nameAccessPoints->isNotEmpty())
    <div class="field-row">
      <div class="field-label">Name access points</div>
      <div class="field-value">
        @foreach($nameAccessPoints as $nap)
          {{ $nap->name }}@if(!$loop->last), @endif
        @endforeach
      </div>
    </div>
  @endif

  @if(isset($genres) && $genres->isNotEmpty())
    <div class="field-row">
      <div class="field-label">Genre access points</div>
      <div class="field-value">
        @foreach($genres as $genre)
          {{ $genre->name }}@if(!$loop->last), @endif
        @endforeach
      </div>
    </div>
  @endif

  {{-- ===== 8. Description control area ===== --}}
  <h2 class="section-heading">Description control area</h2>

  @if($io->description_identifier ?? null)
    <div class="field-row">
      <div class="field-label">Description identifier</div>
      <div class="field-value">{{ $io->description_identifier }}</div>
    </div>
  @endif

  @if($io->institution_responsible_identifier ?? null)
    <div class="field-row">
      <div class="field-label">Institution identifier</div>
      <div class="field-value">{{ $io->institution_responsible_identifier }}</div>
    </div>
  @endif

  @if($io->rules ?? null)
    <div class="field-row">
      <div class="field-label">Rules and/or conventions used</div>
      <div class="field-value">{!! nl2br(e($io->rules)) !!}</div>
    </div>
  @endif

  @if(isset($descriptionStatusName) && $descriptionStatusName)
    <div class="field-row">
      <div class="field-label">Status</div>
      <div class="field-value">{{ $descriptionStatusName }}</div>
    </div>
  @endif

  @if(isset($descriptionDetailName) && $descriptionDetailName)
    <div class="field-row">
      <div class="field-label">Level of detail</div>
      <div class="field-value">{{ $descriptionDetailName }}</div>
    </div>
  @endif

  @if($io->revision_history ?? null)
    <div class="field-row">
      <div class="field-label">Dates of creation, revision and deletion</div>
      <div class="field-value">{!! nl2br(e($io->revision_history)) !!}</div>
    </div>
  @endif

  @if(isset($languagesOfDescription) && (is_countable($languagesOfDescription) ? count($languagesOfDescription) > 0 : !empty($languagesOfDescription)))
    <div class="field-row">
      <div class="field-label">Language(s)</div>
      <div class="field-value">
        @foreach($languagesOfDescription as $lang)
          {{ $lang }}@if(!$loop->last), @endif
        @endforeach
      </div>
    </div>
  @endif

  @if(isset($scriptsOfDescription) && (is_countable($scriptsOfDescription) ? count($scriptsOfDescription) > 0 : !empty($scriptsOfDescription)))
    <div class="field-row">
      <div class="field-label">Script(s)</div>
      <div class="field-value">
        @foreach($scriptsOfDescription as $script)
          {{ $script }}@if(!$loop->last), @endif
        @endforeach
      </div>
    </div>
  @endif

  @if($io->sources ?? null)
    <div class="field-row">
      <div class="field-label">Sources</div>
      <div class="field-value">{!! nl2br(e($io->sources)) !!}</div>
    </div>
  @endif

  @if(isset($notes) && $notes->isNotEmpty())
    @foreach($notes->where('type_id', 142) as $note)
      <div class="field-row">
        <div class="field-label">Archivist's note</div>
        <div class="field-value">{!! nl2br(e($note->content)) !!}</div>
      </div>
    @endforeach
  @endif

  {{-- ===== 9. Rights area ===== --}}
  @if(isset($rights) && (is_countable($rights) ? count($rights) > 0 : !empty($rights)))
    <h2 class="section-heading">Rights area</h2>
    @foreach($rights as $right)
      <div class="field-row">
        <div class="field-label">{{ $right->basis ?? 'Right' }}</div>
        <div class="field-value">
          @if(isset($right->act)){{ $right->act }}@endif
          @if(isset($right->start_date) || isset($right->end_date))
            ({{ $right->start_date ?? '?' }} - {{ $right->end_date ?? '?' }})
          @endif
          @if(isset($right->rights_note))
            <br>{!! nl2br(e($right->rights_note)) !!}
          @endif
        </div>
      </div>
    @endforeach
  @endif

  {{-- ===== 10. Accession area ===== --}}
  @if(isset($accessions) && (is_countable($accessions) ? count($accessions) > 0 : !empty($accessions)))
    <h2 class="section-heading">Accession area</h2>
    @foreach($accessions as $accession)
      <div class="field-row">
        <div class="field-label">Accession</div>
        <div class="field-value">{{ $accession->identifier ?? $accession->name ?? '[Untitled]' }}</div>
      </div>
    @endforeach
  @endif

  {{-- ===== 11. Physical storage ===== --}}
  @if(isset($physicalObjects) && (is_countable($physicalObjects) ? count($physicalObjects) > 0 : !empty($physicalObjects)))
    <h2 class="section-heading">Physical storage</h2>
    @foreach($physicalObjects as $pobj)
      <div class="field-row">
        <div class="field-label">
          @if(isset($physicalObjectTypeNames[$pobj->type_id ?? null]))
            {{ $physicalObjectTypeNames[$pobj->type_id] }}
          @else
            Container
          @endif
        </div>
        <div class="field-value">{{ $pobj->name ?? $pobj->location ?? '[Unknown]' }}</div>
      </div>
    @endforeach
  @endif

  {{-- ===== 12. Publication status ===== --}}
  @if(isset($publicationStatus) && $publicationStatus)
    <h2 class="section-heading">Administration</h2>
    <div class="field-row">
      <div class="field-label">Publication status</div>
      <div class="field-value">{{ $publicationStatus }}</div>
    </div>
  @endif

  {{-- ===== 13. Child descriptions ===== --}}
  @if(isset($children) && $children->isNotEmpty())
    <h2 class="section-heading">Child descriptions ({{ $children->count() }})</h2>
    <table>
      <thead>
        <tr style="background:var(--ahg-primary);color:#fff">
          <th>Title</th>
          <th style="width: 150px;">Level</th>
        </tr>
      </thead>
      <tbody>
        @foreach($children as $child)
          <tr>
            <td>{{ $child->title ?? '[Untitled]' }}</td>
            <td>{{ $childLevelNames[$child->level_of_description_id] ?? '' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

@endsection
