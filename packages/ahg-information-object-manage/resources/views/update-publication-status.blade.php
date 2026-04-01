@extends('theme::layout_2col')

@section('sidebar')
  @include('ahg-information-object-manage::_context-menu')
@endsection

@section('title')

  <h1>{{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}</h1>

@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $e)
        <p>{{ $e }}</p>
      @endforeach
      @if(session('workflow_start_url'))
        <a href="{{ session('workflow_start_url') }}" class="btn btn-sm atom-btn-outline-success mt-2">
          <i class="fas fa-tasks me-1"></i>{{ __('Go to Workflow Dashboard') }}
        </a>
      @endif
    </div>
  @endif

  <form
    action="{{ route('io.updateStatus', $resource->slug) }}"
    method="post"
    id="update-publication-status-form"
    data-cy="update-publication-status-form">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="pub-status-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#pub-status-collapse" aria-expanded="true" aria-controls="pub-status-collapse">
            {{ __('Update publication status') }}
          </button>
        </h2>
        <div id="pub-status-collapse" class="accordion-collapse collapse show" aria-labelledby="pub-status-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="publicationStatus" class="form-label">{{ __('Publication status') }}</label>
              <select class="form-select" id="publicationStatus" name="publicationStatus">
                @foreach($publicationStatuses ?? [] as $id => $name)
                  <option value="{{ $id }}" {{ old('publicationStatus', $currentStatus ?? '') == $id ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
              </select>
            </div>

            @if(($resource->rgt ?? 0) - ($resource->lft ?? 0) > 1)
              <div class="mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="updateDescendants" name="updateDescendants" value="1" {{ old('updateDescendants') ? 'checked' : '' }}>
                  <label class="form-check-label" for="updateDescendants">{{ __('Update descendants') }}</label>
                </div>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('informationobject.show', $resource->slug) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Update') }}"></li>
    </ul>

  </form>

@endsection
