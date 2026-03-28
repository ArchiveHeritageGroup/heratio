@extends('ahg-theme-b5::layout')

@section('title', 'Classify Record')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item active">Classify</li>
  </ol></nav>

  <h1><i class="fas fa-lock"></i> Classify Record</h1>
  <p>Object: <strong>{{ e($object->title ?? 'ID: ' . $object->id) }}</strong></p>

  @if($currentClassification)
    <div class="alert alert-info">
      Current classification: <span class="badge" style="background-color: {{ $currentClassification->color ?? '#666' }}">{{ e($currentClassification->name ?? 'Unknown') }}</span>
    </div>
  @endif

  <form method="POST" action="{{ route('security-clearance.classify-store') }}">
    @csrf
    <input type="hidden" name="object_id" value="{{ $object->id }}">

    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">Select Classification Level</h5></div>
      <div class="card-body">
        <div class="row">
          @foreach($classifications ?? [] as $cl)
          <div class="col-md-4 mb-3">
            <div class="card h-100 {{ ($currentClassification->id ?? 0) == $cl->id ? 'border-primary' : '' }}" style="cursor:pointer;"
                 onclick="document.getElementById('cl_{{ $cl->id }}').checked = true;">
              <div class="card-body text-center">
                <input type="radio" name="classification_id" id="cl_{{ $cl->id }}" value="{{ $cl->id }}"
                       {{ ($currentClassification->id ?? 0) == $cl->id ? 'checked' : '' }} class="form-check-input">
                <h5><span class="badge" style="background-color: {{ $cl->color ?? '#666' }}; font-size: 1em;">{{ e($cl->name) }}</span></h5>
                <small class="text-muted">Level {{ $cl->level }}</small>
                @if(!empty($cl->description))
                  <p class="mt-2 small">{{ e($cl->description) }}</p>
                @endif
              </div>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Compartments --}}
    @if(!empty($compartments) && count($compartments))
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">Compartments (optional)</h5></div>
      <div class="card-body">
        @foreach($compartments as $comp)
        <div class="form-check">
          <input type="checkbox" name="compartment_ids[]" value="{{ $comp->id }}" class="form-check-input" id="comp_{{ $comp->id }}">
          <label class="form-check-label" for="comp_{{ $comp->id }}">{{ e($comp->name) }} <code>({{ e($comp->code ?? '') }})</code></label>
        </div>
        @endforeach
      </div>
    </div>
    @endif

    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">Reason</h5></div>
      <div class="card-body">
        <textarea name="reason" class="form-control" rows="3" placeholder="Reason for classification..."></textarea>
      </div>
    </div>

    <div class="mb-3 form-check">
      <input type="checkbox" name="apply_to_children" value="1" class="form-check-input" id="applyToChildren">
      <label class="form-check-label" for="applyToChildren">Apply to all child records (inheritance)</label>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fas fa-lock"></i> Apply Classification</button>
    <a href="{{ route('security-clearance.dashboard') }}" class="btn btn-secondary">Cancel</a>
  </form>
</div>
@endsection
