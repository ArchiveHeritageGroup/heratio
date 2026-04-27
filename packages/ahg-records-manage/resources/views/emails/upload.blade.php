{{--
  Records Management — Upload .eml form (P2.6)
  @copyright Johan Pieterse / Plain Sailing Information Systems
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Capture an Email')
@section('body-class', 'admin records emails upload')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-upload me-2"></i> Capture an Email</h1>
  <a href="{{ route('records.emails.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to queue</a>
</div>

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row">
  <div class="col-md-7">
    <div class="card">
      <div class="card-body">
        <form method="POST" action="{{ route('records.emails.upload') }}" enctype="multipart/form-data">
          @csrf
          <div class="mb-3">
            <label class="form-label">EML file</label>
            <input type="file" name="eml_file" class="form-control" accept=".eml,message/rfc822" required>
            <div class="form-text small">Up to 50 MB. The original file is preserved verbatim under <code>{{ rtrim(config('heratio.storage_path', '/storage'), '/') }}/rm/email-capture/</code>.</div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>Capture</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card border-info">
      <div class="card-header bg-info text-white"><i class="fas fa-info-circle me-1"></i> What happens</div>
      <div class="card-body small">
        <ol class="mb-0 ps-3">
          <li>EML is parsed (headers + body, multipart text/html, attachment counts).</li>
          <li>Original file is saved under the configured storage path for forensic preservation.</li>
          <li>A row is written to <code>rm_email_capture</code> with the parsed values.</li>
          <li>Duplicate detection by <code>Message-ID</code> — re-uploading is idempotent.</li>
          <li>You then classify the email to a file plan node and optionally declare it as a record.</li>
        </ol>
      </div>
    </div>
  </div>
</div>
@endsection
