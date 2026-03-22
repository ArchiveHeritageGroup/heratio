@extends('theme::layouts.1col')

@section('title', 'Upload Finding Aid')

@section('content')

  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">Upload finding aid</h1>
    <span class="small" id="heading-label">{{ $io->title ?? '' }}</span>
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('informationobject.findingaid.upload', $io->slug) }}" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="file-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#file-collapse" aria-expanded="true">
            Select file
          </button>
        </h2>
        <div id="file-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="finding-aid-file" class="form-label">Select a PDF or RTF file to upload <span class="badge bg-secondary ms-1">Optional</span></label>
              <input class="form-control" type="file" id="finding-aid-file" name="file" accept=".pdf,.rtf">
            </div>
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <input class="btn atom-btn-outline-success" type="submit" value="Upload">
      <a href="{{ route('informationobject.show', $io->slug) }}" class="btn atom-btn-outline-light ms-2">Cancel</a>
    </section>

  </form>

@endsection
