@extends('theme::layouts.1col')

@section('title', 'TK Labels - ' . ($resource->title ?? $resource->slug))
@section('body-class', 'rights tk-labels')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ $resource->title ?? $resource->slug }}</h1>
    <span class="small">Traditional Knowledge Labels</span>
  </div>
@endsection

@section('content')
  {{-- Current Labels --}}
  @if(isset($assignedLabels) && count($assignedLabels) > 0)
  <div class="card mb-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0">Assigned Labels</h5>
    </div>
    <div class="card-body">
      <div class="row">
        @foreach($assignedLabels as $label)
        <div class="col-md-6 mb-3">
          <div class="d-flex align-items-start">
            <span class="badge me-3" style="background-color: {{ $label->color ?? '#6c757d' }}; width: 60px; padding: 10px;">
              {{ $label->code ?? '' }}
            </span>
            <div>
              <strong>{{ $label->name ?? '' }}</strong>
              @if($label->verified ?? false)
                <i class="fas fa-check-circle text-success ms-1" title="Verified"></i>
              @endif
              <br>
              <small class="text-muted">{{ $label->description ?? '' }}</small>
              @if($label->community_name ?? null)
                <br><small>Community: {{ $label->community_name }}</small>
              @endif
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
  @endif

  {{-- Assign New Label --}}
  @auth
  <div class="card mb-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0">Assign TK Label</h5>
    </div>
    <div class="card-body">
      <form action="{{ route('ext-rights.assign-tk-label', $resource->slug) }}" method="post">
        @csrf
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">TK Label <span class="text-danger">*</span></label>
            <select name="tk_label_id" class="form-select" required>
              <option value="">- Select Label -</option>
              @foreach($availableLabels as $label)
              <option value="{{ $label->id }}">{{ ($label->code ?? '') . ' - ' . ($label->name ?? '') }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Community Name</label>
            <input type="text" name="community_name" class="form-control">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Community Contact</label>
          <textarea name="community_contact" class="form-control" rows="2"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Provenance Statement</label>
          <textarea name="provenance_statement" class="form-control" rows="2"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Cultural Note</label>
          <textarea name="cultural_note" class="form-control" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Assign Label</button>
      </form>
    </div>
  </div>
  @endauth

  <section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
    <a href="{{ route('ext-rights.index', $resource->slug) }}" class="btn atom-btn-outline-light">Back to Rights</a>
  </section>
@endsection
