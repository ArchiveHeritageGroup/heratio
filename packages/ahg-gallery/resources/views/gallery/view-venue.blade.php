@extends('theme::layouts.1col')
@section('title', 'View Venue')
@section('body-class', 'gallery view-venue')
@section('title-block')<h1 class="mb-0">{{ $venue->name ?? 'Venue Details' }}</h1>@endsection
@section('content')
<div class="card"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Venue Details') }}</h5></div>
<div class="card-body"><div class="row"><div class="col-md-6"><dl>
  @if($venue->name ?? null)<dt>Name</dt><dd>{{ $venue->name }}</dd>@endif
  @if($venue->venue_type ?? null)<dt>Type</dt><dd>{{ ucfirst($venue->venue_type) }}</dd>@endif
  @if($venue->address ?? null)<dt>Address</dt><dd>{{ $venue->address }}</dd>@endif
  @if($venue->city ?? null)<dt>City</dt><dd>{{ $venue->city }}</dd>@endif
</dl></div><div class="col-md-6"><dl>
  @if($venue->country ?? null)<dt>Country</dt><dd>{{ $venue->country }}</dd>@endif
  @if($venue->contact_person ?? null)<dt>Contact Person</dt><dd>{{ $venue->contact_person }}</dd>@endif
  @if($venue->email ?? null)<dt>Email</dt><dd>{{ $venue->email }}</dd>@endif
</dl></div></div>
@if($venue->notes ?? null)<h6>{{ __('Notes') }}</h6><p>{!! nl2br(e($venue->notes)) !!}</p>@endif
</div></div>
<div class="mt-3"><a href="{{ route('gallery.venues') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Venues</a></div>
@endsection
