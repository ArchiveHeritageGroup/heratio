@extends('theme::layouts.2col')
@section('title', 'Annotate for Training')
@section('body-class', 'admin ai-services htr')
@section('sidebar')
<div class="card mb-3">
  <div class="card-header" style="background: var(--ahg-primary); color: white;">Annotation Fields</div>
  <div class="card-body">
    <form id="annotation-form">
      <div class="mb-2">
        <label class="form-label">Document Type <span class="badge bg-secondary ms-1">Required</span></label>
        <select name="type" class="form-select form-select-sm" id="doc-type">
          <option value="type_a">Type A — Death Certificate</option>
          <option value="type_b">Type B — Register</option>
          <option value="type_c">Type C — Narrative</option>
        </select>
      </div>
      <div id="field-inputs"></div>
      <button type="submit" class="btn atom-btn-outline-success w-100 mt-2"><i class="fas fa-save me-1"></i>Save Annotation</button>
    </form>
  </div>
</div>
<div class="card">
  <div class="card-header" style="background: var(--ahg-primary); color: white;">Statistics</div>
  <div class="card-body">
    <p class="mb-1"><strong>Type A:</strong> <span id="count-a">0</span> annotations</p>
    <p class="mb-1"><strong>Type B:</strong> <span id="count-b">0</span> annotations</p>
    <p class="mb-0"><strong>Type C:</strong> <span id="count-c">0</span> annotations</p>
  </div>
</div>
@endsection
@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item"><a href="{{ route('admin.ai.htr.dashboard') }}">HTR</a></li><li class="breadcrumb-item active">Annotate</li></ol></nav>
<h1><i class="fas fa-pen-square me-2"></i>Annotate for Training</h1>

<div class="card mb-3">
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label">Upload Image <span class="badge bg-secondary ms-1">Required</span></label>
      <input type="file" id="image-upload" class="form-control" accept="image/*">
    </div>
    <div id="image-container" class="border rounded bg-light text-center p-5" style="min-height:400px">
      <p class="text-muted">Upload an image to begin annotation</p>
    </div>
  </div>
</div>
@endsection

@push('js')
<script>
const typeAFields = ['form_number','deceased_name','sex','usual_residence','age','race','birthplace','marital_status','occupation','date_of_death','place_of_death','burial_place','cause_of_death','informant_name','date_registered'];
const typeBFields = ['entry_number','name','date','event_type','notes'];
const typeCFields = ['text_block'];

function updateFields() {
  const type = document.getElementById('doc-type').value;
  const fields = type === 'type_a' ? typeAFields : type === 'type_b' ? typeBFields : typeCFields;
  const container = document.getElementById('field-inputs');
  container.innerHTML = fields.map(f => `<div class="mb-2"><label class="form-label form-label-sm">${f.replace(/_/g,' ')}</label><input type="text" name="fields[${f}]" class="form-control form-control-sm" placeholder="Transcribe ${f.replace(/_/g,' ')}"></div>`).join('');
}
document.getElementById('doc-type').addEventListener('change', updateFields);
updateFields();

document.getElementById('image-upload').addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = (ev) => {
      document.getElementById('image-container').innerHTML = `<img src="${ev.target.result}" class="img-fluid" alt="Document">`;
    };
    reader.readAsDataURL(file);
  }
});

document.getElementById('annotation-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const imageInput = document.getElementById('image-upload');
  if (!imageInput.files.length) { alert('Please upload an image first'); return; }
  const formData = new FormData();
  formData.append('image', imageInput.files[0]);
  formData.append('type', document.getElementById('doc-type').value);
  const fields = {};
  document.querySelectorAll('#field-inputs input').forEach(i => { if(i.value) fields[i.name.match(/\[(.+)\]/)[1]] = i.value; });
  formData.append('annotations', JSON.stringify(fields));
  try {
    const resp = await fetch('{{ route("admin.ai.htr.saveAnnotation") }}', { method: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}, body: formData });
    const data = await resp.json();
    if (data.success) { alert('Annotation saved!'); document.querySelectorAll('#field-inputs input').forEach(i => i.value = ''); }
    else alert('Error: ' + (data.error || 'Unknown'));
  } catch(err) { alert('Error: ' + err.message); }
});
</script>
@endpush
