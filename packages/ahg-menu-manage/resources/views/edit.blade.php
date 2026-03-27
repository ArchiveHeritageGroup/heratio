@extends('theme::layouts.1col')

@section('title')
  @if($menu && isset($menu->id))
    <div class="multiline-header d-flex flex-column mb-3">
      <h1 class="mb-0" aria-describedby="heading-label">
        {{ __('Edit menu') }}
      </h1>
      <span class="small" id="heading-label">
        {{ $menu->name ?? '' }}
      </span>
    </div>
  @else
    <h1>{{ __('Add new menu') }}</h1>
  @endif
@endsection

@section('content')

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if($menu && isset($menu->id))
    <form method="POST" action="{{ route('menu.update', $menu->id) }}">
  @else
    <form method="POST" action="{{ route('menu.store') }}">
  @endif
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="edit-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#edit-collapse" aria-expanded="true" aria-controls="edit-collapse">
            {{ __('Main area') }}
          </button>
        </h2>
        <div id="edit-collapse" class="accordion-collapse collapse show" aria-labelledby="edit-heading">
          <div class="accordion-body">
            @if(!($menu->isProtected ?? false))
              <div class="form-item mb-3">
                <label for="name" class="form-label">{{ __('Name') }}</label>
                <input type="text" name="name" id="name" class="form-control"
                       value="{{ old('name', $menu->name ?? '') }}">
                <div class="form-text">{{ __('Provide an internal menu name.  This is not visible to users.') }}</div>
              </div>
            @endif

            <div class="form-item mb-3">
              <label for="label" class="form-label">{{ __('Label') }}</label>
              <input type="text" name="label" id="label" class="form-control"
                     value="{{ old('label', $menu->label ?? '') }}">
              <div class="form-text">{{ __('Provide a menu label for users.  For menu items that are not visible (i.e. are organizational only) this should be left blank.') }}</div>
            </div>

            <div class="form-item mb-3">
              <label for="parent_id" class="form-label">{{ __('Parent') }}</label>
              <select name="parent_id" id="parent_id" class="form-select">
                @foreach($parentChoices as $choiceId => $choiceLabel)
                  <option value="{{ $choiceId }}"
                    @selected(old('parent_id', $menu->parentId ?? \AhgMenuManage\Services\MenuService::ROOT_ID) == $choiceId)>
                    {{ $choiceLabel }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="form-item mb-3">
              <label for="path" class="form-label">{{ __('Path') }}</label>
              <input type="text" name="path" id="path" class="form-control"
                     value="{{ old('path', $menu->path ?? '') }}">
              <div class="form-text">{{ __('Provide a link to an external website or an internal, symfony path (module/action).') }}</div>
            </div>

            <div class="form-item mb-3">
              <label for="description" class="form-label">{{ __('Description') }}</label>
              <textarea name="description" id="description" class="form-control" rows="4">{{ old('description', $menu->description ?? '') }}</textarea>
              <div class="form-text">{{ __("Provide a brief description of the menu and it's purpose.") }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('menu.browse') }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      @if(!($menu->isProtected ?? false) && isset($menu->id))
        <li><a href="{{ route('menu.confirmDelete', $menu->id) }}" class="btn atom-btn-outline-danger" role="button">{{ __('Delete') }}</a></li>
      @endif
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
    </ul>

  </form>

@endsection
