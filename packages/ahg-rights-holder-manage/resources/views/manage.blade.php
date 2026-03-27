@extends('theme::layouts.1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}
    </h1>
    <span class="small" id="heading-label">
      {{ __('Manage rights inheritance') }}
    </span>
  </div>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <p>{{ $error }}</p>
      @endforeach
    </div>
  @endif

  <form method="POST" action="{{ route('rightsholder.manageStore', ['slug' => $resource->slug]) }}">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="inheritance-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#inheritance-collapse" aria-expanded="true" aria-controls="inheritance-collapse">
            Inheritance options
          </button>
        </h2>
        <div id="inheritance-collapse" class="accordion-collapse collapse show" aria-labelledby="inheritance-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="all_or_digital_only" class="form-label">All descendants or just digital objects</label>
              <select name="all_or_digital_only" id="all_or_digital_only" class="form-select">
                <option value="all" {{ old('all_or_digital_only', $allOrDigitalOnly ?? 'all') === 'all' ? 'selected' : '' }}>All descendants</option>
                <option value="digital_only" {{ old('all_or_digital_only', $allOrDigitalOnly ?? '') === 'digital_only' ? 'selected' : '' }}>Digital objects only</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="overwrite_or_combine" class="form-label">Overwrite or combine rights</label>
              <select name="overwrite_or_combine" id="overwrite_or_combine" class="form-select">
                <option value="combine" {{ old('overwrite_or_combine', $overwriteOrCombine ?? 'combine') === 'combine' ? 'selected' : '' }}>Combine</option>
                <option value="overwrite" {{ old('overwrite_or_combine', $overwriteOrCombine ?? '') === 'overwrite' ? 'selected' : '' }}>Overwrite</option>
              </select>
              <div class="form-text">Set if you want to combine the current set of rights with any existing rights, or remove the existing rights and apply these new rights.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('informationobject.show', ['slug' => $resource->slug]) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="Apply"></li>
    </ul>

  </form>

@endsection
