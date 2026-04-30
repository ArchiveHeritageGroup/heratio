@extends('theme::layouts.1col')
@section('title', 'Edit Item')
@section('body-class', 'admin heritage')

@php
$itemData = (array)($itemData ?? []);
$item = $itemData['item'] ?? null;
$history = (array)($itemData['history'] ?? []);
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-heritage-manage::partials._admin-sidebar')
    @if($item)
    <div class="card border-0 shadow-sm mt-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0">{{ __('Item Info') }}</h6></div>
      <div class="card-body">
        <small class="text-muted d-block mb-2">{{ __('Reference Code') }}</small><p class="mb-3">{{ $item->identifier ?? '-' }}</p>
        <small class="text-muted d-block mb-2">{{ __('Level') }}</small><p class="mb-3">{{ $item->level_of_description ?? '-' }}</p>
        <small class="text-muted d-block mb-2">{{ __('Created') }}</small><p class="mb-0">{{ date('M d, Y', strtotime($item->created_at)) }}</p>
      </div>
    </div>
    @endif
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-edit me-2"></i>{{ __('Edit Item') }}</h1>

    @if(!$item)
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>{{ __('Item not found.') }}</div>
    @else
    <form method="post">@csrf
      @foreach([
        ['Identity Area (ISAD 3.1)','fas fa-fingerprint',[['identifier','Reference Code','text','col-md-4'],['title','Title','text','col-12'],['extent_and_medium','Extent and Medium','text','col-md-6'],['date','Date(s)','text','col-md-4']]],
        ['Context Area (ISAD 3.2)','fas fa-sitemap',[['creator','Name of Creator(s)','text','col-12'],['administrative_history','Admin/Bio History','textarea','col-12'],['archival_history','Archival History','textarea','col-12'],['immediate_source','Immediate Source','textarea','col-12']]],
        ['Content and Structure (ISAD 3.3)','fas fa-file-alt',[['scope_and_content','Scope and Content','textarea','col-12'],['appraisal','Appraisal','textarea','col-12'],['accruals','Accruals','textarea','col-12'],['arrangement','System of Arrangement','textarea','col-12']]],
        ['Access and Use (ISAD 3.4)','fas fa-lock',[['access_conditions','Conditions Governing Access','textarea','col-12'],['reproduction_conditions','Conditions Governing Reproduction','textarea','col-12'],['language','Language/Script','text','col-md-6'],['physical_characteristics','Physical Characteristics','text','col-md-6'],['finding_aids','Finding Aids','textarea','col-12']]],
        ['Allied Materials (ISAD 3.5)','fas fa-link',[['location_of_originals','Location of Originals','textarea','col-12'],['location_of_copies','Location of Copies','textarea','col-12'],['related_materials','Related Units','textarea','col-12'],['publication_note','Publication Note','textarea','col-12']]],
        ['Notes (ISAD 3.6)','fas fa-sticky-note',[['general_note','General Note','textarea','col-12']]],
        ['Description Control (ISAD 3.7)','fas fa-tasks',[['archivist_note',"Archivist's Note",'textarea','col-md-6'],['rules_or_conventions','Rules or Conventions','textarea','col-md-6'],['date_of_description','Date(s) of Description','text','col-md-6']]]
      ] as [$title,$icon,$fields])
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="{{ $icon }} me-2"></i>{{ $title }}</h5></div>
        <div class="card-body"><div class="row g-3">
          @foreach($fields as [$name,$label,$type,$col])
          <div class="{{ $col }}"><label for="{{ $name }}" class="form-label">{{ $label }}</label>@if($type==='textarea')<textarea class="form-control" id="{{ $name }}" name="{{ $name }}" rows="3">{{ $item->$name ?? '' }}</textarea>@else<input type="text" class="form-control" id="{{ $name }}" name="{{ $name }}" value="{{ $item->$name ?? '' }}">@endif</div>
          @endforeach
        </div></div>
      </div>
      @endforeach

      <div class="d-flex justify-content-between">
        <a href="{{ route('heritage.custodian') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Cancel') }}</a>
        <div><button type="submit" name="form_action" value="save" class="btn atom-btn-secondary"><i class="fas fa-check me-2"></i>{{ __('Save Changes') }}</button><button type="submit" name="form_action" value="save_continue" class="btn atom-btn-white ms-2">{{ __('Save & Continue') }}</button></div>
      </div>
    </form>
    @endif
  </div>
</div>
@endsection
