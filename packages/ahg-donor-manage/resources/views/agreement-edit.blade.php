@extends('theme::layouts.1col')
@section('title', 'Edit Agreement')
@section('body-class', 'edit')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-file-signature me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">Edit Agreement: {{ $record->title ?? $record->agreement_number ?? '' }}</h1></div></div>
  <div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-edit me-2"></i>Edit Agreement</div>
  <div class="card-body"><form method="POST" action="{{ $formAction }}" enctype="multipart/form-data">@csrf
    @include('ahg-donor-manage::_agreement-form')
    <div class="d-flex gap-2 mt-3"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> Save</button><a href="{{ route('donor.agreement.view', $record->id) }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> Cancel</a></div>
  </form></div></div>

  @if($documents->isNotEmpty())
  <div class="card mt-3"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-paperclip me-2"></i>Documents</div>
  <div class="card-body"><table class="table table-sm table-striped mb-0">
    <thead><tr><th>Filename</th><th>Type</th><th>Size</th><th>Uploaded</th></tr></thead>
    <tbody>
    @foreach($documents as $doc)
      <tr><td>{{ $doc->original_filename ?? $doc->filename }}</td><td>{{ $doc->document_type ?? '-' }}</td><td>{{ number_format(($doc->file_size ?? 0) / 1024, 1) }} KB</td><td>{{ $doc->created_at ?? '-' }}</td></tr>
    @endforeach
    </tbody>
  </table></div></div>
  @endif

  @if($linkedRecords->isNotEmpty())
  <div class="card mt-3"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-link me-2"></i>Linked Records</div>
  <div class="card-body"><table class="table table-sm table-striped mb-0">
    <thead><tr><th>Identifier</th><th>Title</th></tr></thead>
    <tbody>
    @foreach($linkedRecords as $lr)
      <tr><td>{{ $lr->identifier ?? '-' }}</td><td><a href="{{ $lr->slug ? url('/'.$lr->slug) : '#' }}">{{ $lr->title ?? '-' }}</a></td></tr>
    @endforeach
    </tbody>
  </table></div></div>
  @endif

  @if($linkedAccessions->isNotEmpty())
  <div class="card mt-3"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-archive me-2"></i>Linked Accessions</div>
  <div class="card-body"><table class="table table-sm table-striped mb-0">
    <thead><tr><th>Identifier</th><th>Title</th></tr></thead>
    <tbody>
    @foreach($linkedAccessions as $la)
      <tr><td>{{ $la->identifier ?? '-' }}</td><td>{{ $la->title ?? '-' }}</td></tr>
    @endforeach
    </tbody>
  </table></div></div>
  @endif
@endsection
