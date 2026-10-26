@extends('theme::layouts.1col')

@section('title', __('Suggest review'))
@section('body-class', 'browse ai')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-robot me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Suggest review') }}</h1>
      <span class="text-muted">{{ __('Text proposed for records: field changes, translations and transcriptions. Nothing changes on a record until you approve it.') }}</span>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

  <ul class="nav nav-tabs mb-3">
    @foreach($statuses as $s)
      <li class="nav-item">
        <a class="nav-link @if($s === $status) active @endif" href="{{ route('admin.ai.suggest-review', ['status' => $s]) }}">
          {{ __(ucfirst($s)) }} <span class="badge bg-secondary">{{ $counts[$s] ?? 0 }}</span>
        </a>
      </li>
    @endforeach
  </ul>

  @forelse($rows as $row)
    @php
      $label = match ($row->kind) {
          'transcript' => __('Transcription of a file'),
          'translation' => __('Translation of :field into :lang', ['field' => str_replace('_', ' ', $row->meta['field'] ?? ''), 'lang' => $row->meta['culture'] ?? '']),
          default => __('Change to :field', ['field' => str_replace('_', ' ', $row->meta['field'] ?? '')]),
      };
    @endphp
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between flex-wrap gap-2">
        <div>
          <strong>{{ $label }}</strong>
          @if($row->slug)
            - <a href="{{ url('/'.$row->slug) }}" target="_blank" rel="noopener">{{ $row->record_title ?: $row->slug }}</a>
          @endif
        </div>
        <small class="text-muted">
          {{ __('Proposed') }}@if($row->proposer) {{ __('by') }} {{ $row->proposer }}@endif
          @if(! empty($row->meta['proposed_via'])) ({{ $row->meta['proposed_via'] }})@elseif($row->model_used) ({{ $row->model_used }})@endif,
          {{ $row->created_at }}
          @if($row->reviewer) - {{ __('reviewed by') }} {{ $row->reviewer }}, {{ $row->reviewed_at }}@endif
        </small>
      </div>
      <div class="card-body">
        @if(! empty($row->meta['evidence']))
          <p class="small mb-2"><strong>{{ __('Evidence') }}:</strong> {{ $row->meta['evidence'] }}</p>
        @endif
        @if($row->sources_list)
          <p class="small mb-1"><strong>{{ __('Sources') }}:</strong></p>
          <ul class="small">
            @foreach($row->sources_list as $src)
              <li><a href="{{ $src }}" target="_blank" rel="noopener noreferrer nofollow">{{ $src }}</a></li>
            @endforeach
          </ul>
        @endif

        <div class="row g-3">
          @if($row->kind === 'transcript' && ! empty($row->meta['image']) && str_starts_with($row->meta['image'], '/'))
            <div class="col-lg-6">
              <h2 class="h6">{{ __('Page') }}</h2>
              <a href="{{ $row->meta['image'] }}" target="_blank" rel="noopener"><img src="{{ $row->meta['image'] }}" class="img-fluid border" alt="{{ __('Page image') }}" loading="lazy"></a>
            </div>
          @else
            <div class="col-lg-6">
              <h2 class="h6">{{ $row->kind === 'translation' ? __('Current text in that language') : __('Current text') }}</h2>
              <div class="border rounded p-2 bg-light small" style="white-space: pre-wrap; max-height: 20rem; overflow:auto;">{{ $row->existing_text ?? '' }}@if(($row->existing_text ?? '') === '')<span class="text-muted">{{ __('(empty)') }}</span>@endif</div>
            </div>
          @endif
          <div class="col-lg-6">
            <h2 class="h6">{{ $status === 'pending' ? __('Proposed text (you may edit it before approving)') : __('Proposed text') }}</h2>
            @if($status === 'pending')
              <form method="POST" action="{{ route('admin.ai.suggest-review.decision', $row->id) }}">
                @csrf
                <textarea class="form-control mb-2" name="edited_text" rows="10" aria-label="{{ __('Proposed text') }}">{{ $row->suggested_text }}</textarea>
                <input class="form-control form-control-sm mb-2" name="notes" maxlength="2000" placeholder="{{ __('Note for the proposer (optional)') }}" aria-label="{{ __('Review note') }}">
                <button class="btn btn-success btn-sm" name="decision" value="approve"><i class="fas fa-check me-1"></i>{{ __('Approve') }}</button>
                <button class="btn btn-outline-danger btn-sm" name="decision" value="reject"><i class="fas fa-times me-1"></i>{{ __('Reject') }}</button>
              </form>
            @else
              <div class="border rounded p-2 small" style="white-space: pre-wrap; max-height: 20rem; overflow:auto;">{{ $row->edited_text ?: $row->suggested_text }}</div>
              @if($row->review_notes)<p class="small mt-2 mb-0"><strong>{{ __('Note') }}:</strong> {{ $row->review_notes }}</p>@endif
            @endif
          </div>
        </div>
      </div>
    </div>
  @empty
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>{{ __('Nothing here.') }}</div>
  @endforelse

  {{ $rows->links() }}
@endsection
