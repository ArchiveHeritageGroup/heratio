@extends('theme::layouts.1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">
      @if($menu)
        Edit menu item
      @else
        Add menu item
      @endif
    </h1>
    @if($menu)
      <span class="small">{{ $menu->label ?: $menu->name ?: 'Menu #' . $menu->id }}</span>
    @endif
  </div>
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

  @if($menu && $menu->isProtected)
    <div class="alert alert-warning">
      <i class="fas fa-shield-alt me-1"></i>
      This is a protected menu item. The internal name cannot be changed.
    </div>
  @endif

  <form method="POST"
        action="{{ $menu ? route('menu.update', $menu->id) : route('menu.store') }}"
        id="editForm">
    @csrf

    <div class="card mb-3">
      <div class="card-body">
        <div class="mb-3">
          <label for="label" class="form-label">
            Label <span class="form-required text-danger" title="This is a mandatory element.">*</span> <span class="badge bg-danger ms-1">Required</span></label>
          <input type="text" name="label" id="label" class="form-control" required
                 value="{{ old('label', $menu->label ?? '') }}">
          <div class="form-text">The display label for this menu item.</div>
        </div>

        <div class="mb-3">
          <label for="name" class="form-label">Name <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="text" name="name" id="name" class="form-control"
                 value="{{ old('name', $menu->name ?? '') }}"
                 @if($menu && $menu->isProtected) readonly @endif>
          <div class="form-text">Internal identifier for the menu item.{{ $menu && $menu->isProtected ? ' Cannot be changed for protected menus.' : '' }}</div>
        </div>

        <div class="mb-3">
          <label for="path" class="form-label">Path <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="text" name="path" id="path" class="form-control"
                 value="{{ old('path', $menu->path ?? '') }}">
          <div class="form-text">The URL path for this menu item (e.g. /informationobject/browse).</div>
        </div>

        <div class="mb-3">
          <label for="parent_id" class="form-label">Parent <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="parent_id" id="parent_id" class="form-select">
            @foreach($parentChoices as $choiceId => $choiceLabel)
              <option value="{{ $choiceId }}"
                @selected(old('parent_id', $menu->parentId ?? \AhgMenuManage\Services\MenuService::ROOT_ID) == $choiceId)>
                {{ $choiceLabel }}
              </option>
            @endforeach
          </select>
          <div class="form-text">Select the parent menu item.</div>
        </div>

        <div class="mb-3">
          <label for="description" class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
          <textarea name="description" id="description" class="form-control" rows="4">{{ old('description', $menu->description ?? '') }}</textarea>
          <div class="form-text">An optional description for this menu item.</div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($menu)
        <li><a href="{{ route('menu.show', $menu->id) }}" class="btn atom-btn-white">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a href="{{ route('menu.browse') }}" class="btn atom-btn-white">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
      @endif
    </ul>
  </form>

@endsection
