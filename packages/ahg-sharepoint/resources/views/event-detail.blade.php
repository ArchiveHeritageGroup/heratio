@extends('layouts.app')
@section('title', sprintf(__('SharePoint event #%d'), (int) $event->id))
@section('content')
<h1>{{ sprintf(__('SharePoint event #%d'), (int) $event->id) }}</h1>

<dl class="row">
    <dt class="col-sm-3">{{ __('Received') }}</dt><dd class="col-sm-9">{{ $event->received_at }}</dd>
    <dt class="col-sm-3">{{ __('Processed') }}</dt><dd class="col-sm-9">{{ $event->processed_at ?? '—' }}</dd>
    <dt class="col-sm-3">{{ __('Status') }}</dt><dd class="col-sm-9"><code>{{ $event->status }}</code></dd>
    <dt class="col-sm-3">{{ __('Attempts') }}</dt><dd class="col-sm-9">{{ $event->attempts }}</dd>
    <dt class="col-sm-3">{{ __('Drive') }}</dt><dd class="col-sm-9">{{ $event->drive_id }}</dd>
    <dt class="col-sm-3">{{ __('SP item') }}</dt><dd class="col-sm-9 small text-muted">{{ $event->sp_item_id ?? '—' }}</dd>
    <dt class="col-sm-3">{{ __('eTag') }}</dt><dd class="col-sm-9 small text-muted">{{ $event->sp_etag ?? '—' }}</dd>
    <dt class="col-sm-3">{{ __('Heratio info_object') }}</dt><dd class="col-sm-9">{{ $event->information_object_id ?? '—' }}</dd>
</dl>

@if (!empty($event->last_error))
    <div class="alert alert-danger"><strong>{{ __('Last error') }}:</strong> {{ $event->last_error }}</div>
@endif

<h3 class="mt-4">{{ __('Raw payload') }}</h3>
<pre class="bg-light p-3 small">{{ is_string($event->raw_payload) ? $event->raw_payload : json_encode($event->raw_payload, JSON_PRETTY_PRINT) }}</pre>

<form method="post" class="mt-3">
    @csrf
    <input type="hidden" name="form_action" value="retry">
    <button type="submit" class="btn btn-warning">
        <i class="fa fa-redo me-1"></i>{{ __('Re-queue this event') }}
    </button>
    <a href="{{ route('sharepoint.events') }}" class="btn btn-link">{{ __('Back to event log') }}</a>
</form>
@endsection
