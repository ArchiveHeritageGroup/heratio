@extends('theme::layouts.1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ $storage ? 'Edit' : 'Create' }} physical storage</h1>
    @if($storage)
      <span class="small">{{ $storage->name }}</span>
    @endif
  </div>
@endsection

@section('content')
  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <form method="POST" action="{{ $storage ? route('physicalobject.update', $storage->slug) : route('physicalobject.store') }}">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#identity-collapse" aria-expanded="true">Identity area</button></h2>
        <div id="identity-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="name" class="form-control" required
                     value="{{ old('name', $storage->name ?? '') }}">
            </div>

            <div class="mb-3">
              <label for="type_id" class="form-label">Type</label>
              <select name="type_id" id="type_id" class="form-select">
                <option value="">- Select type -</option>
                @foreach($typeChoices as $tid => $tname)
                  <option value="{{ $tid }}" {{ old('type_id', $storage->type_id ?? '') == $tid ? 'selected' : '' }}>{{ $tname }}</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="location" class="form-label">Location</label>
              <textarea name="location" id="location" class="form-control" rows="3">{{ old('location', $storage->location ?? '') }}</textarea>
            </div>

            <div class="mb-3">
              <label for="description" class="form-label">Description</label>
              <textarea name="description" id="description" class="form-control" rows="4">{{ old('description', $storage->description ?? '') }}</textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($storage)
        <li><a href="{{ route('physicalobject.show', $storage->slug) }}" class="btn btn-outline-secondary">Cancel</a></li>
        <li><input class="btn btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a href="{{ route('physicalobject.browse') }}" class="btn btn-outline-secondary">Cancel</a></li>
        <li><input class="btn btn-outline-success" type="submit" value="Create"></li>
      @endif
    </ul>
  </form>
@endsection
