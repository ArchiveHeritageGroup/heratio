@extends('theme::layouts.1col')
@section('title', isset($dropdown) ? 'Edit Dropdown: ' . $dropdown->name : 'Add Dropdown')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-settings::_menu')
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-list me-2"></i>{{ isset($dropdown) && $dropdown->id ? 'Edit' : 'Add' }} Dropdown</h1>

    <form method="post" action="{{ route('settings.dropdown.store', ['id' => $dropdown->id ?? null]) }}">
      @csrf

      <div class="card mb-4">
        <div class="card-header"><i class="fas fa-info-circle me-2"></i>Dropdown Details</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Name <span class="badge bg-danger ms-1">Required</span></label>
            <input type="text" name="name" class="form-control" value="{{ $dropdown->name ?? '' }}" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Slug <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="slug" class="form-control" value="{{ $dropdown->slug ?? '' }}" placeholder="Auto-generated from name">
          </div>
          <div class="mb-3">
            <label class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
            <textarea name="description" class="form-control" rows="2">{{ $dropdown->description ?? '' }}</textarea>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fas fa-list-ol me-2"></i>Values</span>
          <button type="button" class="btn btn-sm atom-btn-white" id="add-value-btn"><i class="fas fa-plus me-1"></i>Add Value</button>
        </div>
        <div class="card-body">
          <div id="dropdown-values">
            @foreach($values ?? [] as $i => $value)
              <div class="input-group mb-2 dropdown-value-row">
                <input type="text" name="values[]" class="form-control" value="{{ $value->value ?? $value }}" placeholder="Value">
                <button type="button" class="btn btn-outline-danger remove-value-btn"><i class="fas fa-times"></i></button>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.dropdown.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>

<script>
document.getElementById('add-value-btn').addEventListener('click', function() {
  var row = document.createElement('div');
  row.className = 'input-group mb-2 dropdown-value-row';
  row.innerHTML = '<input type="text" name="values[]" class="form-control" placeholder="Value"><button type="button" class="btn btn-outline-danger remove-value-btn"><i class="fas fa-times"></i></button>';
  document.getElementById('dropdown-values').appendChild(row);
  row.querySelector('.remove-value-btn').addEventListener('click', function() { row.remove(); });
});
document.querySelectorAll('.remove-value-btn').forEach(function(btn) {
  btn.addEventListener('click', function() { btn.closest('.dropdown-value-row').remove(); });
});
</script>
@endsection
