{{--
  EAD2002 / EAD3 import - upload, preview, commit (#657 Phase 1).

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', __('EAD Import'))

@section('content')
<div class="d-flex align-items-center mb-3">
  <h1 class="mb-0"><i class="bi bi-file-earmark-arrow-up"></i> {{ __('EAD Import') }}</h1>
  <a href="{{ route('ahgmetadataexport.index') }}" class="btn btn-outline-secondary btn-sm ms-auto">
    <i class="bi bi-arrow-left"></i> {{ __('Back to Metadata Export') }}
  </a>
</div>

<div class="alert alert-info">
  <i class="bi bi-info-circle"></i>
  {{ __('Upload an EAD 2002 or EAD 3 finding aid (one <ead> document). Heratio detects the variant, validates against the vendored XSD, shows a tree preview, then commits to the archival catalogue.') }}
</div>

@if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $message)
        <li>{{ $message }}</li>
      @endforeach
    </ul>
  </div>
@endif

@if ($stage === 'upload')
  <div class="card">
    <div class="card-header">
      <h2 class="card-title h5 mb-0">{{ __('Step 1 - Upload EAD XML file') }}</h2>
    </div>
    <div class="card-body">
      <form method="post" action="{{ route('ahgmetadataexport.ead.import.preview') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
          <label for="eadxml" class="form-label">{{ __('EAD XML file') }}</label>
          <input type="file" name="eadxml" id="eadxml" class="form-control" required accept=".xml,.ead,text/xml,application/xml">
          <div class="form-text">{{ __('Up to 100 MB. Variant detected automatically: EAD 2002 (urn:isbn:1-931666-22-9) or EAD 3 (http://ead3.archivists.org/schema/).') }}</div>
        </div>
        <div class="mb-3">
          <label for="culture" class="form-label">{{ __('Culture / language code') }}</label>
          <input type="text" name="culture" id="culture" class="form-control" value="{{ $culture }}" maxlength="16">
          <div class="form-text">{{ __('Two-letter culture code (en, fr, af, ...) used to write the i18n side of the import.') }}</div>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-eye"></i> {{ __('Validate & Preview') }}
        </button>
      </form>
    </div>
  </div>
@endif

@if ($stage === 'preview')
  <div class="alert alert-secondary">
    <strong>{{ __('Detected variant:') }}</strong>
    @if ($variant === 'ead3')
      <span class="badge bg-success">EAD 3</span>
    @elseif ($variant === 'ead2002')
      <span class="badge bg-primary">EAD 2002</span>
    @else
      <span class="badge bg-warning text-dark">{{ __('Unknown') }}</span>
    @endif
  </div>

  @if (!empty($errors) && is_array($errors))
    <div class="alert alert-warning">
      <h2 class="h6"><i class="bi bi-exclamation-triangle"></i> {{ __('Schema validation notes') }}</h2>
      <ul class="mb-0 small">
        @foreach ($errors as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if (!$tree)
    <div class="alert alert-secondary">{{ __('No <archdesc> element was parsed from the upload.') }}</div>
    <a href="{{ route('ahgmetadataexport.ead.import') }}" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left"></i> {{ __('Start over') }}
    </a>
  @else
    <div class="card mb-3">
      <div class="card-header">
        <h2 class="card-title h5 mb-0">{{ __('Step 2 - Preview') }}</h2>
      </div>
      <div class="card-body">
        @php
          // Inline recursive renderer; avoids a separate partial since
          // the tree is bounded by the IO depth in the finding aid.
          $render = function ($node, $depth = 0) use (&$render) {
            $pad = str_repeat('  ', $depth);
            $title = $node['title'] ?? '(untitled)';
            $unitid = $node['unitid'] ?? null;
            $badge = !empty($node['will_create'])
              ? '<span class="badge bg-success">CREATE</span>'
              : '<span class="badge bg-warning text-dark">UPDATE #'.$node['matched_io_id'].'</span>';
            echo '<div style="padding-left:'.($depth * 1.2).'em" class="border-bottom py-1">';
            echo $badge.' ';
            if ($unitid) {
              echo '<code class="me-2">'.e($unitid).'</code>';
            }
            echo '<strong>'.e($title).'</strong> ';
            echo '<span class="small text-muted">('.e($node['level'] ?? 'otherlevel').')</span>';
            if (!empty($node['warnings'])) {
              foreach ($node['warnings'] as $w) {
                echo '<div class="small text-warning">'.e($w).'</div>';
              }
            }
            echo '</div>';
            foreach ($node['children'] ?? [] as $c) {
              $render($c, $depth + 1);
            }
          };
          $render($tree);
        @endphp
      </div>
    </div>

    @if ($valid ?? false)
      <form method="post" action="{{ route('ahgmetadataexport.ead.import.commit') }}">
        @csrf
        <input type="hidden" name="eadxml_payload" value="{{ session('eadxml_payload') }}">
        <input type="hidden" name="culture" value="{{ session('eadxml_culture', 'en') }}">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check2-circle"></i> {{ __('Commit Import') }}
        </button>
        <a href="{{ route('ahgmetadataexport.ead.import') }}" class="btn btn-outline-secondary">
          <i class="bi bi-x"></i> {{ __('Cancel') }}
        </a>
      </form>
    @else
      <div class="alert alert-danger">
        <i class="bi bi-shield-exclamation"></i> {{ __('Fix the validation issues above before committing.') }}
        <a href="{{ route('ahgmetadataexport.ead.import') }}" class="btn btn-sm btn-outline-secondary ms-2">
          {{ __('Start over') }}
        </a>
      </div>
    @endif
  @endif
@endif

@if ($stage === 'committed')
  <div class="card">
    <div class="card-header">
      <h2 class="card-title h5 mb-0">{{ __('Import results') }}</h2>
    </div>
    <div class="card-body">
      @php
        $renderResult = function ($node, $depth = 0) use (&$renderResult) {
          $title = $node['title'] ?? '(untitled)';
          $action = $node['action'] ?? 'skipped';
          $colour = match($action) {
            'create' => 'bg-success',
            'update' => 'bg-warning text-dark',
            'error' => 'bg-danger',
            default => 'bg-secondary',
          };
          echo '<div style="padding-left:'.($depth * 1.2).'em" class="border-bottom py-1">';
          echo '<span class="badge '.$colour.'">'.strtoupper(e($action)).'</span> ';
          if (!empty($node['io_id'])) {
            echo '<span class="small text-muted">#'.e($node['io_id']).'</span> ';
          }
          if (!empty($node['unitid'])) {
            echo '<code class="me-2">'.e($node['unitid']).'</code>';
          }
          echo '<strong>'.e($title).'</strong>';
          if (!empty($node['error'])) {
            echo '<div class="small text-danger">'.e($node['error']).'</div>';
          }
          echo '</div>';
          foreach ($node['children'] ?? [] as $c) {
            $renderResult($c, $depth + 1);
          }
        };
        if ($tree) {
          $renderResult($tree);
        } else {
          echo '<p class="text-muted">'.__('Nothing was parsed.').'</p>';
        }
      @endphp
    </div>
  </div>
  <a href="{{ route('ahgmetadataexport.ead.import') }}" class="btn btn-outline-primary mt-3">
    <i class="bi bi-arrow-repeat"></i> {{ __('Import another file') }}
  </a>
@endif
@endsection
