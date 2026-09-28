{{--
  Move one physical object between storage locations, or out of storage
  (heratio#1514). The move is recorded in ahg_storage_movement, which is
  append-only: a move entered wrongly is corrected by another move.
--}}
@extends('theme::layouts.1col')

@section('title', __('Move').': '.($storage->name ?? __('Physical object')))
@section('body-class', 'move physicalobject')

@section('content')
<div class="container-fluid">

  <h1 class="mb-3">{{ __('Move') }}: {{ $storage->name ?? '#'.$storage->id }}</h1>

  <div class="card mb-3">
    <div class="card-body">
      <p class="mb-0">
        {{ __('Currently') }}:
        @if($currentLocationId === null)
          <span class="text-muted">{{ __('not in storage') }}</span>
        @else
          @foreach($locations as $option)
            @if((int) $option->id === (int) $currentLocationId)
              <a href="{{ route('storagelocation.show', $option->slug) }}">{{ $option->name }}</a>
            @endif
          @endforeach
        @endif
      </p>
    </div>
  </div>

  <form method="POST" action="{{ route('physicalobject.move.store', $storage->slug) }}" class="card">
    @csrf
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label" for="to_location_id">{{ __('Move to') }}</label>
        <select class="form-select @error('to_location_id') is-invalid @enderror" id="to_location_id" name="to_location_id">
          <option value="">{{ __('Choose a location') }}</option>
          @foreach($locations as $option)
            <option value="{{ $option->id }}"
                    @disabled((int) $option->id === (int) $currentLocationId)
                    @selected((int) old('to_location_id') === (int) $option->id)>
              {{ str_repeat('- ', (int) $option->level).$option->name }}
              @if((int) $option->id === (int) $currentLocationId) ({{ __('already here') }}) @endif
            </option>
          @endforeach
        </select>
        @error('to_location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="remove_from_storage" name="remove_from_storage" value="1"
               @checked(old('remove_from_storage'))>
        <label class="form-check-label" for="remove_from_storage">
          {{ __('Remove from storage') }}
          <span class="text-muted">- {{ __('the object leaves storage entirely rather than moving to another location') }}</span>
        </label>
      </div>

      <div class="mb-3">
        <label class="form-label" for="note">{{ __('Note') }}</label>
        <textarea class="form-control" id="note" name="note" rows="2" maxlength="2000"
                  placeholder="{{ __('Why it moved (optional)') }}">{{ old('note') }}</textarea>
      </div>
    </div>

    <div class="card-footer">
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fas fa-dolly me-1"></i>{{ __('Record move') }}
      </button>
      <a href="{{ route('physicalobject.show', $storage->slug) }}" class="btn btn-outline-secondary btn-sm">{{ __('Cancel') }}</a>
    </div>
  </form>

</div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // A destination and "remove from storage" are alternatives, not a pair.
    var remove = document.getElementById('remove_from_storage');
    var destination = document.getElementById('to_location_id');
    if (remove && destination) {
        var sync = function () {
            destination.disabled = remove.checked;
            if (remove.checked) { destination.value = ''; }
        };
        remove.addEventListener('change', sync);
        sync();
    }
});
</script>
@endpush
