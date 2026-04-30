{{-- RiC View: Actor — agent network and contextual relationships --}}
@php
  $culture = app()->getLocale();

  // Determine RiC agent type
  $entityTypeId = $actor->entity_type_id ?? null;
  $ricAgentType = match($entityTypeId) {
      132 => 'CorporateBody',
      133 => 'Person',
      134 => 'Family',
      default => 'Agent',
  };

  // Descriptions created by this agent
  $createdDescriptions = \Illuminate\Support\Facades\DB::table('event')
      ->join('information_object_i18n as ioi', 'event.object_id', '=', 'ioi.id')
      ->leftJoin('slug', 'event.object_id', '=', 'slug.object_id')
      ->where('event.actor_id', $actor->id)
      ->where('ioi.culture', $culture)
      ->whereNotNull('ioi.title')
      ->select('event.object_id as id', 'ioi.title', 'slug.slug', 'event.type_id')
      ->distinct()
      ->limit(20)
      ->get();

  // Related agents via relation table
  $relatedAgents = \Illuminate\Support\Facades\DB::table('relation')
      ->leftJoin('actor_i18n as ai', function ($j) use ($actor, $culture) {
          $j->on(DB::raw("CASE WHEN relation.subject_id = {$actor->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'ai.id')
            ->where('ai.culture', $culture);
      })
      ->leftJoin('slug', DB::raw("CASE WHEN relation.subject_id = {$actor->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'slug.object_id')
      ->leftJoin('term_i18n as ti', function ($j) {
          $j->on('relation.type_id', '=', 'ti.id')->where('ti.culture', 'en');
      })
      ->where(function ($q) use ($actor) {
          $q->where('relation.subject_id', $actor->id)
            ->orWhere('relation.object_id', $actor->id);
      })
      ->whereNotNull('ai.authorized_form_of_name')
      ->select('ai.authorized_form_of_name as name', 'slug.slug', 'ti.name as relation_type')
      ->limit(20)
      ->get();

  // Maintained repositories
  $repositories = \Illuminate\Support\Facades\DB::table('repository')
      ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
      ->leftJoin('slug', 'repository.id', '=', 'slug.object_id')
      ->where('actor_i18n.culture', $culture)
      ->whereNotNull('actor_i18n.authorized_form_of_name')
      ->where(function ($q) use ($actor) {
          $q->whereExists(function ($sub) use ($actor) {
              $sub->select(DB::raw(1))
                  ->from('relation')
                  ->whereColumn('relation.object_id', 'repository.id')
                  ->where('relation.subject_id', $actor->id);
          });
      })
      ->select('repository.id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug')
      ->get();

  // Places associated with this agent
  $places = \Illuminate\Support\Facades\DB::table('object_term_relation')
      ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
      ->join('term', 'object_term_relation.term_id', '=', 'term.id')
      ->leftJoin('slug', 'object_term_relation.term_id', '=', 'slug.object_id')
      ->where('object_term_relation.object_id', $actor->id)
      ->where('term.taxonomy_id', 42)
      ->where('term_i18n.culture', $culture)
      ->select('term_i18n.name', 'slug.slug')
      ->get();
@endphp

<div class="ric-view">
  {{-- RiC Agent Header --}}
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div>
        <i class="fas fa-{{ $ricAgentType === 'Person' ? 'user' : ($ricAgentType === 'Family' ? 'users' : 'building') }} me-2"></i>
        <strong>{{ $actor->authorized_form_of_name }}</strong>
      </div>
      <span style="background:#fff !important;color:#198754 !important;border:2px solid #198754;padding:.25em .6em;border-radius:.375em;font-size:.85em;font-weight:600;display:inline-block;">rico:{{ $ricAgentType }}</span>
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:35%">{{ __('RiC Entity Type') }}</th><td><code>rico:{{ $ricAgentType }}</code></td></tr>
        @if($actor->dates_of_existence ?? null)
          <tr><th class="text-muted">{{ __('Dates of existence') }}</th><td>{{ $actor->dates_of_existence }}</td></tr>
        @endif
        @if($actor->history ?? null)
          <tr><th class="text-muted">{{ __('History') }}</th><td>{{ \Illuminate\Support\Str::limit(strip_tags($actor->history), 300) }}</td></tr>
        @endif
      </table>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">

      {{-- Created / Accumulated Descriptions --}}
      @if($createdDescriptions->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-file-alt me-1"></i> isCreatorOf / isAccumulatorOf
          <span class="badge bg-light text-dark float-end">{{ $createdDescriptions->count() }}</span>
        </div>
        <div class="list-group list-group-flush">
          @foreach($createdDescriptions as $desc)
            <a href="{{ url('/' . $desc->slug) }}" class="list-group-item list-group-item-action">
              <i class="fas fa-file-alt text-info me-1"></i>{{ $desc->title }}
            </a>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Related Agents --}}
      @if($relatedAgents->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-users me-1"></i> {{ __('Related Agents (isAssociatedWith)') }}
        </div>
        <div class="list-group list-group-flush">
          @foreach($relatedAgents as $rel)
            <a href="{{ url('/' . $rel->slug) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
              <span><i class="fas fa-user-circle text-danger me-1"></i>{{ $rel->name }}</span>
              @if($rel->relation_type)
                <span class="badge bg-secondary">{{ $rel->relation_type }}</span>
              @endif
            </a>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Maintained Repositories --}}
      @if($repositories->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-building me-1"></i> {{ __('Maintains (isHolderOf)') }}
        </div>
        <div class="list-group list-group-flush">
          @foreach($repositories as $repo)
            <a href="{{ url('/' . $repo->slug) }}" class="list-group-item list-group-item-action">
              <i class="fas fa-building text-warning me-1"></i>{{ $repo->name }}
            </a>
          @endforeach
        </div>
      </div>
      @endif

    </div>

    <div class="col-md-4">

      {{-- Places --}}
      @if($places->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-map-marker-alt me-1"></i> {{ __('Places') }}
        </div>
        <div class="card-body">
          @foreach($places as $place)
            <a href="{{ url('/' . $place->slug) }}" class="badge bg-warning text-dark text-decoration-none me-1 mb-1">{{ $place->name }}</a>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Actions --}}
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-bolt me-1"></i> {{ __('Actions') }}
        </div>
        <div class="card-body">
          <a href="{{ route('ric.explorer') }}?id={{ $actor->id }}" class="btn btn-sm btn-outline-success w-100 mb-2 text-start">
            <i class="fas fa-project-diagram me-1"></i> {{ __('Graph Explorer') }}
          </a>
          <a href="{{ route('ric.export-jsonld') }}?id={{ $actor->id }}" class="btn btn-sm btn-outline-info w-100 mb-2 text-start" target="_blank">
            <i class="fas fa-code me-1"></i> {{ __('JSON-LD Export') }}
          </a>
          <a href="{{ route('ric.explorer') }}?id={{ $actor->id }}&view=timeline" class="btn btn-sm btn-outline-secondary w-100 text-start">
            <i class="fas fa-clock me-1"></i> {{ __('Timeline') }}
          </a>
        </div>
      </div>

    </div>
  </div>
</div>
