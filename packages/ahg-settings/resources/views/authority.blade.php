{{--
  Authority Records Settings stub
  Copyright (C) 2026 Johan Pieterse — Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Authority Records Settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('content')
  <h1><i class="fas fa-id-card me-2"></i>Authority Records Settings</h1>
  <p class="text-muted">External linking, completeness, NER pipeline, merge/dedup, occupations, functions</p>

  <div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>This settings page is under development.
  </div>
@endsection
