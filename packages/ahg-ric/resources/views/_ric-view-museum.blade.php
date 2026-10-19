{{--
  RiC View: Museum object - the same catalogue record expressed in RiC-CM terms:
  the object as a Record Resource, its Production activity, custody, location,
  rules and context. Field names follow MuseumService::getBySlug() (museum_metadata
  joined to the information object); the earlier view read material,
  date_created, place_of_origin and accession_number, which do not exist, so it
  showed almost nothing (Oct 2026).

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@php
  $culture = app()->getLocale();

  $creators = collect();
  if (isset($museum->id)) {
      $creators = \Illuminate\Support\Facades\DB::table('relation as r')
          ->join('actor_i18n as ai', function ($j) use ($museum, $culture) {
              $j->on(DB::raw("CASE WHEN r.subject_id = {$museum->id} THEN r.object_id ELSE r.subject_id END"), '=', 'ai.id')
                ->where('ai.culture', $culture);
          })
          ->leftJoin('slug', 'slug.object_id', '=', 'ai.id')
          ->where(function ($q) use ($museum) {
              $q->where('r.subject_id', $museum->id)->orWhere('r.object_id', $museum->id);
          })
          ->whereNotNull('ai.authorized_form_of_name')
          ->select('ai.authorized_form_of_name as name', 'slug.slug')
          ->limit(20)
          ->get();
  }

  $v = fn (string $k) => trim((string) ($museum->{$k} ?? ''));
  $level = ! empty($museum->level_of_description_id)
      ? (string) \Illuminate\Support\Facades\DB::table('term_i18n')->where('id', $museum->level_of_description_id)
          ->whereIn('culture', [$culture, 'en'])->orderByRaw('culture = ? desc', [$culture])->value('name')
      : '';
  $master = isset($museum->id)
      ? \Illuminate\Support\Facades\DB::table('digital_object')->where('object_id', $museum->id)->whereNull('parent_id')->first(['name', 'mime_type', 'byte_size'])
      : null;
  $instantiation = $master
      ? trim($master->name.' ('.$master->mime_type.($master->byte_size ? ', '.number_format($master->byte_size / 1024, 1).' KB' : '').')')
      : '';
  $join = fn (array $parts, string $sep = '; ') => implode($sep, array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
  $dateRange = $join([$v('creation_date_earliest'), $v('creation_date_latest')], ' - ');
  $date = $v('creation_date_display') !== '' ? $v('creation_date_display') : $dateRange;

  // [RiC-O 1.0.2 property, plain label, value]; empty values are not shown. Every
  // property name is checked against the ontology by RicMuseumViewTest.
  $sections = [
      [__('Record Resource'), 'fa-landmark', [
          ['rico:title', __('Title'), $v('title')],
          ['rico:identifier', __('Identifier'), $v('identifier')],
          ['rico:hasOrHadCategory', __('Object type'), $join([$v('object_type'), $v('work_type')], ' / ')],
          ['rico:hasDocumentaryFormType', __('Level of description'), $level],
          ['rico:hasOrHadCategory', __('Classification'), $join([$v('classification'), $v('object_class'), $v('object_category')])],
          ['rico:scopeAndContent', __('Description'), $v('scope_and_content')],
          ['rico:physicalCharacteristicsNote', __('Physical characteristics'), $join([$v('physical_characteristics'), $v('materials')])],
          ['rico:hasProductionTechniqueType', __('Techniques'), $v('techniques')],
          ['rico:hasExtent', __('Extent'), $join([$v('measurements'), $v('dimensions')])],
          ['rico:hasOrHadInstantiation', __('Digital object'), $instantiation],
      ]],
      [__('Production (Activity)'), 'fa-hammer', [
          ['rico:hasOrHadParticipant', __('Creator'), $join([$v('creator_identity'), $v('creator_role') !== '' ? '('.$v('creator_role').')' : ''], ' ')],
          ['rico:hasCreator', __('Attribution'), $v('creator_attribution')],
          ['rico:expressedDate', __('Date'), $date],
          ['rico:hasOrHadLocation', __('Place of production'), $v('creation_place')],
      ]],
      [__('Custody and provenance'), 'fa-route', [
          ['rico:history', __('Provenance'), $join([$v('provenance'), $v('provenance_text')], ' ')],
          ['rico:hasOrHadHolder', __('Ownership history'), $v('ownership_history')],
          ['rico:hasOrHadLocation', __('Current location'), $join([$v('current_location'), $v('current_location_repository'), $v('current_location_geography')], ', ')],
      ]],
      [__('Rules and condition'), 'fa-scale-balanced', [
          ['rico:isOrWasRegulatedBy', __('Rights'), $join([$v('rights_type'), $v('rights_holder')], ' - ')],
          ['rico:isOrWasRegulatedBy', __('Legal status'), $v('legal_status')],
          ['rico:physicalCharacteristicsNote', __('Condition'), $join([$v('condition_term'), $v('condition_date'), $v('condition_notes')], ' - ')],
      ]],
      [__('Context'), 'fa-globe', [
          ['rico:hasOrHadSubject', __('Style and period'), $join([$v('style'), $v('style_period'), $v('period')])],
          ['rico:hasOrHadSubject', __('Cultural context'), $join([$v('cultural_context'), $v('cultural_group')])],
          ['rico:hasOrHadSubject', __('Subjects'), $v('subject_display')],
          ['rico:history', __('Historical context'), $v('historical_context')],
      ]],
      [__('Description activity'), 'fa-pen-to-square', [
          ['rico:isOrWasDescribedBy', __('Catalogued by'), $join([$v('cataloger_name'), $v('cataloging_institution')], ', ')],
          ['rico:expressedDate', __('Catalogued on'), $v('cataloging_date')],
          ['rico:expressedDate', __('Record created'), $v('created_at')],
          ['rico:hasModificationDate', __('Last updated'), $v('updated_at')],
      ]],
  ];

  $museumTitle = $museum->title ?? '[Untitled object]';
@endphp

<div class="ric-view">
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div><i class="fas fa-landmark me-2"></i><strong>{{ $museumTitle }}</strong></div>
      @include('ahg-ric::components.type-pill', ['type' => 'RecordResource', 'qualifier' => 'Museum object'])
    </div>
  </div>

  @foreach($sections as [$heading, $icon, $rows])
    @php $rows = array_values(array_filter($rows, fn ($r) => $r[2] !== '')); @endphp
    @if($rows)
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas {{ $icon }} me-1"></i> {{ $heading }}</div>
        <table class="table table-sm mb-0">
          @foreach($rows as [$property, $label, $value])
            <tr>
              <th class="text-muted" style="width:35%">{{ $label }}<br><code class="small fw-normal">{{ $property }}</code></th>
              <td>{!! nl2br(e($value)) !!}</td>
            </tr>
          @endforeach
        </table>
      </div>
    @endif
  @endforeach

  @if($creators->count())
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-user-pen me-1"></i> rico:hasCreator</div>
      <div class="list-group list-group-flush">
        @foreach($creators as $c)
          <a href="{{ $c->slug ? url('/' . $c->slug) : '#' }}" class="list-group-item list-group-item-action">{{ $c->name }}</a>
        @endforeach
      </div>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-bolt me-1"></i> {{ __('Actions') }}</div>
    <div class="card-body">
      <a href="/explorer" class="btn btn-sm btn-outline-success w-100 mb-2"><i class="fas fa-project-diagram me-1"></i>{{ __('Open in Graph Explorer') }}</a>
    </div>
  </div>
</div>
