{{-- Internationalized form field partial --}}
@php
  $fieldName = $fieldName ?? 'field';
  $fieldLabel = $fieldLabel ?? ucfirst(str_replace('_', ' ', $fieldName));
  $fieldValue = $fieldValue ?? '';
  $fieldType = $fieldType ?? 'text';
  $fieldHelp = $fieldHelp ?? '';
  $cultures = $cultures ?? ['en'];
@endphp

<div class="mb-3 i18n-form-field">
  <label class="form-label">{{ $fieldLabel }} <span class="badge bg-secondary ms-1">Optional</span></label>
  @if(count($cultures) > 1)
    <ul class="nav nav-tabs nav-tabs-sm mb-2">
      @foreach($cultures as $i => $culture)
        <li class="nav-item">
          <a class="nav-link {{ $i === 0 ? 'active' : '' }}" data-bs-toggle="tab" href="#{{ $fieldName }}-{{ $culture }}">{{ strtoupper($culture) }}</a>
        </li>
      @endforeach
    </ul>
    <div class="tab-content">
      @foreach($cultures as $i => $culture)
        <div class="tab-pane {{ $i === 0 ? 'active' : '' }}" id="{{ $fieldName }}-{{ $culture }}">
          @if($fieldType === 'textarea')
            <textarea name="{{ $fieldName }}[{{ $culture }}]" class="form-control" rows="3">{{ $fieldValue[$culture] ?? '' }}</textarea>
          @else
            <input type="{{ $fieldType }}" name="{{ $fieldName }}[{{ $culture }}]" class="form-control" value="{{ $fieldValue[$culture] ?? '' }}">
          @endif
        </div>
      @endforeach
    </div>
  @else
    @if($fieldType === 'textarea')
      <textarea name="{{ $fieldName }}" class="form-control" rows="3">{{ $fieldValue }}</textarea>
    @else
      <input type="{{ $fieldType }}" name="{{ $fieldName }}" class="form-control" value="{{ $fieldValue }}">
    @endif
  @endif
  @if($fieldHelp)<small class="text-muted">{{ $fieldHelp }}</small>@endif
</div>
