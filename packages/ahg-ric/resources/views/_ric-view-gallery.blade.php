{{--
  RiC View: Gallery artwork — record-resource context with creators and exhibition history.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@php
  $culture = app()->getLocale();

  $creators = collect();
  if (isset($artwork->id)) {
      $creators = \Illuminate\Support\Facades\DB::table('relation as r')
          ->join('actor_i18n as ai', function ($j) use ($artwork, $culture) {
              $j->on(DB::raw("CASE WHEN r.subject_id = {$artwork->id} THEN r.object_id ELSE r.subject_id END"), '=', 'ai.id')
                ->where('ai.culture', $culture);
          })
          ->leftJoin('slug', 'slug.object_id', '=', 'ai.id')
          ->where(function ($q) use ($artwork) {
              $q->where('r.subject_id', $artwork->id)->orWhere('r.object_id', $artwork->id);
          })
          ->whereNotNull('ai.authorized_form_of_name')
          ->select('ai.authorized_form_of_name as name', 'slug.slug')
          ->limit(20)
          ->get();
  }

  $artworkTitle = $artwork->title ?? '[Untitled artwork]';
@endphp

<div class="ric-view">
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div><i class="fas fa-palette me-2"></i><strong>{{ $artworkTitle }}</strong></div>
      @include('ahg-ric::components.type-pill', ['type' => 'RecordResource', 'qualifier' => 'Artwork'])
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:35%">{{ __('RiC Role') }}</th><td>Cultural artwork / museum piece</td></tr>
        @if(! empty($artwork->medium))<tr><th class="text-muted">{{ __('Medium') }}</th><td>{{ $artwork->medium }}</td></tr>@endif
        @if(! empty($artwork->date_created))<tr><th class="text-muted">{{ __('Date created') }}</th><td>{{ $artwork->date_created }}</td></tr>@endif
        @if(! empty($artwork->dimensions))<tr><th class="text-muted">{{ __('Dimensions') }}</th><td>{{ $artwork->dimensions }}</td></tr>@endif
      </table>
    </div>
  </div>

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
