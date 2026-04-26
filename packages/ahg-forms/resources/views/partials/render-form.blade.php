{{--
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems / AGPL v3+

  Renders a form_template's fields[] as a Bootstrap 5 form.
  Inputs:
    $template     ahg_form_template row + ->fields collection
    $entityType   'information_object' | 'actor' | etc.
    $entityId     existing entity id (for edit) or null (for create)
    $values       array<field_name, current_value>  optional preloaded values
    $action       URL to POST submission
    $cancelUrl    URL to return to on cancel
--}}
@php
  $values = $values ?? [];
  $cfg = is_string($template->config_json ?? null) ? json_decode($template->config_json, true) : ($template->config_json ?? []);
  $cfg = is_array($cfg) ? $cfg : [];
  $sections = [];
  foreach (($template->fields ?? []) as $f) {
      $sec = $f->section_name ?: 'General';
      $sections[$sec][] = $f;
  }
@endphp

<form method="POST" action="{{ $action }}" class="ahg-template-form">
  @csrf
  <input type="hidden" name="_template_id" value="{{ $template->id }}">
  <input type="hidden" name="_form_type" value="{{ $template->form_type }}">

  <div class="alert alert-info py-2 px-3 mb-3 d-flex justify-content-between align-items-center">
    <div>
      <i class="fas fa-clipboard-list me-2"></i>
      Editing with template: <strong>{{ $template->name }}</strong>
      @if(!empty($template->descriptive_standard))
        <span class="badge bg-secondary ms-2">{{ $template->descriptive_standard }}</span>
      @endif
      @if(!empty($template->is_default))
        <span class="badge bg-success ms-1">default</span>
      @endif
    </div>
    <a href="{{ $cancelUrl }}" class="btn btn-sm btn-outline-secondary">Switch to standard form</a>
  </div>

  @foreach($sections as $sectionName => $fields)
    <div class="card mb-3">
      <div class="card-header bg-light"><strong>{{ $sectionName }}</strong></div>
      <div class="card-body">
        <div class="row g-3">
          @foreach($fields as $field)
            @php
              $width = $field->width ?: 'full';
              $colClass = match($width) {
                'half' => 'col-md-6',
                'third' => 'col-md-4',
                'quarter' => 'col-md-3',
                default => 'col-12',
              };
              $name = $field->field_name;
              $val = $values[$name] ?? ($field->default_value ?? '');
              $req = $field->is_required ? 'required' : '';
              $ro  = $field->is_readonly ? 'readonly' : '';
              $placeholder = $field->placeholder ?: '';
              $opts = is_string($field->options_json ?? null) ? json_decode($field->options_json, true) : ($field->options_json ?? null);
              $opts = is_array($opts) ? $opts : [];
            @endphp
            @if(!$field->is_hidden)
              <div class="{{ $colClass }}">
                <label class="form-label" for="fld-{{ $field->id }}">
                  {{ $field->label }}
                  @if($field->is_required)<span class="text-danger">*</span>@endif
                </label>

                @switch($field->field_type)
                  @case('textarea')
                    <textarea id="fld-{{ $field->id }}" name="fields[{{ $name }}]"
                              class="form-control" rows="4" {{ $req }} {{ $ro }}
                              placeholder="{{ $placeholder }}">{{ $val }}</textarea>
                    @break
                  @case('select')
                    <select id="fld-{{ $field->id }}" name="fields[{{ $name }}]" class="form-select" {{ $req }} {{ $ro }}>
                      <option value="">— Select —</option>
                      @foreach($opts as $optVal => $optLabel)
                        <option value="{{ is_int($optVal) ? $optLabel : $optVal }}" @selected((string)$val === (string)(is_int($optVal) ? $optLabel : $optVal))>
                          {{ is_array($optLabel) ? ($optLabel['label'] ?? $optLabel['value'] ?? '') : $optLabel }}
                        </option>
                      @endforeach
                    </select>
                    @break
                  @case('checkbox')
                    <div class="form-check">
                      <input type="hidden" name="fields[{{ $name }}]" value="0">
                      <input type="checkbox" id="fld-{{ $field->id }}" name="fields[{{ $name }}]" value="1"
                             class="form-check-input" {{ $val ? 'checked' : '' }} {{ $ro }}>
                    </div>
                    @break
                  @case('date')
                    <input type="date" id="fld-{{ $field->id }}" name="fields[{{ $name }}]"
                           class="form-control" value="{{ $val }}" {{ $req }} {{ $ro }}>
                    @break
                  @case('number')
                    <input type="number" id="fld-{{ $field->id }}" name="fields[{{ $name }}]"
                           class="form-control" value="{{ $val }}" {{ $req }} {{ $ro }}
                           placeholder="{{ $placeholder }}">
                    @break
                  @default
                    <input type="text" id="fld-{{ $field->id }}" name="fields[{{ $name }}]"
                           class="form-control" value="{{ $val }}" {{ $req }} {{ $ro }}
                           placeholder="{{ $placeholder }}">
                @endswitch

                @if($field->help_text)
                  <small class="form-text text-muted">{{ $field->help_text }}</small>
                @endif
              </div>
            @endif
          @endforeach
        </div>
      </div>
    </div>
  @endforeach

  <div class="d-flex justify-content-end gap-2 mt-3">
    <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
  </div>
</form>
