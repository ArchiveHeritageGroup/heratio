@php
    $titles = ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Prof', 'Rev', 'Hon', 'Sir', 'Dame', 'Adv'];
@endphp

<div id="contacts-container">
@foreach($contacts as $index => $contact)
  <div class="contact-entry" data-index="{{ $index }}">
    <div class="card mb-4">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-telephone me-2"></i>Contact #<span class="contact-number">{{ $index + 1 }}</span></h5>
        <button type="button" class="btn btn-sm btn-outline-light remove-contact" @if($loop->first && $contacts->count() === 1) style="display:none;" @endif>
          <i class="bi bi-trash"></i> Remove
        </button>
      </div>
      <div class="card-body">
        <input type="hidden" name="contacts[{{ $index }}][id]" value="{{ $contact->id ?? '' }}">
        <input type="hidden" name="contacts[{{ $index }}][delete]" value="0" class="delete-flag">

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Contact person</label>
            <input type="text" name="contacts[{{ $index }}][contact_person]" class="form-control"
                   value="{{ $contact->contact_person ?? '' }}">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Contact type</label>
            <input type="text" name="contacts[{{ $index }}][contact_type]" class="form-control"
                   value="{{ $contact->contact_type ?? '' }}"
                   placeholder="e.g., Primary, Business, Home">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Telephone</label>
            <input type="tel" name="contacts[{{ $index }}][telephone]" class="form-control"
                   value="{{ $contact->telephone ?? '' }}">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Fax</label>
            <input type="tel" name="contacts[{{ $index }}][fax]" class="form-control"
                   value="{{ $contact->fax ?? '' }}">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="contacts[{{ $index }}][email]" class="form-control"
                   value="{{ $contact->email ?? '' }}">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Website</label>
          <input type="url" name="contacts[{{ $index }}][website]" class="form-control"
                 value="{{ $contact->website ?? '' }}" placeholder="https://">
        </div>

        <hr>
        <h6 class="text-muted mb-3"><i class="bi bi-geo-alt me-2"></i>Physical location</h6>

        <div class="mb-3">
          <label class="form-label">Street address</label>
          <textarea name="contacts[{{ $index }}][street_address]" class="form-control" rows="2">{{ $contact->street_address ?? '' }}</textarea>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">City</label>
            <input type="text" name="contacts[{{ $index }}][city]" class="form-control"
                   value="{{ $contact->city ?? '' }}">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Region/Province</label>
            <input type="text" name="contacts[{{ $index }}][region]" class="form-control"
                   value="{{ $contact->region ?? '' }}">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Postal code</label>
            <input type="text" name="contacts[{{ $index }}][postal_code]" class="form-control"
                   value="{{ $contact->postal_code ?? '' }}">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Country</label>
            <input type="text" name="contacts[{{ $index }}][country_code]" class="form-control"
                   value="{{ $contact->country_code ?? '' }}" placeholder="e.g. ZA, US, GB">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Latitude</label>
            <input type="text" name="contacts[{{ $index }}][latitude]" class="form-control"
                   value="{{ $contact->latitude ?? '' }}">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Longitude</label>
            <input type="text" name="contacts[{{ $index }}][longitude]" class="form-control"
                   value="{{ $contact->longitude ?? '' }}">
          </div>
        </div>

        <hr>

        <div class="mb-3">
          <label class="form-label">Note</label>
          <textarea name="contacts[{{ $index }}][note]" class="form-control" rows="2">{{ $contact->note ?? '' }}</textarea>
        </div>

        <div class="form-check">
          <input type="checkbox" name="contacts[{{ $index }}][primary_contact]" value="1" class="form-check-input primary-contact-check"
                 @checked(!empty($contact->primary_contact))>
          <label class="form-check-label">Primary contact</label>
        </div>
      </div>
    </div>
  </div>
@endforeach
</div>

<div class="mb-4">
  <button type="button" id="add-contact" class="btn btn-outline-primary">
    <i class="bi bi-plus-circle me-2"></i>Add another contact
  </button>
</div>

<template id="contact-template">
  <div class="contact-entry" data-index="__INDEX__">
    <div class="card mb-4">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-telephone me-2"></i>Contact #<span class="contact-number">__NUMBER__</span></h5>
        <button type="button" class="btn btn-sm btn-outline-light remove-contact">
          <i class="bi bi-trash"></i> Remove
        </button>
      </div>
      <div class="card-body">
        <input type="hidden" name="contacts[__INDEX__][id]" value="">
        <input type="hidden" name="contacts[__INDEX__][delete]" value="0" class="delete-flag">

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Contact person</label>
            <input type="text" name="contacts[__INDEX__][contact_person]" class="form-control">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Contact type</label>
            <input type="text" name="contacts[__INDEX__][contact_type]" class="form-control"
                   placeholder="e.g., Primary, Business, Home">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Telephone</label>
            <input type="tel" name="contacts[__INDEX__][telephone]" class="form-control">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Fax</label>
            <input type="tel" name="contacts[__INDEX__][fax]" class="form-control">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="contacts[__INDEX__][email]" class="form-control">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Website</label>
          <input type="url" name="contacts[__INDEX__][website]" class="form-control" placeholder="https://">
        </div>

        <hr>
        <h6 class="text-muted mb-3"><i class="bi bi-geo-alt me-2"></i>Physical location</h6>

        <div class="mb-3">
          <label class="form-label">Street address</label>
          <textarea name="contacts[__INDEX__][street_address]" class="form-control" rows="2"></textarea>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">City</label>
            <input type="text" name="contacts[__INDEX__][city]" class="form-control">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Region/Province</label>
            <input type="text" name="contacts[__INDEX__][region]" class="form-control">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Postal code</label>
            <input type="text" name="contacts[__INDEX__][postal_code]" class="form-control">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Country</label>
            <input type="text" name="contacts[__INDEX__][country_code]" class="form-control" placeholder="e.g. ZA, US, GB">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Latitude</label>
            <input type="text" name="contacts[__INDEX__][latitude]" class="form-control">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Longitude</label>
            <input type="text" name="contacts[__INDEX__][longitude]" class="form-control">
          </div>
        </div>

        <hr>

        <div class="mb-3">
          <label class="form-label">Note</label>
          <textarea name="contacts[__INDEX__][note]" class="form-control" rows="2"></textarea>
        </div>

        <div class="form-check">
          <input type="checkbox" name="contacts[__INDEX__][primary_contact]" value="1" class="form-check-input primary-contact-check">
          <label class="form-check-label">Primary contact</label>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('contacts-container');
  const addBtn = document.getElementById('add-contact');
  const template = document.getElementById('contact-template');

  function getNextIndex() {
    const entries = container.querySelectorAll('.contact-entry');
    let maxIndex = -1;
    entries.forEach(function(entry) {
      const idx = parseInt(entry.dataset.index, 10);
      if (idx > maxIndex) maxIndex = idx;
    });
    return maxIndex + 1;
  }

  function updateContactNumbers() {
    const entries = container.querySelectorAll('.contact-entry:not([style*="display: none"])');
    entries.forEach(function(entry, i) {
      const numSpan = entry.querySelector('.contact-number');
      if (numSpan) numSpan.textContent = i + 1;
    });
    const visibleEntries = container.querySelectorAll('.contact-entry:not([style*="display: none"])');
    visibleEntries.forEach(function(entry) {
      const removeBtn = entry.querySelector('.remove-contact');
      if (removeBtn) {
        removeBtn.style.display = visibleEntries.length > 1 ? '' : 'none';
      }
    });
  }

  addBtn.addEventListener('click', function() {
    const newIndex = getNextIndex();
    const newNumber = container.querySelectorAll('.contact-entry:not([style*="display: none"])').length + 1;
    let html = template.innerHTML;
    html = html.replace(/__INDEX__/g, newIndex);
    html = html.replace(/__NUMBER__/g, newNumber);
    const div = document.createElement('div');
    div.innerHTML = html;
    const newEntry = div.firstElementChild;
    container.appendChild(newEntry);
    updateContactNumbers();
    newEntry.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  container.addEventListener('click', function(e) {
    if (e.target.closest('.remove-contact')) {
      const entry = e.target.closest('.contact-entry');
      const idInput = entry.querySelector('input[name$="[id]"]');
      const deleteFlag = entry.querySelector('.delete-flag');
      if (idInput && idInput.value) {
        deleteFlag.value = '1';
        entry.style.display = 'none';
      } else {
        entry.remove();
      }
      updateContactNumbers();
    }
  });

  container.addEventListener('change', function(e) {
    if (e.target.classList.contains('primary-contact-check') && e.target.checked) {
      container.querySelectorAll('.primary-contact-check').forEach(function(cb) {
        if (cb !== e.target) cb.checked = false;
      });
    }
  });

  updateContactNumbers();
});
</script>
