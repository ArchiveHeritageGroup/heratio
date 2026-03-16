{{-- Other names: Parallel, Standardized, Other forms (ISAAR 5.1.3, 5.1.4, 5.1.5) --}}
<div id="other-names-container">
  @foreach($otherNames as $index => $name)
    <div class="other-name-entry card mb-2" data-index="{{ $index }}">
      <div class="card-body py-2">
        <div class="row align-items-end">
          <div class="col-md-5 mb-2">
            <label class="form-label small">Name</label>
            <input type="text" name="other_names[{{ $index }}][name]" class="form-control form-control-sm"
                   value="{{ $name->name ?? '' }}">
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label small">Type</label>
            <select name="other_names[{{ $index }}][type_id]" class="form-select form-select-sm">
              <option value="">-- Select --</option>
              @foreach($nameTypes as $type)
                <option value="{{ $type->id }}" @selected(($name->type_id ?? '') == $type->id)>{{ $type->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 mb-2">
            <label class="form-label small">Note</label>
            <input type="text" name="other_names[{{ $index }}][note]" class="form-control form-control-sm"
                   value="{{ $name->note ?? '' }}">
          </div>
          <div class="col-md-1 mb-2 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger remove-other-name"><i class="bi bi-trash"></i></button>
          </div>
        </div>
      </div>
    </div>
  @endforeach
</div>

<div class="mb-3">
  <button type="button" id="add-other-name" class="btn btn-sm btn-outline-primary">
    <i class="bi bi-plus-circle me-1"></i>Add other name
  </button>
  <div class="form-text">"Record parallel, standardized, or other forms of name." (ISAAR 5.1.3–5.1.5)</div>
</div>

<template id="other-name-template">
  <div class="other-name-entry card mb-2" data-index="__INDEX__">
    <div class="card-body py-2">
      <div class="row align-items-end">
        <div class="col-md-5 mb-2">
          <label class="form-label small">Name</label>
          <input type="text" name="other_names[__INDEX__][name]" class="form-control form-control-sm">
        </div>
        <div class="col-md-4 mb-2">
          <label class="form-label small">Type</label>
          <select name="other_names[__INDEX__][type_id]" class="form-select form-select-sm">
            <option value="">-- Select --</option>
            @foreach($nameTypes as $type)
              <option value="{{ $type->id }}">{{ $type->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 mb-2">
          <label class="form-label small">Note</label>
          <input type="text" name="other_names[__INDEX__][note]" class="form-control form-control-sm">
        </div>
        <div class="col-md-1 mb-2 text-end">
          <button type="button" class="btn btn-sm btn-outline-danger remove-other-name"><i class="bi bi-trash"></i></button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('other-names-container');
  const addBtn = document.getElementById('add-other-name');
  const template = document.getElementById('other-name-template');

  function getNextIndex() {
    const entries = container.querySelectorAll('.other-name-entry');
    let maxIndex = -1;
    entries.forEach(function(entry) {
      const idx = parseInt(entry.dataset.index, 10);
      if (idx > maxIndex) maxIndex = idx;
    });
    return maxIndex + 1;
  }

  addBtn.addEventListener('click', function() {
    let html = template.innerHTML.replace(/__INDEX__/g, getNextIndex());
    const div = document.createElement('div');
    div.innerHTML = html;
    container.appendChild(div.firstElementChild);
  });

  container.addEventListener('click', function(e) {
    if (e.target.closest('.remove-other-name')) {
      e.target.closest('.other-name-entry').remove();
    }
  });
});
</script>
