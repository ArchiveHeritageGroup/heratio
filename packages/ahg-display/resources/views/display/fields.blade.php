@extends('theme::layouts.master')

@section('title', 'Field Mappings')
@section('body-class', 'admin display fields')

@section('breadcrumbs')
  <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
  <li class="breadcrumb-item"><a href="{{ route('glam.index') }}">Display Configuration</a></li>
  <li class="breadcrumb-item active" aria-current="page">Fields</li>
@endsection

@section('layout-content')
@php
  $groupIcons = [
    'identity'      => 'fa-id-card',
    'context'       => 'fa-sitemap',
    'content'       => 'fa-file-alt',
    'conditions'    => 'fa-gavel',
    'allied'        => 'fa-link',
    'notes'         => 'fa-sticky-note',
    'description'   => 'fa-align-left',
    'access'        => 'fa-key',
    'physical'      => 'fa-box',
    'administration'=> 'fa-cog',
    'digital'       => 'fa-image',
    'relations'     => 'fa-project-diagram',
    'control'       => 'fa-sliders-h',
  ];
  $typeBadges = [
    'text'     => 'bg-primary',
    'textarea' => 'bg-info',
    'date'     => 'bg-warning',
    'select'   => 'bg-success',
    'boolean'  => 'bg-secondary',
    'integer'  => 'bg-dark',
    'relation' => 'bg-danger',
  ];
@endphp

<div id="main-column" role="main">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center">
      <i class="fas fa-3x fa-columns me-3 text-primary" aria-hidden="true"></i>
      <div>
        <h1 class="mb-0">Field Mappings</h1>
        <span class="small text-muted">View field definitions, source mappings, and standard compliance</span>
      </div>
    </div>
    <a href="{{ route('glam.index') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i> Back
    </a>
  </div>

  @if(!empty($fieldGroups) && count($fieldGroups))
    @foreach($fieldGroups as $groupName => $groupFields)
      @php
        $icon = $groupIcons[strtolower($groupName)] ?? 'fa-th-list';
      @endphp
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">
            <i class="fas {{ $icon }} me-2"></i>
            {{ ucfirst($groupName) }}
            <span class="badge bg-secondary ms-2">{{ count($groupFields) }} fields</span>
          </h5>
        </div>
        <div class="card-body p-0">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr>
                <th>Field Name</th>
                <th>Code</th>
                <th>Type</th>
                <th>Source Table</th>
                <th>Source Column</th>
                <th class="text-center" title="ISAD(G)">ISAD</th>
                <th class="text-center" title="Spectrum">Spectrum</th>
                <th class="text-center" title="Dublin Core">DC</th>
              </tr>
            </thead>
            <tbody>
              @foreach($groupFields as $field)
                <tr>
                  <td><strong>{{ $field->name ?? $field->label ?? '-' }}</strong></td>
                  <td><code>{{ $field->code ?? '-' }}</code></td>
                  <td>
                    @php
                      $fieldType = $field->type ?? 'text';
                      $badgeClass = $typeBadges[strtolower($fieldType)] ?? 'bg-secondary';
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ $fieldType }}</span>
                  </td>
                  <td><code class="small">{{ $field->source_table ?? '-' }}</code></td>
                  <td><code class="small">{{ $field->source_column ?? '-' }}</code></td>
                  <td class="text-center">
                    @if(!empty($field->isad))
                      <i class="fas fa-check text-success" title="{{ $field->isad }}"></i>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td class="text-center">
                    @if(!empty($field->spectrum))
                      <i class="fas fa-check text-success" title="{{ $field->spectrum }}"></i>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td class="text-center">
                    @if(!empty($field->dc))
                      <i class="fas fa-check text-success" title="{{ $field->dc }}"></i>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endforeach
  @elseif(!empty($fields) && count($fields))
    {{-- Flat field list fallback when fieldGroups is not grouped --}}
    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-th-list me-2"></i>All Fields <span class="badge bg-secondary ms-2">{{ count($fields) }}</span></h5>
      </div>
      <div class="card-body p-0">
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr>
              <th>Field Name</th>
              <th>Code</th>
              <th>Type</th>
              <th>Source Table</th>
              <th>Source Column</th>
              <th class="text-center">ISAD</th>
              <th class="text-center">Spectrum</th>
              <th class="text-center">DC</th>
            </tr>
          </thead>
          <tbody>
            @foreach($fields as $field)
              <tr>
                <td><strong>{{ $field->name ?? $field->label ?? '-' }}</strong></td>
                <td><code>{{ $field->code ?? '-' }}</code></td>
                <td>
                  @php
                    $fieldType = $field->type ?? 'text';
                    $badgeClass = $typeBadges[strtolower($fieldType)] ?? 'bg-secondary';
                  @endphp
                  <span class="badge {{ $badgeClass }}">{{ $fieldType }}</span>
                </td>
                <td><code class="small">{{ $field->source_table ?? '-' }}</code></td>
                <td><code class="small">{{ $field->source_column ?? '-' }}</code></td>
                <td class="text-center">
                  @if(!empty($field->isad))
                    <i class="fas fa-check text-success" title="{{ $field->isad }}"></i>
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
                <td class="text-center">
                  @if(!empty($field->spectrum))
                    <i class="fas fa-check text-success" title="{{ $field->spectrum }}"></i>
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
                <td class="text-center">
                  @if(!empty($field->dc))
                    <i class="fas fa-check text-success" title="{{ $field->dc }}"></i>
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @else
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-2"></i>No field mappings have been configured.
    </div>
  @endif
</div>
@endsection
