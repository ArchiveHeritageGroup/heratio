{{--
  RM Classification Rule — create / edit form (P4.2)
  @copyright Johan Pieterse / Plain Sailing Information Systems
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', $rule ? 'Edit rule #' . $rule->id : 'New classification rule')
@section('body-class', 'admin records classification edit')

@section('content')
@php $isEdit = (bool) $rule; @endphp

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-magic me-2"></i> {{ $isEdit ? 'Edit rule #' . $rule->id : 'New classification rule' }}</h1>
  <a href="{{ route('records.classification.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to rules') }}</a>
</div>

@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ $isEdit ? route('records.classification.update', $rule->id) : route('records.classification.store') }}" class="row g-3">
  @csrf
  @if($isEdit)@method('PUT')@endif

  <div class="col-md-8">
    <label class="form-label">{{ __('Name') }}</label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $rule->name ?? '') }}" required>
    <div class="form-text small">Human-readable label, e.g. "Tender folder → 2/1/1 (Construction)"</div>
  </div>
  <div class="col-md-4">
    <label class="form-label">{{ __('Priority') }}</label>
    <input type="number" name="priority" class="form-control" value="{{ old('priority', $rule->priority ?? 0) }}">
    <div class="form-text small">Higher number = evaluated first.</div>
  </div>

  <div class="col-12">
    <label class="form-label">{{ __('Description (optional)') }}</label>
    <textarea name="description" rows="2" class="form-control">{{ old('description', $rule->description ?? '') }}</textarea>
  </div>

  <div class="col-md-4">
    <label class="form-label">{{ __('Rule type') }}</label>
    <select name="rule_type" class="form-select" required>
      <option value="">— pick type —</option>
      @foreach($ruleTypes as $rt)
        <option value="{{ $rt->code }}" @selected(old('rule_type', $rule->rule_type ?? '')===$rt->code)>{{ $rt->label }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-8">
    <label class="form-label">{{ __('Match pattern') }}</label>
    <input type="text" name="match_pattern" class="form-control" value="{{ old('match_pattern', $rule->match_pattern ?? '') }}" required>
    <div class="form-text small">
      <strong>folder_path / mime_type:</strong> regex (e.g. <code>^/Projects/</code>, <code>^application/pdf</code>).<br>
      <strong>workspace / department:</strong> exact (case-insensitive) match.<br>
      <strong>tag:</strong> comma-separated tag list — any one tag match fires.<br>
      <strong>metadata:</strong> <code>key=value</code> against the document's custom metadata.
    </div>
  </div>

  <div class="col-md-6">
    <label class="form-label">{{ __('Target file plan node') }}</label>
    <select name="fileplan_node_id" class="form-select" required>
      <option value="">— pick a node —</option>
      @foreach($fileplanNodes as $n)
        <option value="{{ $n->id }}" @selected(old('fileplan_node_id', $rule->fileplan_node_id ?? '')==$n->id)>
          {{ str_repeat('— ', $n->depth) }}{{ $n->code }} — {{ $n->title }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="col-md-6">
    <label class="form-label">{{ __('Disposal class (optional override)') }}</label>
    <select name="disposal_class_id" class="form-select">
      <option value="">— inherit from node —</option>
      @foreach($disposalClasses as $dc)
        <option value="{{ $dc->id }}" @selected(old('disposal_class_id', $rule->disposal_class_id ?? '')==$dc->id)>{{ $dc->class_ref }} — {{ $dc->title }}</option>
      @endforeach
    </select>
  </div>

  <div class="col-md-6">
    <label class="form-label">{{ __('Apply on') }}</label>
    <select name="apply_on" class="form-select">
      <option value="declare" @selected(old('apply_on', $rule->apply_on ?? 'declare')==='declare')>declare (run when record is declared)</option>
      <option value="upload"  @selected(old('apply_on', $rule->apply_on ?? '')==='upload')>upload (run on document upload — needs DM)</option>
      <option value="both"    @selected(old('apply_on', $rule->apply_on ?? '')==='both')>both</option>
    </select>
  </div>
  <div class="col-md-6">
    <label class="form-label">{{ __('Active') }}</label>
    <div class="form-check form-switch">
      <input type="hidden" name="is_active" value="0">
      <input type="checkbox" name="is_active" value="1" class="form-check-input"
             @checked(old('is_active', $rule->is_active ?? 1))>
      <label class="form-check-label">{{ __('Rule is active') }}</label>
    </div>
  </div>

  <div class="col-12">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ $isEdit ? 'Save' : 'Create rule' }}</button>
    <a href="{{ route('records.classification.index') }}" class="btn btn-outline-secondary">Cancel</a>
    @if($isEdit)
      <form method="POST" action="{{ route('records.classification.destroy', $rule->id) }}" class="d-inline" onsubmit="return confirm('Delete this rule? Its rm_classification_log entries are kept for audit.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash me-1"></i>{{ __('Delete') }}</button>
      </form>
    @endif
  </div>
</form>
@endsection
