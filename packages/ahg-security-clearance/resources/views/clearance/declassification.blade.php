@extends('ahg-theme-b5::layout')

@section('title', 'Declassification')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item active">Declassification</li>
  </ol></nav>

  <h1><i class="fas fa-unlock"></i> Declassification</h1>
  <p>Object: <strong>{{ e($object->title ?? 'ID: ' . $object->id) }}</strong></p>

  @if($currentClassification)
    <div class="alert alert-info">
      Current classification: <span class="badge" style="background-color: {{ $currentClassification->color ?? '#666' }}">{{ e($currentClassification->name ?? '') }}</span>
    </div>
  @else
    <div class="alert alert-warning">This object has no classification.</div>
  @endif

  <form method="POST" action="{{ route('security-clearance.declassify-store') }}">
    @csrf
    <input type="hidden" name="object_id" value="{{ $object->id }}">

    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">Declassification Options</h5></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Downgrade To</label>
          <select name="target_classification_id" class="form-select">
            <option value="0">Public (remove classification)</option>
            @foreach($classifications ?? [] as $cl)
              @if(($cl->level ?? 0) < ($currentClassification->level ?? 99))
              <option value="{{ $cl->id }}" style="color: {{ $cl->color ?? '#333' }}">{{ e($cl->name) }} (Level {{ $cl->level }})</option>
              @endif
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Effective Date</label>
          <div class="form-check mb-2">
            <input type="radio" name="schedule_type" value="immediate" class="form-check-input" id="immediate" checked>
            <label class="form-check-label" for="immediate">Immediate</label>
          </div>
          <div class="form-check mb-2">
            <input type="radio" name="schedule_type" value="scheduled" class="form-check-input" id="scheduled">
            <label class="form-check-label" for="scheduled">Scheduled</label>
          </div>
          <input type="date" name="scheduled_date" class="form-control mt-2" id="scheduledDate" disabled>
        </div>

        <div class="mb-3">
          <label class="form-label">Reason for Declassification</label>
          <textarea name="reason" class="form-control" rows="3" required></textarea>
        </div>

        <div class="form-check mb-3">
          <input type="checkbox" name="apply_to_children" value="1" class="form-check-input" id="applyChildren">
          <label class="form-check-label" for="applyChildren">Apply to all child records</label>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-success"><i class="fas fa-unlock"></i> Declassify</button>
    <a href="{{ route('security-clearance.dashboard') }}" class="btn btn-secondary">Cancel</a>
  </form>
</div>

@push('scripts')
<script>
document.querySelectorAll('input[name="schedule_type"]').forEach(radio => {
  radio.addEventListener('change', function() {
    document.getElementById('scheduledDate').disabled = this.value !== 'scheduled';
  });
});
</script>
@endpush
@endsection
