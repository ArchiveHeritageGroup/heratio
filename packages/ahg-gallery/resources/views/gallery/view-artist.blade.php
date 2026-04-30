{{-- Alias for artist-show --}}
@extends('theme::layouts.1col')
@section('title', ($artist->display_name ?? 'Artist'))
@section('body-class', 'gallery view-artist')
@section('title-block')<h1 class="mb-0">{{ $artist->display_name ?? 'Artist' }}</h1>@endsection
@section('content')
<div class="card"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Artist Details') }}</h5></div>
<div class="card-body"><div class="row"><div class="col-md-6"><dl>
  @if($artist->display_name ?? null)<dt>Name</dt><dd>{{ $artist->display_name }}</dd>@endif
  @if($artist->birth_date ?? null)<dt>Birth Date</dt><dd>{{ $artist->birth_date }}</dd>@endif
  @if($artist->death_date ?? null)<dt>Death Date</dt><dd>{{ $artist->death_date }}</dd>@endif
  @if($artist->nationality ?? null)<dt>Nationality</dt><dd>{{ $artist->nationality }}</dd>@endif
  @if($artist->artist_type ?? null)<dt>Type</dt><dd>{{ ucfirst($artist->artist_type) }}</dd>@endif
</dl></div><div class="col-md-6"><dl>
  @if($artist->medium_specialty ?? null)<dt>Medium/Specialty</dt><dd>{{ $artist->medium_specialty }}</dd>@endif
  @if($artist->movement_style ?? null)<dt>Movement/Style</dt><dd>{{ $artist->movement_style }}</dd>@endif
  @if($artist->active_period ?? null)<dt>Active Period</dt><dd>{{ $artist->active_period }}</dd>@endif
  @if($artist->website ?? null)<dt>Website</dt><dd><a href="{{ $artist->website }}" target="_blank">{{ $artist->website }}</a></dd>@endif
</dl></div></div>
@if($artist->biography ?? null)<h6 class="mt-3">{{ __('Biography') }}</h6><p>{!! nl2br(e($artist->biography)) !!}</p>@endif
</div></div>
@endsection
