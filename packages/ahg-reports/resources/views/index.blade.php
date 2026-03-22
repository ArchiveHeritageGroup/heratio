@extends('theme::layouts.1col')
@section('title', 'Reports Index')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-chart-bar me-2"></i>Reports</h1>
    <p class="text-muted">Select a report from the sidebar menu to get started.</p>

    <div class="row">
      @php
        $reportCards = [
          ['route' => 'reports.descriptions', 'icon' => 'fa-file-alt', 'label' => 'Archival Descriptions', 'color' => 'primary'],
          ['route' => 'reports.authorities', 'icon' => 'fa-users', 'label' => 'Authority Records', 'color' => 'success'],
          ['route' => 'reports.repositories', 'icon' => 'fa-university', 'label' => 'Repositories', 'color' => 'info'],
          ['route' => 'reports.accessions', 'icon' => 'fa-inbox', 'label' => 'Accessions', 'color' => 'warning'],
          ['route' => 'reports.donors', 'icon' => 'fa-hand-holding-heart', 'label' => 'Donors', 'color' => 'danger'],
          ['route' => 'reports.storage', 'icon' => 'fa-box', 'label' => 'Physical Storage', 'color' => 'secondary'],
        ];
      @endphp
      @foreach($reportCards as $card)
      <div class="col-md-4 mb-3">
        <a href="{{ route($card['route']) }}" class="text-decoration-none">
          <div class="card border-{{ $card['color'] }} h-100">
            <div class="card-body text-center">
              <i class="fas {{ $card['icon'] }} fa-2x text-{{ $card['color'] }} mb-2"></i>
              <h6>{{ $card['label'] }}</h6>
            </div>
          </div>
        </a>
      </div>
      @endforeach
    </div>
  </div>
</div>
@endsection