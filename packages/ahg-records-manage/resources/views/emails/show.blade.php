{{--
  Records Management — Email detail + classify + declare (P2.6)
  @copyright Johan Pieterse / Plain Sailing Information Systems
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Email #' . $email->id)
@section('body-class', 'admin records emails show')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0">
    <i class="fas fa-envelope me-2"></i>{{ \Illuminate\Support\Str::limit($email->subject ?: '[No subject]', 80) }}
    <span class="badge bg-{{ $email->status === 'declared' ? 'success' : ($email->status === 'classified' ? 'info text-dark' : 'warning text-dark') }} ms-2">{{ $email->status }}</span>
  </h1>
  <a href="{{ route('records.emails.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-list me-1"></i>{{ __('Queue') }}</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row">
  <div class="col-md-7">
    <div class="card mb-3">
      <div class="card-header bg-light">Headers</div>
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:30%">{{ __('Message-ID') }}</th><td><code class="small">{{ $email->message_id }}</code></td></tr>
        <tr><th class="text-muted">{{ __('From') }}</th><td>{{ $email->from_address }}</td></tr>
        <tr><th class="text-muted">{{ __('To') }}</th><td><small>{{ $email->to_addresses }}</small></td></tr>
        @if($email->cc_addresses)<tr><th class="text-muted">{{ __('CC') }}</th><td><small>{{ $email->cc_addresses }}</small></td></tr>@endif
        <tr><th class="text-muted">{{ __('Subject') }}</th><td>{{ $email->subject }}</td></tr>
        <tr><th class="text-muted">{{ __('Sent') }}</th><td>{{ $email->sent_at }}</td></tr>
        @if($email->received_at)<tr><th class="text-muted">{{ __('Received') }}</th><td>{{ $email->received_at }}</td></tr>@endif
        <tr><th class="text-muted">{{ __('Attachments') }}</th><td>{{ $email->attachment_count }}</td></tr>
        <tr><th class="text-muted">{{ __('Source') }}</th><td>{{ $email->capture_source }}</td></tr>
        @if($email->information_object_id)
          <tr><th class="text-muted">{{ __('Declared as') }}</th><td><a href="/admin/information-object/{{ $email->information_object_id }}">information_object #{{ $email->information_object_id }}</a></td></tr>
        @endif
        @if($email->eml_storage_path)
          <tr><th class="text-muted">{{ __('EML on disk') }}</th><td><code class="small">{{ $email->eml_storage_path }}</code></td></tr>
        @endif
      </table>
    </div>

    <div class="card mb-3">
      <div class="card-header bg-light">Body</div>
      <div class="card-body small" style="max-height:400px;overflow-y:auto;white-space:pre-wrap;font-family:ui-monospace,monospace;">@if($email->body_text){{ $email->body_text }}@elseif($email->body_html){!! strip_tags($email->body_html) !!}@else<em class="text-muted">(empty body)</em>@endif</div>
    </div>
  </div>

  <div class="col-md-5">
    <div class="card border-primary mb-3">
      <div class="card-header bg-primary text-white"><i class="fas fa-tags me-1"></i> {{ __('Classify') }}</div>
      <div class="card-body">
        @if($email->fileplan_code)
          <div class="mb-2 small text-muted">Currently classified to <strong>{{ $email->fileplan_code }} — {{ $email->fileplan_title }}</strong>@if($email->disposal_class_ref) under disposal class <code>{{ $email->disposal_class_ref }}</code>@endif</div>
        @endif
        <form method="POST" action="{{ route('records.emails.classify', $email->id) }}">
          @csrf
          <div class="mb-2">
            <label class="form-label small mb-1">{{ __('File plan node') }}</label>
            <select name="fileplan_node_id" class="form-select form-select-sm" required>
              <option value="">— pick a node —</option>
              @foreach($fileplanNodes as $n)<option value="{{ $n->id }}" @selected($email->fileplan_node_id == $n->id)>{{ str_repeat('— ', $n->depth) }}{{ $n->code }} — {{ $n->title }}</option>@endforeach
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">{{ __('Disposal class (optional)') }}</label>
            <select name="disposal_class_id" class="form-select form-select-sm">
              <option value="">— inherit from node —</option>
              @foreach($disposalClasses as $dc)<option value="{{ $dc->id }}" @selected($email->disposal_class_id == $dc->id)>{{ $dc->class_ref }} — {{ $dc->title }}</option>@endforeach
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-tags me-1"></i>{{ __('Save classification') }}</button>
        </form>
      </div>
    </div>

    @if(! $email->information_object_id)
    <div class="card border-success">
      <div class="card-header bg-success text-white"><i class="fas fa-flag me-1"></i> {{ __('Declare as record') }}</div>
      <div class="card-body small">
        <p>Declares this email as an <code>information_object</code>. The record becomes part of the archival catalogue and the disposal class (if classified) is applied. The <em>Declare</em> action is irreversible without admin intervention.</p>
        <form method="POST" action="{{ route('records.emails.declare', $email->id) }}" onsubmit="return confirm('Declare this email as a record? It will become part of the archival catalogue.');">
          @csrf
          <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-flag me-1"></i>{{ __('Declare as record') }}</button>
        </form>
      </div>
    </div>
    @endif
  </div>
</div>
@endsection
