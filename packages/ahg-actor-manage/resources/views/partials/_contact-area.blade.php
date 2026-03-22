@php
    $titles = ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Prof', 'Rev', 'Hon', 'Sir', 'Dame', 'Adv'];
@endphp

<div id="contacts-container">
@foreach($contacts as $index => $contact)
  <div class="contact-entry" data-index="{{ $index }}">
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="bi bi-telephone me-2"></i>Contact #<span class="contact-number">{{ $index + 1 }}</span></h5>
        <button type="button" class="btn btn-sm btn-outline-light remove-contact" @if($loop->first && $contacts->count() === 1) style="display:none;" @endif>
          <i class="bi bi-trash"></i> Remove
        </button>
      </div>
      <div class="card-body">
        <input type="hidden" name="contacts[{{ $index }}][id]" value="{{ $contact->id ?? '' }}">
        <input type="hidden" name="contacts[{{ $index }}][delete]" value="0" class="delete-flag">

        <div class="row">
          <div class="col-md-2 mb-3">
            <label class="form-label">Title <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="contacts[{{ $index }}][title]" class="form-select">
              <option value="">Select...</option>
              @foreach($titles as $t)
                <option value="{{ $t }}" @selected(($contact->title ?? '') === $t)>{{ $t }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-5 mb-3">
            <label class="form-label">Contact person <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[{{ $index }}][contact_person]" class="form-control"
                   value="{{ $contact->contact_person ?? '' }}">
          </div>
          <div class="col-md-5 mb-3">
            <label class="form-label">Role/Position <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[{{ $index }}][role]" class="form-control"
                   value="{{ $contact->role ?? '' }}"
                   placeholder="e.g., Director, Manager, Curator">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Department <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[{{ $index }}][department]" class="form-control"
                   value="{{ $contact->department ?? '' }}">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Contact type <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[{{ $index }}][contact_type]" class="form-control"
                   value="{{ $contact->contact_type ?? '' }}"
                   placeholder="e.g., Primary, Business, Home">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">ID/Passport number <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[{{ $index }}][id_number]" class="form-control"
                   value="{{ $contact->id_number ?? '' }}">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Preferred contact method <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="contacts[{{ $index }}][preferred_contact_method]" class="form-select">
              <option value="">Select...</option>
              <option value="email" @selected(($contact->preferred_contact_method ?? '') === 'email')>Email</option>
              <option value="phone" @selected(($contact->preferred_contact_method ?? '') === 'phone')>Phone</option>
              <option value="cell" @selected(($contact->preferred_contact_method ?? '') === 'cell')>Cell/Mobile</option>
              <option value="fax" @selected(($contact->preferred_contact_method ?? '') === 'fax')>Fax</option>
              <option value="mail" @selected(($contact->preferred_contact_method ?? '') === 'mail')>Post/Mail</option>
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Language preference <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="contacts[{{ $index }}][language_preference]" class="form-select">
              <option value="">-- Select --</option>
              @foreach($formChoices['languages'] ?? ['en' => 'English', 'fr' => 'French', 'af' => 'Afrikaans', 'de' => 'German', 'nl' => 'Dutch', 'pt' => 'Portuguese', 'es' => 'Spanish', 'it' => 'Italian', 'zu' => 'Zulu', 'xh' => 'Xhosa', 'st' => 'Sotho'] as $code => $name)
                <option value="{{ $code }}" @selected(($contact->language_preference ?? '') == $code)>{{ $name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <hr>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Telephone <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="tel" name="contacts[{{ $index }}][telephone]" class="form-control"
                   value="{{ $contact->telephone ?? '' }}">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Cell/Mobile <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="tel" name="contacts[{{ $index }}][cell]" class="form-control"
                   value="{{ $contact->cell ?? '' }}">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Fax <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="tel" name="contacts[{{ $index }}][fax]" class="form-control"
                   value="{{ $contact->fax ?? '' }}">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Email <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="email" name="contacts[{{ $index }}][email]" class="form-control"
                   value="{{ $contact->email ?? '' }}">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Alternative email <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="email" name="contacts[{{ $index }}][alternative_email]" class="form-control"
                   value="{{ $contact->alternative_email ?? '' }}">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Website <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="url" name="contacts[{{ $index }}][website]" class="form-control"
                   value="{{ $contact->website ?? '' }}" placeholder="https://">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Alternative phone <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="tel" name="contacts[{{ $index }}][alternative_phone]" class="form-control"
                   value="{{ $contact->alternative_phone ?? '' }}">
          </div>
        </div>

        <hr>
        <h6 class="text-muted mb-3"><i class="bi bi-geo-alt me-2"></i>Physical location</h6>

        <div class="mb-3">
          <label class="form-label">Street address <span class="badge bg-secondary ms-1">Optional</span></label>
          <textarea name="contacts[{{ $index }}][street_address]" class="form-control" rows="2">{{ $contact->street_address ?? '' }}</textarea>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">City <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[{{ $index }}][city]" class="form-control"
                   value="{{ $contact->city ?? '' }}">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Region/Province <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[{{ $index }}][region]" class="form-control"
                   value="{{ $contact->region ?? '' }}">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Postal code <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[{{ $index }}][postal_code]" class="form-control"
                   value="{{ $contact->postal_code ?? '' }}">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Country <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[{{ $index }}][country_code]" class="form-control"
                   value="{{ $contact->country_code ?? '' }}" placeholder="e.g. ZA, US, GB">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Latitude <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[{{ $index }}][latitude]" class="form-control"
                   value="{{ $contact->latitude ?? '' }}">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Longitude <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[{{ $index }}][longitude]" class="form-control"
                   value="{{ $contact->longitude ?? '' }}">
          </div>
        </div>

        <hr>

        <div class="mb-3">
          <label class="form-label">Note <span class="badge bg-secondary ms-1">Optional</span></label>
          <textarea name="contacts[{{ $index }}][note]" class="form-control" rows="2">{{ $contact->note ?? '' }}</textarea>
        </div>

        <div class="form-check">
          <input type="checkbox" name="contacts[{{ $index }}][primary_contact]" value="1" class="form-check-input primary-contact-check"
                 @checked(!empty($contact->primary_contact))>
          <label class="form-check-label">Primary contact <span class="badge bg-secondary ms-1">Optional</span></label>
        </div>
      </div>
    </div>
  </div>
@endforeach
</div>

<div class="mb-4">
  <button type="button" id="add-contact" class="btn atom-btn-white">
    <i class="bi bi-plus-circle me-2"></i>Add another contact
  </button>
</div>

<template id="contact-template">
  <div class="contact-entry" data-index="__INDEX__">
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="bi bi-telephone me-2"></i>Contact #<span class="contact-number">__NUMBER__</span></h5>
        <button type="button" class="btn btn-sm btn-outline-light remove-contact">
          <i class="bi bi-trash"></i> Remove
        </button>
      </div>
      <div class="card-body">
        <input type="hidden" name="contacts[__INDEX__][id]" value="">
        <input type="hidden" name="contacts[__INDEX__][delete]" value="0" class="delete-flag">

        <div class="row">
          <div class="col-md-2 mb-3">
            <label class="form-label">Title <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="contacts[__INDEX__][title]" class="form-select">
              <option value="">Select...</option>
              @foreach($titles as $t)
                <option value="{{ $t }}">{{ $t }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-5 mb-3">
            <label class="form-label">Contact person <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[__INDEX__][contact_person]" class="form-control">
          </div>
          <div class="col-md-5 mb-3">
            <label class="form-label">Role/Position <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[__INDEX__][role]" class="form-control"
                   placeholder="e.g., Director, Manager, Curator">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Department <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[__INDEX__][department]" class="form-control">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Contact type <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[__INDEX__][contact_type]" class="form-control"
                   placeholder="e.g., Primary, Business, Home">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">ID/Passport number <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[__INDEX__][id_number]" class="form-control">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Preferred contact method <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="contacts[__INDEX__][preferred_contact_method]" class="form-select">
              <option value="">Select...</option>
              <option value="email">Email</option>
              <option value="phone">Phone</option>
              <option value="cell">Cell/Mobile</option>
              <option value="fax">Fax</option>
              <option value="mail">Post/Mail</option>
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Language preference <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="contacts[__INDEX__][language_preference]" class="form-select">
              <option value="">-- Select --</option>
              @foreach($formChoices['languages'] ?? ['en' => 'English', 'fr' => 'French', 'af' => 'Afrikaans', 'de' => 'German', 'nl' => 'Dutch', 'pt' => 'Portuguese', 'es' => 'Spanish', 'it' => 'Italian', 'zu' => 'Zulu', 'xh' => 'Xhosa', 'st' => 'Sotho'] as $code => $name)
                <option value="{{ $code }}">{{ $name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <hr>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Telephone <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="tel" name="contacts[__INDEX__][telephone]" class="form-control">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Cell/Mobile <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="tel" name="contacts[__INDEX__][cell]" class="form-control">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Fax <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="tel" name="contacts[__INDEX__][fax]" class="form-control">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Email <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="email" name="contacts[__INDEX__][email]" class="form-control">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Alternative email <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="email" name="contacts[__INDEX__][alternative_email]" class="form-control">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Website <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="url" name="contacts[__INDEX__][website]" class="form-control" placeholder="https://">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Alternative phone <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="tel" name="contacts[__INDEX__][alternative_phone]" class="form-control">
          </div>
        </div>

        <hr>
        <h6 class="text-muted mb-3"><i class="bi bi-geo-alt me-2"></i>Physical location</h6>

        <div class="mb-3">
          <label class="form-label">Street address <span class="badge bg-secondary ms-1">Optional</span></label>
          <textarea name="contacts[__INDEX__][street_address]" class="form-control" rows="2"></textarea>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">City <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[__INDEX__][city]" class="form-control">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Region/Province <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[__INDEX__][region]" class="form-control">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Postal code <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[__INDEX__][postal_code]" class="form-control">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Country <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[__INDEX__][country_code]" class="form-control" placeholder="e.g. ZA, US, GB">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Latitude <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[__INDEX__][latitude]" class="form-control">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Longitude <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="contacts[__INDEX__][longitude]" class="form-control">
          </div>
        </div>

        <hr>

        <div class="mb-3">
          <label class="form-label">Note <span class="badge bg-secondary ms-1">Optional</span></label>
          <textarea name="contacts[__INDEX__][note]" class="form-control" rows="2"></textarea>
        </div>

        <div class="form-check">
          <input type="checkbox" name="contacts[__INDEX__][primary_contact]" value="1" class="form-check-input primary-contact-check">
          <label class="form-check-label">Primary contact <span class="badge bg-secondary ms-1">Optional</span></label>
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
      if (removeBtn) removeBtn.style.display = visibleEntries.length > 1 ? '' : 'none';
    });
  }

  addBtn.addEventListener('click', function() {
    const newIndex = getNextIndex();
    const newNumber = container.querySelectorAll('.contact-entry:not([style*="display: none"])').length + 1;
    let html = template.innerHTML.replace(/__INDEX__/g, newIndex).replace(/__NUMBER__/g, newNumber);
    const div = document.createElement('div');
    div.innerHTML = html;
    container.appendChild(div.firstElementChild);
    updateContactNumbers();
    container.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
