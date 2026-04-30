{{-- RiC View: Information Object — relationship-centered layout --}}
@php
  $culture = app()->getLocale();

  // RiC entity type based on level of description
  $ricTypeMap = [
    'Fonds' => 'RecordSet', 'Sub-fonds' => 'RecordSet', 'Collection' => 'RecordSet',
    'Series' => 'RecordSet', 'Sub-series' => 'RecordSet',
    'File' => 'Record', 'Item' => 'Record', 'Part' => 'RecordPart',
  ];
  $lodLabel = $io->level_of_description ?? '';
  $ricType = $ricTypeMap[$lodLabel] ?? 'RecordResource';

  // Creators via event table
  $creators = \Illuminate\Support\Facades\DB::table('event')
      ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
      ->leftJoin('slug', 'event.actor_id', '=', 'slug.object_id')
      ->where('event.object_id', $io->id)
      ->where('actor_i18n.culture', $culture)
      ->whereNotNull('actor_i18n.authorized_form_of_name')
      ->select('event.actor_id as id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug', 'event.type_id')
      ->get();

  // Parent
  $parent = null;
  if ($io->parent_id && $io->parent_id > 1) {
      $parent = \Illuminate\Support\Facades\DB::table('information_object')
          ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
          ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
          ->where('information_object.id', $io->parent_id)
          ->where('information_object_i18n.culture', $culture)
          ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
          ->first();
  }

  // Children count
  $childCount = \Illuminate\Support\Facades\DB::table('information_object')
      ->where('parent_id', $io->id)
      ->count();

  // Related records via relation table
  $relatedRecords = \Illuminate\Support\Facades\DB::table('relation')
      ->leftJoin('information_object_i18n as ioi', function ($j) use ($io, $culture) {
          $j->on(DB::raw("CASE WHEN relation.subject_id = {$io->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'ioi.id')
            ->where('ioi.culture', $culture);
      })
      ->leftJoin('slug', DB::raw("CASE WHEN relation.subject_id = {$io->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'slug.object_id')
      ->leftJoin('term_i18n as ti', function ($j) {
          $j->on('relation.type_id', '=', 'ti.id')->where('ti.culture', 'en');
      })
      ->where(function ($q) use ($io) {
          $q->where('relation.subject_id', $io->id)
            ->orWhere('relation.object_id', $io->id);
      })
      ->whereNotNull('ioi.title')
      ->select('ioi.title', 'slug.slug', 'ti.name as relation_type')
      ->limit(20)
      ->get();

  // Subjects via object_term_relation
  $subjects = \Illuminate\Support\Facades\DB::table('object_term_relation')
      ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
      ->leftJoin('slug', 'object_term_relation.term_id', '=', 'slug.object_id')
      ->where('object_term_relation.object_id', $io->id)
      ->where('term_i18n.culture', $culture)
      ->select('term_i18n.name', 'slug.slug')
      ->get();

  // Places via event/relation
  $places = \Illuminate\Support\Facades\DB::table('object_term_relation')
      ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
      ->join('term', 'object_term_relation.term_id', '=', 'term.id')
      ->leftJoin('slug', 'object_term_relation.term_id', '=', 'slug.object_id')
      ->where('object_term_relation.object_id', $io->id)
      ->where('term.taxonomy_id', 42) // Places taxonomy
      ->where('term_i18n.culture', $culture)
      ->select('term_i18n.name', 'slug.slug')
      ->get();

  // Digital objects (instantiations)
  $digitalObjects = \Illuminate\Support\Facades\DB::table('digital_object')
      ->where('object_id', $io->id)
      ->get();

  // Repository (holder)
  $holder = null;
  if ($io->repository_id) {
      $holder = \Illuminate\Support\Facades\DB::table('actor_i18n')
          ->leftJoin('slug', 'actor_i18n.id', '=', 'slug.object_id')
          ->where('actor_i18n.id', $io->repository_id)
          ->where('actor_i18n.culture', $culture)
          ->select('actor_i18n.authorized_form_of_name as name', 'slug.slug')
          ->first();
  }
@endphp

<div class="ric-view">
  {{-- RiC Entity Header --}}
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div>
        <i class="fas fa-project-diagram me-2"></i>
        <strong>{{ $io->title }}</strong>
      </div>
      <span style="background:#fff !important;color:#198754 !important;border:2px solid #198754;padding:.25em .6em;border-radius:.375em;font-size:.85em;font-weight:600;display:inline-block;">rico:{{ $ricType }}</span>
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:25%">{{ __('RiC Entity Type') }}</th><td><code>rico:{{ $ricType }}</code></td></tr>
        <tr><th class="text-muted">{{ __('Identifier') }}</th><td>{{ $io->identifier ?? '—' }}</td></tr>
        <tr><th class="text-muted">{{ __('Level') }}</th><td>{{ $lodLabel ?: '—' }}</td></tr>
        @if($holder)
          <tr><th class="text-muted">{{ __('hasOrHadHolder') }}</th><td><a href="{{ url('/' . $holder->slug) }}">{{ $holder->name }}</a></td></tr>
        @endif
        @if($io->scope_and_content)
          <tr><th class="text-muted">{{ __('Scope') }}</th><td>{{ \Illuminate\Support\Str::limit(strip_tags($io->scope_and_content), 200) }}</td></tr>
        @endif
        @if($io->extent_and_medium)
          <tr><th class="text-muted">{{ __('Extent') }}</th><td>{{ $io->extent_and_medium }}</td></tr>
        @endif
      </table>
    </div>
  </div>

  <div class="row">
    {{-- Relationships --}}
    <div class="col-12">

      {{-- Hierarchy removed — IO parent/child already shown via treeview in left sidebar --}}

      {{-- Creators / Accumulators --}}
      @if($creators->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-user me-1"></i> Creators and Agents (hasOrHadCreator)
        </div>
        <div class="list-group list-group-flush">
          @foreach($creators as $creator)
            <a href="{{ url('/' . $creator->slug) }}" class="list-group-item list-group-item-action">
              <i class="fas fa-user-circle text-danger me-1"></i>
              {{ $creator->name }}
              <span class="badge bg-secondary float-end">{{ __('Agent') }}</span>
            </a>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Related Records --}}
      @if($relatedRecords->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-link me-1"></i> Related Records (isAssociatedWith)
        </div>
        <div class="list-group list-group-flush">
          @foreach($relatedRecords as $rel)
            <a href="{{ url('/' . $rel->slug) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
              <span>{{ $rel->title }}</span>
              @if($rel->relation_type)
                <span class="badge bg-secondary">{{ $rel->relation_type }}</span>
              @endif
            </a>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Subjects --}}
      @if($subjects->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-tags me-1"></i> Subjects (hasOrHadSubject)
        </div>
        <div class="card-body">
          @foreach($subjects as $subj)
            <a href="{{ url('/' . $subj->slug) }}" class="badge bg-primary text-decoration-none me-1 mb-1">{{ $subj->name }}</a>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Places --}}
      @if($places->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-map-marker-alt me-1"></i> Places (isAssociatedWithPlace)
        </div>
        <div class="card-body">
          @foreach($places as $place)
            <a href="{{ url('/' . $place->slug) }}" class="badge bg-warning text-dark text-decoration-none me-1 mb-1">{{ $place->name }}</a>
          @endforeach
        </div>
      </div>
      @endif

    </div>
  </div>

  {{-- Full width: Provenance + Instantiations + Actions --}}
  <div class="row">
    <div class="col-12">

      {{-- Provenance & Chain of Custody (matching Heratio view) --}}
      @php
        $ricProvenanceEntries = collect();
        try {
            $ricProvenanceEntries = \Illuminate\Support\Facades\DB::table('provenance_entry')
                ->where('information_object_id', $io->id)
                ->orderBy('sequence')
                ->get();
        } catch (\Exception $e) {}
      @endphp
      @if($ricProvenanceEntries->isNotEmpty())
        <div class="card mb-3">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <i class="fas fa-history me-1"></i> Provenance &amp; Chain of Custody
          </div>
          <div class="card-body px-3 py-2">
            @foreach($ricProvenanceEntries as $i => $entry)
              <div class="d-flex mb-2 align-items-start">
                <div class="me-2">
                  <span class="badge rounded-pill bg-{{ $i === 0 ? 'primary' : 'secondary' }}">{{ $ricProvenanceEntries->count() - $i }}</span>
                </div>
                <div class="flex-grow-1">
                  <div class="d-flex justify-content-between">
                    <div>
                      <strong class="small">{{ $entry->owner_name }}</strong>
                      @if($entry->owner_type && $entry->owner_type !== 'unknown')
                        <span class="badge bg-info ms-1" style="font-size:0.65rem;">{{ ucfirst(str_replace('_', ' ', $entry->owner_type)) }}</span>
                      @endif
                      @if($entry->transfer_type && $entry->transfer_type !== 'unknown')
                        <span class="badge bg-secondary ms-1" style="font-size:0.65rem;">{{ ucfirst(str_replace('_', ' ', $entry->transfer_type)) }}</span>
                      @endif
                    </div>
                  </div>
                  <small class="text-muted">
                    @if($entry->start_date && $entry->end_date)
                      {{ $entry->start_date }} &ndash; {{ $entry->end_date }}
                    @elseif($entry->start_date)
                      {{ $entry->start_date }} &ndash; present
                    @elseif($entry->end_date)
                      until {{ $entry->end_date }}
                    @endif
                  </small>
                  @if($entry->owner_location)
                    <br><small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i>{{ $entry->owner_location }}</small>
                  @endif
                  @if($entry->notes)
                    <p class="small text-muted mb-0 mt-1">{{ $entry->notes }}</p>
                  @endif
                </div>
              </div>
            @endforeach
            @auth
              <a href="{{ route('io.provenance', $io->slug) }}" class="btn btn-sm atom-btn-white mt-1">
                <i class="fas fa-edit me-1"></i>{{ __('Edit provenance') }}
              </a>
            @endauth
          </div>
        </div>
      @endif

      {{-- Instantiations — digital object metadata --}}
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-file-image me-1"></i> Instantiations ({{ $digitalObjects->count() }})
        </div>
        <div class="card-body px-3 py-2">
          @if($digitalObjects->count())
            @foreach($digitalObjects as $dobj)
              @php
                $dobjMediaType = \AhgCore\Services\DigitalObjectService::getMediaType($dobj);
                $dobjUrl = \AhgCore\Services\DigitalObjectService::getUrl($dobj);
                $usageLabel = match((int)($dobj->usage_id ?? 0)) {
                    140 => 'Master', 141 => 'Reference', 142 => 'Thumbnail', 166 => 'Master', default => 'File'
                };
                // Get derivatives (reference + thumbnail)
                $derivatives = \Illuminate\Support\Facades\DB::table('digital_object')
                    ->where('parent_id', $dobj->id)
                    ->orderBy('usage_id')
                    ->get();
              @endphp
              <div class="mb-3">
                <h6 class="small fw-bold mb-1">{{ $usageLabel }} file</h6>
                <table class="table table-sm table-borderless mb-1 small">
                  <tr><td class="text-muted" style="width:80px">Filename</td><td class="text-break">{{ $dobj->name ?? '-' }}</td></tr>
                  <tr><td class="text-muted">Media type</td><td>{{ ucfirst($dobjMediaType) }}</td></tr>
                  <tr><td class="text-muted">MIME type</td><td><code>{{ $dobj->mime_type ?? '-' }}</code></td></tr>
                  @if($dobj->byte_size)
                    <tr><td class="text-muted">Filesize</td><td>{{ \AhgCore\Services\DigitalObjectService::formatFileSize($dobj->byte_size) }}</td></tr>
                  @endif
                  @if($dobj->checksum ?? null)
                    <tr><td class="text-muted">Checksum</td><td class="text-break" style="font-size:0.7rem;">{{ $dobj->checksum }}</td></tr>
                  @endif
                </table>
                @foreach($derivatives as $deriv)
                  @php
                    $derivLabel = match((int)($deriv->usage_id ?? 0)) {
                        141 => 'Reference copy', 142 => 'Thumbnail copy', default => 'Derivative'
                    };
                  @endphp
                  <h6 class="small fw-bold mb-1">{{ $derivLabel }}</h6>
                  <table class="table table-sm table-borderless mb-1 small">
                    <tr><td class="text-muted" style="width:80px">Filename</td><td class="text-break">{{ $deriv->name ?? '-' }}</td></tr>
                    <tr><td class="text-muted">MIME type</td><td><code>{{ $deriv->mime_type ?? '-' }}</code></td></tr>
                    @if($deriv->byte_size)
                      <tr><td class="text-muted">Filesize</td><td>{{ \AhgCore\Services\DigitalObjectService::formatFileSize($deriv->byte_size) }}</td></tr>
                    @endif
                  </table>
                @endforeach
              </div>
            @endforeach
          @else
            <p class="text-muted small mb-0">No instantiations.</p>
          @endif
        </div>
      </div>

      {{-- Actions moved to right sidebar (@section('right') in IO show.blade.php) --}}

    </div>
  </div>
</div>

<script>
</script>
