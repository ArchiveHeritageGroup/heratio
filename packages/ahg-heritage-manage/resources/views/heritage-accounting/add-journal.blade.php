@extends('theme::layouts.1col')
@section('title', 'Add Journal Entry')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-book me-2"></i>Add Journal Entry</h1>
    <p class="text-muted">Record an accounting journal entry.</p>

    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ $formAction ?? '#' }}">
      @csrf
      @if(isset($asset)) @method('PUT') @endif

      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-book me-2"></i>Add Journal Entry</div>
        <div class="card-body">
          @foreach($fields ?? [] as $field)
          <div class="mb-3">
            <label class="form-label">{{ $field['label'] }} <span class="badge bg-secondary ms-1">Optional</span></label>
            @if(($field['type'] ?? 'text') === 'select')
              <select name="{{ $field['name'] }}" class="form-select">
                @foreach($field['options'] ?? [] as $val => $label)
                  <option value="{{ $val }}" {{ old($field['name'], $field['value'] ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            @elseif(($field['type'] ?? 'text') === 'textarea')
              <textarea name="{{ $field['name'] }}" class="form-control" rows="3">{{ old($field['name'], $field['value'] ?? '') }}</textarea>
            @else
              <input type="{{ $field['type'] ?? 'text' }}" name="{{ $field['name'] }}" class="form-control" value="{{ old($field['name'], $field['value'] ?? '') }}">
            @endif
          </div>
          @endforeach

          @if(empty($fields))
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Name <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" name="name" class="form-control" value="{{ old('name', $asset->name ?? '') }}"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Reference <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" name="reference" class="form-control" value="{{ old('reference', $asset->reference ?? '') }}"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Amount <span class="badge bg-secondary ms-1">Optional</span></label><input type="number" step="0.01" name="amount" class="form-control" value="{{ old('amount', $asset->amount ?? '') }}"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Date <span class="badge bg-secondary ms-1">Optional</span></label><input type="date" name="date" class="form-control" value="{{ old('date', $asset->date ?? '') }}"></div>
            <div class="col-12 mb-3"><label class="form-label">Notes <span class="badge bg-secondary ms-1">Optional</span></label><textarea name="notes" class="form-control" rows="3">{{ old('notes', $asset->notes ?? '') }}</textarea></div>
          </div>
          @endif
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>Save</button>
        <a href="{{ route('heritage.accounting.browse') }}" class="btn atom-btn-white">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection