{{--
  receipt.blade.php - anonymous receipt page for a publish request.
  Lookup by 40-char hex token from the URL; no auth required. Holder of the
  URL is the only person who can see status + curator notes. (#745)
--}}
@extends('theme::layouts.1col')

@section('title', __('Publish Request Receipt'))
@section('body-class', 'publish-request receipt')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-receipt fa-2x text-primary me-3" aria-hidden="true"></i>
    <div>
      <h1 class="h3 mb-0">{{ __('Publish Request Receipt') }}</h1>
      <p class="text-muted mb-0">{{ __('Status of your request to publish.') }}</p>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success" role="alert">{{ session('success') }}</div>
  @endif

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      @php
        $statusBadge = match($row->status ?? 'pending') {
          'approved' => 'bg-success',
          'rejected' => 'bg-danger',
          'edited'   => 'bg-info text-dark',
          default    => 'bg-warning text-dark',
        };
      @endphp
      <dl class="row mb-0">
        <dt class="col-sm-3">{{ __('Status') }}</dt>
        <dd class="col-sm-9">
          <span class="badge {{ $statusBadge }}">{{ ucfirst($row->status ?? 'pending') }}</span>
        </dd>

        <dt class="col-sm-3">{{ __('Submitted') }}</dt>
        <dd class="col-sm-9">
          @if(!empty($row->created_at))
            {{ \Carbon\Carbon::parse($row->created_at)->format('d M Y H:i') }}
          @else
            -
          @endif
        </dd>

        @if(!empty($row->decided_at))
          <dt class="col-sm-3">{{ __('Decision recorded') }}</dt>
          <dd class="col-sm-9">{{ \Carbon\Carbon::parse($row->decided_at)->format('d M Y H:i') }}</dd>
        @endif

        @if($object)
          <dt class="col-sm-3">{{ __('Archival item') }}</dt>
          <dd class="col-sm-9">
            @if(!empty($object->slug))
              <a href="/{{ $object->slug }}">{{ $object->title ?: $object->identifier ?: ('#'.$object->id) }}</a>
            @else
              {{ $object->title ?: $object->identifier ?: ('#'.$object->id) }}
            @endif
          </dd>
        @endif

        <dt class="col-sm-3">{{ __('Submitter') }}</dt>
        <dd class="col-sm-9">{{ $row->submitter_name ?: '-' }} &lt;{{ $row->submitter_email }}&gt;</dd>

        <dt class="col-sm-3">{{ __('Your message') }}</dt>
        <dd class="col-sm-9">
          <div class="border rounded p-2 bg-light small">{{ $row->message_text ?: '-' }}</div>
        </dd>

        @if(!empty($row->curator_notes))
          <dt class="col-sm-3">{{ __('Curator notes') }}</dt>
          <dd class="col-sm-9">
            <div class="border rounded p-2 bg-light small">{{ $row->curator_notes }}</div>
          </dd>
        @endif
      </dl>
    </div>
  </div>

  <p class="text-muted small">
    {{ __('Bookmark this page - the receipt URL is the only way to check the status of your request without contacting an archivist.') }}
  </p>
@endsection
