@extends('theme::layouts.print')

@section('title', $actor->authorized_form_of_name ?? 'Authority record')
@section('record-title', $actor->authorized_form_of_name ?? '[Untitled]')
@section('record-type')
  @if($entityTypeName) {{ $entityTypeName }} @endif
  @if($actor->description_identifier) &mdash; {{ $actor->description_identifier }} @endif
@endsection

@section('content')

  {{-- ===== Identity area ===== --}}
  <h2 class="section-heading">Identity area</h2>

  @if($entityTypeName)
    <div class="field-row">
      <div class="field-label">Type of entity</div>
      <div class="field-value">{{ $entityTypeName }}</div>
    </div>
  @endif

  @if($actor->authorized_form_of_name)
    <div class="field-row">
      <div class="field-label">Authorized form of name</div>
      <div class="field-value">{{ $actor->authorized_form_of_name }}</div>
    </div>
  @endif

  @if($otherNames->isNotEmpty())
    @foreach([148 => 'Parallel form(s) of name', 165 => 'Standardized form(s) of name', 149 => 'Other form(s) of name'] as $typeId => $label)
      @php $filtered = $otherNames->where('type_id', $typeId); @endphp
      @if($filtered->isNotEmpty())
        <div class="field-row">
          <div class="field-label">{{ $label }}</div>
          <div class="field-value">
            @foreach($filtered as $name)
              {{ $name->name }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @endif
    @endforeach

    @php $untyped = $otherNames->whereNotIn('type_id', [148, 149, 165]); @endphp
    @if($untyped->isNotEmpty())
      <div class="field-row">
        <div class="field-label">Other name(s)</div>
        <div class="field-value">
          @foreach($untyped as $name)
            {{ $name->name }}
            @if(!empty($name->type_id) && isset($nameTypeNames[$name->type_id]))
              ({{ $nameTypeNames[$name->type_id] }})
            @endif
            @if(!$loop->last), @endif
          @endforeach
        </div>
      </div>
    @endif
  @endif

  @if($actor->corporate_body_identifiers)
    <div class="field-row">
      <div class="field-label">Identifiers for corporate bodies</div>
      <div class="field-value">{{ $actor->corporate_body_identifiers }}</div>
    </div>
  @endif

  @if($actor->description_identifier)
    <div class="field-row">
      <div class="field-label">Identifier</div>
      <div class="field-value">{{ $actor->description_identifier }}</div>
    </div>
  @endif

  {{-- ===== Description area ===== --}}
  @php
    $descFields = [
      'dates_of_existence' => 'Dates of existence',
      'history' => 'History',
      'places' => 'Places',
      'legal_status' => 'Legal status',
      'functions' => 'Functions, occupations and activities',
      'mandates' => 'Mandates/Sources of authority',
      'internal_structures' => 'Internal structures/genealogy',
      'general_context' => 'General context',
    ];
    $hasDesc = false;
    foreach ($descFields as $f => $l) { if ($actor->$f) { $hasDesc = true; break; } }
  @endphp
  @if($hasDesc)
    <h2 class="section-heading">Description area</h2>
    @foreach($descFields as $field => $label)
      @if($actor->$field)
        <div class="field-row">
          <div class="field-label">{{ $label }}</div>
          <div class="field-value">{!! nl2br(e($actor->$field)) !!}</div>
        </div>
      @endif
    @endforeach
  @endif

  {{-- ===== Events (dates) ===== --}}
  @if($events->isNotEmpty())
    <h2 class="section-heading">Dates</h2>
    @foreach($events as $event)
      <div class="field-row">
        <div class="field-label">{{ $event->event_name ?? 'Date' }}</div>
        <div class="field-value">
          {{ $event->date_display ?? '' }}
          @if($event->start_date || $event->end_date)
            ({{ $event->start_date ?? '?' }} - {{ $event->end_date ?? '?' }})
          @endif
        </div>
      </div>
    @endforeach
  @endif

  {{-- ===== Contact information ===== --}}
  @if($contacts->isNotEmpty())
    <h2 class="section-heading">Contact information</h2>
    @foreach($contacts as $contact)
      <div class="contact-block">
        @if($contact->primary_contact)
          <div><strong>[Primary contact]</strong></div>
        @endif
        @if($contact->contact_person) <div><strong>Contact:</strong> {{ $contact->contact_person }}</div> @endif
        @if($contact->contact_type) <div><strong>Type:</strong> {{ $contact->contact_type }}</div> @endif
        @if($contact->street_address) <div>{{ $contact->street_address }}</div> @endif
        @if($contact->city || $contact->region || $contact->postal_code)
          <div>{{ $contact->city ?? '' }}{{ $contact->region ? ', ' . $contact->region : '' }} {{ $contact->postal_code ?? '' }}</div>
        @endif
        @if($contact->country_code) <div>{{ $contact->country_code }}</div> @endif
        @if($contact->telephone) <div><strong>Tel:</strong> {{ $contact->telephone }}</div> @endif
        @if($contact->fax) <div><strong>Fax:</strong> {{ $contact->fax }}</div> @endif
        @if($contact->email) <div><strong>Email:</strong> {{ $contact->email }}</div> @endif
        @if($contact->website) <div><strong>Web:</strong> {{ $contact->website }}</div> @endif
        @if($contact->note) <div><em>{{ $contact->note }}</em></div> @endif
      </div>
    @endforeach
  @endif

  {{-- ===== Access points ===== --}}
  @if(($subjects ?? collect())->isNotEmpty() || ($places ?? collect())->isNotEmpty() || ($occupations ?? collect())->isNotEmpty())
    <h2 class="section-heading">Access points</h2>

    @if($subjects->isNotEmpty())
      <div class="field-row">
        <div class="field-label">Subject access points</div>
        <div class="field-value">
          @foreach($subjects as $subject)
            {{ $subject->name }}@if(!$loop->last), @endif
          @endforeach
        </div>
      </div>
    @endif

    @if($places->isNotEmpty())
      <div class="field-row">
        <div class="field-label">Place access points</div>
        <div class="field-value">
          @foreach($places as $place)
            {{ $place->name }}@if(!$loop->last), @endif
          @endforeach
        </div>
      </div>
    @endif

    @if($occupations->isNotEmpty())
      <div class="field-row">
        <div class="field-label">Occupations</div>
        <div class="field-value">
          @foreach($occupations as $occ)
            {{ $occ->name }}@if(!$loop->last), @endif
          @endforeach
        </div>
      </div>
    @endif
  @endif

  {{-- ===== Relationships ===== --}}
  @if(count($relatedActors) > 0)
    <h2 class="section-heading">Related authority records</h2>
    <table>
      <thead>
        <tr style="background:var(--ahg-primary);color:#fff">
          <th>Name</th>
          <th>Identifier</th>
          <th>Category</th>
          <th>Relationship type</th>
          <th>Dates</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        @foreach($relatedActors as $related)
          <tr>
            <td>{{ $related->name ?: '[Untitled]' }}</td>
            <td>{{ $related->identifier ?? '' }}</td>
            <td>{{ (!empty($related->type_id) && isset($relationCategoryNames[$related->type_id])) ? $relationCategoryNames[$related->type_id] : '' }}</td>
            <td>{{ (!empty($related->type_id) && isset($relationTypeNames[$related->type_id])) ? $relationTypeNames[$related->type_id] : '' }}</td>
            <td>{{ $related->relation_date ?? (($related->start_date || $related->end_date) ? ($related->start_date ?? '?') . ' - ' . ($related->end_date ?? '?') : '') }}</td>
            <td>{{ $related->relation_description ?? '' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  @if($relatedResources->isNotEmpty())
    <h2 class="section-heading">Related descriptions</h2>
    <ul>
      @foreach($relatedResources as $resource)
        <li>{{ $resource->title ?: '[Untitled]' }}</li>
      @endforeach
    </ul>
  @endif

  @if($relatedFunctions->isNotEmpty())
    <h2 class="section-heading">Related functions</h2>
    <ul>
      @foreach($relatedFunctions as $fn)
        <li>{{ $fn->name ?: '[Untitled]' }}</li>
      @endforeach
    </ul>
  @endif

  {{-- ===== Control area ===== --}}
  <h2 class="section-heading">Control area</h2>

  @if($actor->description_identifier)
    <div class="field-row">
      <div class="field-label">Authority record identifier</div>
      <div class="field-value">{{ $actor->description_identifier }}</div>
    </div>
  @endif

  @if($actor->institution_responsible_identifier)
    <div class="field-row">
      <div class="field-label">Institution identifier</div>
      <div class="field-value">{{ $actor->institution_responsible_identifier }}</div>
    </div>
  @endif

  @if($actor->rules)
    <div class="field-row">
      <div class="field-label">Rules and/or conventions</div>
      <div class="field-value">{!! nl2br(e($actor->rules)) !!}</div>
    </div>
  @endif

  @if($descriptionStatusName)
    <div class="field-row">
      <div class="field-label">Status</div>
      <div class="field-value">{{ $descriptionStatusName }}</div>
    </div>
  @endif

  @if($descriptionDetailName)
    <div class="field-row">
      <div class="field-label">Level of detail</div>
      <div class="field-value">{{ $descriptionDetailName }}</div>
    </div>
  @endif

  @if($actor->revision_history)
    <div class="field-row">
      <div class="field-label">Dates of creation, revision and deletion</div>
      <div class="field-value">{!! nl2br(e($actor->revision_history)) !!}</div>
    </div>
  @endif

  @if($actor->sources)
    <div class="field-row">
      <div class="field-label">Sources</div>
      <div class="field-value">{!! nl2br(e($actor->sources)) !!}</div>
    </div>
  @endif

  @if(!empty($languages ?? []))
    <div class="field-row">
      <div class="field-label">Language(s)</div>
      <div class="field-value">{{ implode(', ', $languages) }}</div>
    </div>
  @endif

  @if(!empty($scripts ?? []))
    <div class="field-row">
      <div class="field-label">Script(s)</div>
      <div class="field-value">{{ implode(', ', $scripts) }}</div>
    </div>
  @endif

  @if($maintenanceNotes)
    <div class="field-row">
      <div class="field-label">Maintenance notes</div>
      <div class="field-value">{!! nl2br(e($maintenanceNotes)) !!}</div>
    </div>
  @endif

  @if($maintainingRepository ?? null)
    <div class="field-row">
      <div class="field-label">Maintained by</div>
      <div class="field-value">{{ $maintainingRepository->name ?: '[Untitled]' }}</div>
    </div>
  @endif

  @if($actor->updated_at)
    <div class="field-row">
      <div class="field-label">Last updated</div>
      <div class="field-value">{{ $actor->updated_at }}</div>
    </div>
  @endif

@endsection
