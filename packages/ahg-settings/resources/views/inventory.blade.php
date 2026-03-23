@extends('theme::layouts.1col')
@section('title', 'Inventory')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Inventory</h1>

    <form method="post" action="{{ route('settings.inventory') }}">
      @csrf

      <div class="accordion mb-3" id="inventoryAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="inventory-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#inventory-collapse" aria-expanded="false" aria-controls="inventory-collapse">
              Inventory settings
            </button>
          </h2>
          <div id="inventory-collapse" class="accordion-collapse collapse" aria-labelledby="inventory-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Levels of description <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="settings[levels][]" class="form-select" multiple size="{{ max(count($levels), 4) }}">
                  @foreach ($levels as $level)
                    <option value="{{ $level->id }}" {{ in_array($level->id, $selectedLevels) ? 'selected' : '' }}>{{ $level->name }}</option>
                  @endforeach
                </select>
                <small class="text-muted">Select the levels of description to be included in the inventory list. If no levels are selected, the inventory list link will not be displayed. You can use the control (Mac &#8984;) and/or shift keys to multi-select values from the Levels of description menu.</small>
              </div>

              <br>
              <a href="{{ route('taxonomy.browse', ['taxonomy' => 34]) }}">Review the current terms in the Levels of description taxonomy.</a>
            </div>
          </div>
        </div>
      </div>

      <section class="actions mb-3">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>

    </form>
  </div>
</div>
@endsection
