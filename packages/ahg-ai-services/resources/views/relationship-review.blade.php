@extends('theme::layouts.1col')

@section('title', __('Relationship review'))
@section('body-class', 'browse ai')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-people-arrows me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Relationship review') }}</h1>
      <span class="text-muted">{{ __('Relationships between people or organisations that a record states, proposed for review. Approving one saves it on both authority records.') }}</span>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

  <ul class="nav nav-tabs mb-3">
    @foreach($statuses as $s)
      <li class="nav-item">
        <a class="nav-link @if($s === $status) active @endif" href="{{ route('admin.ai.relationship-review', ['status' => $s]) }}">
          {{ __(ucfirst($s)) }} <span class="badge bg-secondary">{{ $counts[$s] ?? 0 }}</span>
        </a>
      </li>
    @endforeach
  </ul>

  @forelse($rows as $row)
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between flex-wrap gap-2">
        <div>
          @foreach(['subject', 'object'] as $side)
            @php($r = $row->{$side})
            @if($r['id'])
              <a href="{{ $r['slug'] ? url('/'.$r['slug']) : '#' }}" target="_blank" rel="noopener"><strong>{{ $r['name'] ?: $row->{$side.'_name'} }}</strong></a>
            @else
              <strong>{{ $row->{$side.'_name'} }}</strong> <span class="badge bg-warning text-dark">{{ __('not linked yet') }}</span>
            @endif
            @if($side === 'subject') <em class="mx-1">{{ $row->relation }}</em> @endif
          @endforeach
          @if($row->date_text) <span class="text-muted">({{ $row->date_text }})</span>@endif
        </div>
        <small class="text-muted">
          {{ __('From') }} @if($row->slug)<a href="{{ url('/'.$row->slug) }}" target="_blank" rel="noopener">{{ $row->title ?: $row->slug }}</a>@endif,
          {{ __('proposed') }}@if($row->proposer) {{ __('by') }} {{ $row->proposer }}@endif @if($row->proposed_via)({{ $row->proposed_via }})@endif, {{ $row->created_at }}
          @if($row->reviewer) - {{ __('reviewed by') }} {{ $row->reviewer }}, {{ $row->reviewed_at }}@endif
        </small>
      </div>
      <div class="card-body small">
        @if($row->evidence)<p class="mb-2"><strong>{{ __('Evidence') }}:</strong> {{ $row->evidence }}</p>@endif
        @if($row->sources_list)
          <p class="mb-1"><strong>{{ __('Sources') }}:</strong></p>
          <ul>@foreach($row->sources_list as $src)<li><a href="{{ $src }}" target="_blank" rel="noopener noreferrer nofollow">{{ $src }}</a></li>@endforeach</ul>
        @endif
        @if($status === 'pending')
          @if(! $row->subject['id'] || ! $row->object['id'])
            <p class="text-muted">{{ __('Link each name to an authority record in NER review or Authority Resolution, then approve.') }}</p>
          @endif
          <form method="POST" action="{{ route('admin.ai.relationship-review.decision', $row->id) }}" class="d-flex flex-wrap gap-2">
            @csrf
            <input class="form-control form-control-sm w-auto flex-grow-1" name="notes" maxlength="2000" placeholder="{{ __('Note for the proposer (optional)') }}" aria-label="{{ __('Review note') }}">
            <button class="btn btn-success btn-sm" name="decision" value="approve" @disabled(! $row->subject['id'] || ! $row->object['id'])><i class="fas fa-check me-1"></i>{{ __('Approve') }}</button>
            <button class="btn btn-outline-danger btn-sm" name="decision" value="reject"><i class="fas fa-times me-1"></i>{{ __('Reject') }}</button>
          </form>
        @elseif($row->review_notes)
          <p class="mb-0"><strong>{{ __('Note') }}:</strong> {{ $row->review_notes }}</p>
        @endif
      </div>
    </div>
  @empty
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>{{ __('Nothing here.') }}</div>
  @endforelse

  {{ $rows->links() }}
@endsection
