@extends('theme::layouts.1col')

@section('title')
  <h1>{{ $actor->authorized_form_of_name }}</h1>
@endsection

@section('content')

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-header">
      <h2 class="h5 mb-0">{{ __('Link digital object') }}</h2>
    </div>
    <div class="card-body">
      <form action="{{ route('actor.digitalobject.store', $actor->slug) }}" method="post" enctype="multipart/form-data">
        @csrf

        <div class="mb-3">
          <label for="digital_object" class="form-label">{{ __('File') }}</label>
          <input type="file" class="form-control" id="digital_object" name="digital_object" required>
          <div class="form-text">
            {{ __('An authority record holds a single digital object — typically a portrait or a logo. Delete the existing one before attaching another.') }}
          </div>
        </div>

        <div class="alert alert-warning small">
          {{ __('Digital objects attached here are served publicly unless a rights policy is attached. Do not upload restricted material.') }}
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="fas fa-upload me-1"></i>{{ __('Upload') }}
        </button>
        <a href="{{ route('actor.show', $actor->slug) }}" class="btn atom-btn-outline-secondary">{{ __('Cancel') }}</a>
      </form>
    </div>
  </div>

@endsection
