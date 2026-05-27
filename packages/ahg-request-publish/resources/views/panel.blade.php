{{--
  panel.blade.php - per-request curator review + decision panel (#745).
  Admin-only. Shows original submission and writes status + curator notes.
--}}
@extends('theme::layouts.1col')

@section('title', __('Review Publish Request'))
@section('body-class', 'publish-request panel')

@section('content')
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-clipboard-check fa-2x text-primary me-3" aria-hidden="true"></i>
    <div>
      <h1 class="h3 mb-0">{{ __('Review Publish Request') }} #{{ $row->id }}</h1>
      <p class="text-muted mb-0">{{ __('Read the submission, record a decision.') }}</p>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header">{{ __('Submission') }}</div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">{{ __('Submitter') }}</dt>
        <dd class="col-sm-9">{{ $row->submitter_name ?: '-' }} &lt;{{ $row->submitter_email }}&gt;</dd>

        <dt class="col-sm-3">{{ __('Submitted at') }}</dt>
        <dd class="col-sm-9">
          @if(!empty($row->created_at))
            {{ \Carbon\Carbon::parse($row->created_at)->format('d M Y H:i') }}
          @endif
        </dd>

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

        <dt class="col-sm-3">{{ __('Message') }}</dt>
        <dd class="col-sm-9">
          <div class="border rounded p-2 bg-light small">{{ $row->message_text ?: '-' }}</div>
        </dd>

        <dt class="col-sm-3">{{ __('Receipt token') }}</dt>
        <dd class="col-sm-9">
          <code class="small">{{ $row->token }}</code>
          <br><small class="text-muted"><a href="/publish-request/receipt/{{ $row->token }}" target="_blank">{{ __('Open submitter receipt') }}</a></small>
        </dd>
      </dl>
    </div>
  </div>

  <form action="{{ route('publish-requests.decision', $row->id) }}" method="POST" class="card shadow-sm">
    @csrf
    <div class="card-header">{{ __('Decision') }}</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label" for="status">{{ __('Status') }}</label>
        <select name="status" id="status" class="form-select" required>
          @foreach($statuses as $code => $label)
            <option value="{{ $code }}" {{ $row->status === $code ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label" for="curator_notes">{{ __('Curator notes') }}</label>
        <textarea name="curator_notes" id="curator_notes" rows="4" class="form-control">{{ old('curator_notes', $row->curator_notes) }}</textarea>
        <div class="form-text">{{ __('Visible to the submitter on the receipt page.') }}</div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="message_text">{{ __('Edited message (only used when status = edited)') }}</label>
        <textarea name="message_text" id="message_text" rows="4" class="form-control">{{ old('message_text', $row->message_text) }}</textarea>
      </div>
    </div>
    <div class="card-footer text-end">
      <a href="{{ route('publish-requests.inbox') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
      <button type="submit" class="btn btn-primary">{{ __('Record decision') }}</button>
    </div>
  </form>
@endsection
