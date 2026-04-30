{{-- RiC View: Function — ISDF function with related records and sub-functions --}}
@php
  $culture = app()->getLocale();

  // Related descriptions via relation table
  $relatedDescriptions = \Illuminate\Support\Facades\DB::table('relation')
      ->join('information_object_i18n as ioi', function ($j) use ($function, $culture) {
          $j->on(DB::raw("CASE WHEN relation.subject_id = {$function->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'ioi.id')
            ->where('ioi.culture', $culture);
      })
      ->leftJoin('slug', DB::raw("CASE WHEN relation.subject_id = {$function->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'slug.object_id')
      ->where(function ($q) use ($function) {
          $q->where('relation.subject_id', $function->id)->orWhere('relation.object_id', $function->id);
      })
      ->whereNotNull('ioi.title')
      ->select('ioi.title', 'slug.slug')
      ->limit(20)
      ->get();

  // Related agents
  $relatedAgents = \Illuminate\Support\Facades\DB::table('relation')
      ->join('actor_i18n as ai', function ($j) use ($function, $culture) {
          $j->on(DB::raw("CASE WHEN relation.subject_id = {$function->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'ai.id')
            ->where('ai.culture', $culture);
      })
      ->leftJoin('slug', DB::raw("CASE WHEN relation.subject_id = {$function->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'slug.object_id')
      ->where(function ($q) use ($function) {
          $q->where('relation.subject_id', $function->id)->orWhere('relation.object_id', $function->id);
      })
      ->whereNotNull('ai.authorized_form_of_name')
      ->select('ai.authorized_form_of_name as name', 'slug.slug')
      ->limit(20)
      ->get();

  // Sub-functions — function_object has no parent_id column in Heratio; return empty
  $subFunctions = collect();
@endphp

<div class="ric-view">
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div><i class="fas fa-cogs me-2"></i><strong>{{ $function->authorized_form_of_name }}</strong></div>
      @include('ahg-ric::components.type-pill', ['type' => 'Function'])
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:35%">{{ __('RiC Entity Type') }}</th><td>@include('ahg-ric::components.type-pill', ['type' => 'Function'])</td></tr>
        @if($function->classification ?? null)<tr><th class="text-muted">{{ __('Classification') }}</th><td>{{ $function->classification }}</td></tr>@endif
        @if($function->description ?? null)<tr><th class="text-muted">{{ __('Description') }}</th><td>{{ \Illuminate\Support\Str::limit(strip_tags($function->description), 200) }}</td></tr>@endif
      </table>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">
      @if($subFunctions->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-sitemap me-1"></i> Sub-functions (hasPart)</div>
        <div class="list-group list-group-flush">
          @foreach($subFunctions as $sf)
            <a href="{{ url('/' . $sf->slug) }}" class="list-group-item list-group-item-action"><i class="fas fa-cog text-purple me-1"></i>{{ $sf->name }}</a>
          @endforeach
        </div>
      </div>
      @endif

      @if($relatedDescriptions->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-file-alt me-1"></i> Related Descriptions</div>
        <div class="list-group list-group-flush">
          @foreach($relatedDescriptions as $desc)
            <a href="{{ url('/' . $desc->slug) }}" class="list-group-item list-group-item-action"><i class="fas fa-file-alt text-info me-1"></i>{{ $desc->title }}</a>
          @endforeach
        </div>
      </div>
      @endif

      @if($relatedAgents->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-users me-1"></i> Related Agents</div>
        <div class="list-group list-group-flush">
          @foreach($relatedAgents as $a)
            <a href="{{ url('/' . $a->slug) }}" class="list-group-item list-group-item-action"><i class="fas fa-user-circle text-danger me-1"></i>{{ $a->name }}</a>
          @endforeach
        </div>
      </div>
      @endif
    </div>

    <div class="col-md-4">
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-bolt me-1"></i> Actions</div>
        <div class="card-body">
          <a href="/explorer" class="btn btn-sm btn-outline-success w-100 mb-2"><i class="fas fa-project-diagram me-1"></i>{{ __('Open in Graph Explorer') }}</a>
          <a href="/ric-api/relations/{{ $function->id }}" class="btn btn-sm btn-outline-info w-100" target="_blank"><i class="fas fa-code me-1"></i>{{ __('View Relations JSON') }}</a>
        </div>
      </div>
    </div>
  </div>
</div>
