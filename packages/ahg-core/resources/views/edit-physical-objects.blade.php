@extends('theme::layouts.1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}
    </h1>
    <span class="small" id="heading-label">
      {{ __(
          'Link %1%',
          ['%1%' => config('app.ui_label_physicalobject', __('Physical storage'))]
      ) }}
    </span>
  </div>
@endsection

@section('content')
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ $formAction ?? '/informationobject/' . ($resource->slug ?? '') . '/editPhysicalObjects' }}">
    @csrf

    @if(isset($relations) && count($relations) > 0)
      <div class="table-responsive mb-3">
        <table class="table table-bordered mb-0">
          <thead>
	    <tr>
              <th class="w-100">
                {{ __('Containers') }}
              </th>
              <th>
                <span class="visually-hidden">
                  {{ __('Actions') }}
                </span>
              </th>
            </tr>
          </thead>
          <tbody>
            @foreach($relations as $item)
              <tr>
                <td>
                  {{ $item->subject->label ?? $item->subject->name ?? '' }}
                </td>
                <td class="text-nowrap">
                  <a class="btn atom-btn-white me-1" href="{{ route('physicalobject.edit', $item->subject->slug ?? $item->subject->id ?? '') }}">
                    <i class="fas fa-fw fa-pencil-alt" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('Edit row') }}</span>
                  </a>
                  <button
                    type="button"
                    class="btn atom-btn-white delete-physical-storage"
                    id="/relation/{{ $item->id ?? '' }}">
                    <i class="fas fa-fw fa-times" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('Delete row') }}</span>
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    <div class="accordion mb-3">
      <div class="accordion-item{{ isset($relations) && count($relations) ? ' rounded-0' : '' }}">
        <h2 class="accordion-header" id="add-heading">
          <button
            class="accordion-button{{ isset($relations) && count($relations) ? ' rounded-0' : '' }}"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#add-collapse"
            aria-expanded="true"
            aria-controls="add-collapse">
            {{ __('Add container links (duplicate links will be ignored)') }}
          </button>
        </h2>
        <div
          id="add-collapse"
          class="accordion-collapse collapse show"
          aria-labelledby="add-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label" for="containers">{{ __('Containers') }}</label>
              <input class="form-control form-autocomplete" type="text" name="containers" id="containers" value="">
              <input class="add" type="hidden" data-link-existing="false" value="{{ $formAction ?? '/informationobject/' . ($resource->slug ?? '') . '/editPhysicalObjects' }} #name">
              <input class="list" type="hidden" value="{{ route('physicalobject.autocomplete') }}">
            </div>
          </div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="create-heading">
          <button
            class="accordion-button collapsed"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#create-collapse"
            aria-expanded="false"
            aria-controls="create-collapse">
            {{ __('Or, create a new container') }}
          </button>
        </h2>
        <div
          id="create-collapse"
          class="accordion-collapse collapse"
          aria-labelledby="create-heading">
          <div class="accordion-body">
            <div class="form-item">
              <div class="mb-3">
                <label class="form-label" for="name">{{ __('Name') }}</label>
                <input class="form-control" type="text" name="name" id="name" value="">
              </div>
              <div class="mb-3">
                <label class="form-label" for="location">{{ __('Location') }}</label>
                <input class="form-control" type="text" name="location" id="location" value="">
              </div>
              <div class="mb-3">
                <label class="form-label" for="type">{{ __('Type') }}</label>
                <select class="form-select" name="type" id="type">
                  @foreach($physicalObjectTypes ?? [] as $typeId => $typeName)
                    <option value="{{ $typeId }}">{{ $typeName }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li>
        <a href="{{ isset($resource->slug) ? route('informationobject.show', $resource->slug) : url()->previous() }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a>
      </li>
      <li>
        <input
          class="btn atom-btn-outline-success"
          type="submit"
          value="{{ __('Save') }}">
      </li>
    </ul>
  </form>
@endsection
