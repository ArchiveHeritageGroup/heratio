{{-- Classify Record - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/classifySuccess.php --}}
@extends('theme::layouts.1col')

@section('title', ($currentClassification ?? null) ? 'Reclassify Record' : 'Classify Record')

@section('content')

<h1 class="multiline">
  {{ ($currentClassification ?? null) ? 'Reclassify record' : 'Classify record' }}
  <span class="sub">{{ $resource->title ?? $resource->identifier ?? '' }}</span>
</h1>

@if(request('error'))
  <div class="alert alert-danger">
    @if(request('error') === 'invalid')
      Please select a valid classification level.
    @elseif(request('error') === 'failed')
      Failed to apply classification. Please try again.
    @endif
  </div>
@endif

<section id="content">

  {{-- Record Info --}}
  <div class="alert alert-light border mb-4">
    <h6 class="alert-heading">{{ $resource->title ?? $resource->identifier ?? '' }}</h6>
    @if($resource->identifier ?? null)
      <small class="text-muted">Identifier: {{ $resource->identifier }}</small>
    @endif
    @if($currentClassification ?? null)
      <hr class="my-2">
      <small>
        <strong>Current Classification:</strong>
        <span class="badge" style="background-color: {{ $currentClassification->classificationColor ?? $currentClassification->color ?? '#666' }};">
          {{ $currentClassification->classificationName ?? $currentClassification->name ?? '' }}
        </span>
      </small>
    @endif
  </div>

  <form method="post" action="{{ route('acl.classify-store') }}">
    @csrf
    <input type="hidden" name="object_id" value="{{ $resource->id ?? '' }}">

    {{-- Classification Level --}}
    <fieldset class="mb-4">
      <legend class="h6 border-bottom pb-2 mb-3">
        <i class="fas fa-lock me-2"></i>Classification Level
      </legend>

      <div class="row">
        @foreach($classifications ?? [] as $c)
          <div class="col-md-4 mb-3">
            <div class="form-check card h-100 {{ ($currentClassification && ($currentClassification->classificationId ?? $currentClassification->classification_id ?? null) == $c->id) ? 'border-primary' : '' }}">
              <div class="card-body">
                <input class="form-check-input" type="radio"
                       name="classification_id"
                       id="classification_{{ $c->id }}"
                       value="{{ $c->id }}"
                       {{ ($currentClassification && ($currentClassification->classificationId ?? $currentClassification->classification_id ?? null) == $c->id) ? 'checked' : '' }}
                       required>
                <label class="form-check-label w-100" for="classification_{{ $c->id }}">
                  <span class="badge w-100 py-2 mb-2" style="background-color: {{ $c->color }};">
                    <i class="{{ $c->icon ?? 'fas fa-lock' }} me-1"></i>
                    {{ $c->name }}
                  </span>
                  <small class="d-block text-muted">Level {{ $c->level }}</small>
                </label>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </fieldset>

    {{-- Classification Details --}}
    <fieldset class="mb-4">
      <legend class="h6 border-bottom pb-2 mb-3">
        <i class="fas fa-file-alt me-2"></i>Classification Details
      </legend>

      <div class="mb-3">
        <label for="reason" class="form-label">Reason for Classification</label>
        <textarea name="reason" id="reason" class="form-control" rows="3"
                  placeholder="Explain why this classification level is appropriate...">{{ $currentClassification->reason ?? '' }}</textarea>
        <div class="form-text">Document the justification for this classification decision.</div>
      </div>

      <div class="mb-3">
        <label for="handling_instructions" class="form-label">Special Handling Instructions</label>
        <textarea name="handling_instructions" id="handling_instructions" class="form-control" rows="2"
                  placeholder="Any special handling requirements...">{{ $currentClassification->handlingInstructions ?? $currentClassification->handling_instructions ?? '' }}</textarea>
      </div>
    </fieldset>

    {{-- Review & Declassification --}}
    <fieldset class="mb-4">
      <legend class="h6 border-bottom pb-2 mb-3">
        <i class="fas fa-calendar-alt me-2"></i>Review & Declassification
      </legend>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="review_date" class="form-label">Review Date</label>
          <input type="date" name="review_date" id="review_date" class="form-control"
                 value="{{ ($currentClassification && ($currentClassification->reviewDate ?? $currentClassification->review_date ?? null)) ? date('Y-m-d', strtotime($currentClassification->reviewDate ?? $currentClassification->review_date)) : '' }}"
                 min="{{ date('Y-m-d', strtotime('+1 day')) }}">
          <div class="form-text">Date when classification should be reviewed.</div>
        </div>

        <div class="col-md-6 mb-3">
          <label for="declassify_date" class="form-label">Auto-Declassify Date</label>
          <input type="date" name="declassify_date" id="declassify_date" class="form-control"
                 value="{{ ($currentClassification && ($currentClassification->declassifyDate ?? $currentClassification->declassify_date ?? null)) ? date('Y-m-d', strtotime($currentClassification->declassifyDate ?? $currentClassification->declassify_date)) : '' }}"
                 min="{{ date('Y-m-d', strtotime('+1 day')) }}">
          <div class="form-text">Date when classification will be automatically removed.</div>
        </div>
      </div>

      <div class="mb-3">
        <label for="declassify_to_id" class="form-label">Declassify To Level</label>
        <select name="declassify_to_id" id="declassify_to_id" class="form-control">
          <option value="">-- Remove classification entirely --</option>
          @foreach($classifications ?? [] as $c)
            <option value="{{ $c->id }}"
                    {{ ($currentClassification && ($currentClassification->declassifyToId ?? $currentClassification->declassify_to_id ?? null) == $c->id) ? 'selected' : '' }}>
              {{ $c->name }} (Level {{ $c->level }})
            </option>
          @endforeach
        </select>
        <div class="form-text">When auto-declassified, change to this level instead of making public.</div>
      </div>
    </fieldset>

    {{-- Inheritance --}}
    <fieldset class="mb-4">
      <legend class="h6 border-bottom pb-2 mb-3">
        <i class="fas fa-sitemap me-2"></i>Inheritance
      </legend>

      <div class="form-check">
        <input class="form-check-input" type="checkbox"
               name="inherit_to_children" id="inherit_to_children" value="1"
               {{ (!($currentClassification ?? null) || ($currentClassification->inheritToChildren ?? $currentClassification->inherit_to_children ?? true)) ? 'checked' : '' }}>
        <label class="form-check-label" for="inherit_to_children">
          Apply this classification to all child records
        </label>
      </div>
      <div class="form-text">If checked, all descendant records will inherit this classification level.</div>
    </fieldset>

    {{-- Actions --}}
    <section class="actions">
      <ul class="list-unstyled d-flex gap-2">
        <li><a href="{{ route('acl.object-view', ['id' => $resource->id ?? 0]) }}" class="btn btn-secondary">Cancel</a></li>
        @if($currentClassification ?? null)
          <li><button class="btn btn-danger" type="submit" name="action_type" value="declassify" formnovalidate>Declassify</button></li>
        @endif
        <li><button class="btn btn-primary" type="submit" name="action_type" value="classify">Classify</button></li>
      </ul>
    </section>

  </form>

</section>

<style>
.form-check.card { cursor: pointer; }
.form-check.card:hover { border-color: #0d6efd; }
.form-check-input:checked + .form-check-label .badge { box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25); }
</style>
@endsection
