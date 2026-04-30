@extends('theme::layouts.2col')

@section('title', $io->title ?? 'Upload Finding Aid')

@section('sidebar')
  {{-- Repository context menu (matching AtoM layout_2col sidebar) --}}
  @if(isset($repository) && $repository)
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-university me-1"></i> {{ $repository->name ?? 'Repository' }}
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('repository.show', $repository->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-eye me-1"></i> View repository
        </a>
        <a href="{{ route('informationobject.browse', ['repository' => $repository->id]) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-list me-1"></i> Browse holdings
        </a>
      </div>
    </div>
  @endif
@endsection

@section('content')

  <h1>{{ $io->title ?? '[Untitled]' }}</h1>

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
        <h2 class="accordion-header" id="load-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#load-collapse" aria-expanded="true" aria-controls="load-collapse">
            {{ __('Upload finding aid') }}
          </button>
        </h2>
        <div id="load-collapse" class="accordion-collapse collapse show" aria-labelledby="load-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="finding-aid-file" class="form-label">PDF file <span class="badge bg-danger ms-1">Required</span></label>
              <input class="form-control" type="file" id="finding-aid-file" name="file" accept=".pdf,.rtf" required>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('informationobject.show', $io->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="Upload"></li>
    </ul>

  </form>

@endsection
