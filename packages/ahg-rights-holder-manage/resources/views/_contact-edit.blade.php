{{--
  Related contact information — modal table editor
  Matches AtoM contactinformation/_edit.php exactly:
  - Table with Contact person + Primary columns
  - Modal with 3 pill tabs: Main, Physical location, Other details
  - 15 fields per contact (matching contact_information + contact_information_i18n schema)
--}}

<h3 class="fs-6 mb-2">Related contact information</h3>

<div id="contact-table-editor">
  <div class="table-responsive">
    <table class="table table-bordered mb-0" id="contact-table">
      <thead class="table-light">
        <tr>
          <th class="w-80">Contact person</th>
          <th class="w-20">Primary</th>
          <th><span class="visually-hidden">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        @foreach($contacts as $index => $item)
          <tr data-index="{{ $index }}">
            <td>{{ $item->contact_person ?? '' }}</td>
            <td>{{ !empty($item->primary_contact) ? 'Yes' : 'No' }}</td>
            <td class="text-nowrap">
              <button type="button" class="btn atom-btn-white btn-sm me-1 edit-contact-row" data-index="{{ $index }}">
                <i class="fas fa-fw fa-pencil-alt" aria-hidden="true"></i>
                <span class="visually-hidden">Edit row</span>
              </button>
              <button type="button" class="btn atom-btn-white btn-sm delete-contact-row" data-index="{{ $index }}">
                <i class="fas fa-fw fa-times" aria-hidden="true"></i>
                <span class="visually-hidden">Delete row</span>
              </button>
            </td>
            {{-- Hidden fields for this contact --}}
            <input type="hidden" name="contacts[{{ $index }}][id]" value="{{ $item->id ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][delete]" value="0" class="delete-flag">
            <input type="hidden" name="contacts[{{ $index }}][primary_contact]" value="{{ !empty($item->primary_contact) ? '1' : '0' }}">
            <input type="hidden" name="contacts[{{ $index }}][contact_person]" value="{{ $item->contact_person ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][telephone]" value="{{ $item->telephone ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][fax]" value="{{ $item->fax ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][email]" value="{{ $item->email ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][website]" value="{{ $item->website ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][street_address]" value="{{ $item->street_address ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][region]" value="{{ $item->region ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][country_code]" value="{{ $item->country_code ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][postal_code]" value="{{ $item->postal_code ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][city]" value="{{ $item->city ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][latitude]" value="{{ $item->latitude ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][longitude]" value="{{ $item->longitude ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][contact_type]" value="{{ $item->contact_type ?? '' }}">
            <input type="hidden" name="contacts[{{ $index }}][note]" value="{{ $item->note ?? '' }}">
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3">
            <button type="button" class="btn atom-btn-white" id="add-contact-row">
              <i class="fas fa-plus me-1" aria-hidden="true"></i>
              Add new
            </button>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>

  {{-- ── Contact Information Modal ── --}}
  <div class="modal fade" id="contactModal" data-bs-backdrop="static" tabindex="-1"
       aria-labelledby="related-contact-information-heading" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="h5 modal-title" id="related-contact-information-heading">
            Related contact information
          </h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal">
            <span class="visually-hidden">Close</span>
          </button>
        </div>

        <div class="modal-body pb-2">
          <div class="alert alert-danger d-none" id="contact-validation-error" role="alert">
            Please complete all required fields.
          </div>

          {{-- Pill tabs --}}
          <ul class="nav nav-pills mb-3 d-flex gap-2" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="btn atom-btn-white active-primary text-wrap active"
                      id="pills-main-tab" data-bs-toggle="pill" data-bs-target="#pills-main"
                      type="button" role="tab" aria-controls="pills-main" aria-selected="true">
                Main
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="btn atom-btn-white active-primary text-wrap"
                      id="pills-phys-tab" data-bs-toggle="pill" data-bs-target="#pills-phys"
                      type="button" role="tab" aria-controls="pills-phys" aria-selected="false">
                Physical location
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="btn atom-btn-white active-primary text-wrap"
                      id="pills-other-tab" data-bs-toggle="pill" data-bs-target="#pills-other"
                      type="button" role="tab" aria-controls="pills-other" aria-selected="false">
                Other details
              </button>
            </li>
          </ul>

          {{-- Tab content --}}
          <div class="tab-content">
            {{-- Main tab --}}
            <div class="tab-pane fade show active" id="pills-main" role="tabpanel" aria-labelledby="pills-main-tab">
              <div class="mb-3">
                <label for="modal-primaryContact" class="form-label">Primary contact <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" id="modal-primaryContact" value="1">
                  <label class="form-check-label" for="modal-primaryContact">Yes</label>
                </div>
              </div>
              <div class="mb-3">
                <label for="modal-contactPerson" class="form-label">Contact person <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" id="modal-contactPerson">
              </div>
              <div class="mb-3">
                <label for="modal-telephone" class="form-label">Phone <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" id="modal-telephone">
              </div>
              <div class="mb-3">
                <label for="modal-fax" class="form-label">Fax <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" id="modal-fax">
              </div>
              <div class="mb-3">
                <label for="modal-email" class="form-label">Email <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="email" class="form-control" id="modal-email">
              </div>
              <div class="mb-3">
                <label for="modal-website" class="form-label">URL <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="url" class="form-control" id="modal-website">
              </div>
            </div>

            {{-- Physical location tab --}}
            <div class="tab-pane fade" id="pills-phys" role="tabpanel" aria-labelledby="pills-phys-tab">
              <div class="mb-3">
                <label for="modal-streetAddress" class="form-label">Street address <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea class="form-control" id="modal-streetAddress" rows="2"></textarea>
              </div>
              <div class="mb-3">
                <label for="modal-region" class="form-label">Region/province <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" id="modal-region">
              </div>
              <div class="mb-3">
                <label for="modal-countryCode" class="form-label">Country <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" id="modal-countryCode" placeholder="e.g. ZA, US, GB">
              </div>
              <div class="mb-3">
                <label for="modal-postalCode" class="form-label">Postal code <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" id="modal-postalCode">
              </div>
              <div class="mb-3">
                <label for="modal-city" class="form-label">City <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" id="modal-city">
              </div>
              <div class="mb-3">
                <label for="modal-latitude" class="form-label">Latitude <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" id="modal-latitude">
              </div>
              <div class="mb-3">
                <label for="modal-longitude" class="form-label">Longitude <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" id="modal-longitude">
              </div>
            </div>

            {{-- Other details tab --}}
            <div class="tab-pane fade" id="pills-other" role="tabpanel" aria-labelledby="pills-other-tab">
              <div class="mb-3">
                <label for="modal-contactType" class="form-label">Contact type <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" id="modal-contactType">
              </div>
              <div class="mb-3">
                <label for="modal-note" class="form-label">Note <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea class="form-control" id="modal-note" rows="2"></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn atom-btn-outline-light" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn atom-btn-outline-success" id="contact-modal-submit">Submit</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const tbody = document.querySelector('#contact-table tbody');
  const modal = document.getElementById('contactModal');
  const bsModal = new bootstrap.Modal(modal);
  let editingIndex = null;
  let nextIndex = {{ count($contacts) }};

  // Field mapping: modal field id suffix -> hidden input name suffix
  const fields = [
    'primaryContact', 'contactPerson', 'telephone', 'fax', 'email', 'website',
    'streetAddress', 'region', 'countryCode', 'postalCode', 'city',
    'latitude', 'longitude', 'contactType', 'note'
  ];

  // Map camelCase to snake_case for hidden input names
  const fieldToName = {
    primaryContact: 'primary_contact',
    contactPerson: 'contact_person',
    telephone: 'telephone',
    fax: 'fax',
    email: 'email',
    website: 'website',
    streetAddress: 'street_address',
    region: 'region',
    countryCode: 'country_code',
    postalCode: 'postal_code',
    city: 'city',
    latitude: 'latitude',
    longitude: 'longitude',
    contactType: 'contact_type',
    note: 'note'
  };

  function clearModal() {
    fields.forEach(function(f) {
      var el = document.getElementById('modal-' + f);
      if (el.type === 'checkbox') el.checked = false;
      else el.value = '';
    });
    document.getElementById('contact-validation-error').classList.add('d-none');
    // Reset to Main tab
    var mainTab = document.getElementById('pills-main-tab');
    if (mainTab) bootstrap.Tab.getOrCreateInstance(mainTab).show();
  }

  function getHidden(idx, snakeName) {
    return tbody.querySelector('input[name="contacts[' + idx + '][' + snakeName + ']"]');
  }

  // Edit existing row
  tbody.addEventListener('click', function(e) {
    var editBtn = e.target.closest('.edit-contact-row');
    if (editBtn) {
      editingIndex = editBtn.dataset.index;
      clearModal();
      fields.forEach(function(f) {
        var hidden = getHidden(editingIndex, fieldToName[f]);
        if (!hidden) return;
        var el = document.getElementById('modal-' + f);
        if (el.type === 'checkbox') el.checked = hidden.value === '1';
        else el.value = hidden.value;
      });
      bsModal.show();
    }

    var delBtn = e.target.closest('.delete-contact-row');
    if (delBtn) {
      var idx = delBtn.dataset.index;
      var row = tbody.querySelector('tr[data-index="' + idx + '"]');
      var idHidden = getHidden(idx, 'id');
      if (idHidden && idHidden.value) {
        // Mark for deletion
        var deleteFlag = getHidden(idx, 'delete');
        if (deleteFlag) deleteFlag.value = '1';
        row.style.display = 'none';
      } else {
        row.remove();
      }
    }
  });

  // Add new row
  document.getElementById('add-contact-row').addEventListener('click', function() {
    editingIndex = nextIndex++;
    clearModal();
    // Create a new hidden row
    var tr = document.createElement('tr');
    tr.dataset.index = editingIndex;
    tr.innerHTML = '<td></td><td>No</td><td class="text-nowrap">' +
      '<button type="button" class="btn atom-btn-white btn-sm me-1 edit-contact-row" data-index="' + editingIndex + '">' +
      '<i class="fas fa-fw fa-pencil-alt" aria-hidden="true"></i><span class="visually-hidden">Edit row</span></button>' +
      '<button type="button" class="btn atom-btn-white btn-sm delete-contact-row" data-index="' + editingIndex + '">' +
      '<i class="fas fa-fw fa-times" aria-hidden="true"></i><span class="visually-hidden">Delete row</span></button></td>';
    // Add hidden inputs
    var hiddenFields = ['id', 'delete', 'primary_contact', 'contact_person', 'telephone', 'fax',
      'email', 'website', 'street_address', 'region', 'country_code', 'postal_code', 'city',
      'latitude', 'longitude', 'contact_type', 'note'];
    hiddenFields.forEach(function(name) {
      var inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'contacts[' + editingIndex + '][' + name + ']';
      inp.value = name === 'delete' ? '0' : '';
      if (name === 'delete') inp.className = 'delete-flag';
      tr.appendChild(inp);
    });
    tbody.appendChild(tr);
    bsModal.show();
  });

  // Submit modal
  document.getElementById('contact-modal-submit').addEventListener('click', function() {
    // Write modal values back to hidden fields
    fields.forEach(function(f) {
      var el = document.getElementById('modal-' + f);
      var hidden = getHidden(editingIndex, fieldToName[f]);
      if (!hidden) return;
      if (el.type === 'checkbox') hidden.value = el.checked ? '1' : '0';
      else hidden.value = el.value;
    });

    // Update visible table cells
    var row = tbody.querySelector('tr[data-index="' + editingIndex + '"]');
    if (row) {
      var cells = row.querySelectorAll('td');
      cells[0].textContent = document.getElementById('modal-contactPerson').value;
      cells[1].textContent = document.getElementById('modal-primaryContact').checked ? 'Yes' : 'No';
    }

    bsModal.hide();
    editingIndex = null;
  });
});
</script>
