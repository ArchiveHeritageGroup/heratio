@extends('theme::layouts.3col')
@section('title', 'Reports Browse')
@section('body-class', 'admin reports')

@section('sidebar')
<section id="advanced-search-filters">

  <div class="card mb-3">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-chart-bar me-2"></i>{{ __('Reports') }}
    </div>
    <div class="card-body">

      <div class="mb-3">
        <label class="form-label fw-bold">{{ __('Strong Rooms:') }}</label>
        <select name="dropd" id="dropd" class="form-select form-select-sm" onchange="setStrongroomCookie()">
          <option value="Select">{{ __('Select') }}</option>
          @foreach($strongrooms ?? [] as $room)
            <option value="{{ $room }}">{{ $room }}</option>
          @endforeach
          <option value="All">{{ __('All') }}</option>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold">{{ __('Location:') }}</label>
        <select name="dropl" id="dropl" class="form-select form-select-sm" onchange="setLocationCookie()">
          <option value="Select">{{ __('Select') }}</option>
          @foreach($locations ?? [] as $loc)
            <option value="{{ $loc }}">{{ $loc }}</option>
          @endforeach
          <option value="All">{{ __('All') }}</option>
        </select>
      </div>

      <script>
      function setStrongroomCookie() {
        var x = document.getElementById("dropd");
        createCookie('strongroom', x.value, 1, '/');
      }

      function setLocationCookie() {
        var x = document.getElementById("dropl");
        createCookie('strongroom2', x.value, 1, '/');
      }

      function createCookie(name, value, days2expire, path) {
        var date = new Date();
        date.setTime(date.getTime() + (days2expire * 24 * 60 * 60 * 1000));
        var expires = date.toUTCString();
        document.cookie = name + '=' + value + ';expires=' + expires + ';path=' + path + ';';
      }
      </script>

      <div class="d-grid gap-2">
        <a href="{{ route('reports.browse', ['action' => 'search']) }}" class="btn btn-sm atom-btn-white">
          <i class="fas fa-search me-1"></i>{{ __('Search') }}
        </a>
      </div>

    </div>
  </div>

  <div class="list-group mb-3">
    <a href="{{ route('reports.browse', ['export' => 'strongrooms']) }}" class="list-group-item list-group-item-action">
      <i class="fas fa-file-export me-2 text-muted"></i>{{ __('Strongrooms Export') }}
    </a>
    <a href="{{ url('/physicalobject/browse?booked_out=1') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-sign-out-alt me-2 text-muted"></i>{{ __('Booked Out') }}
    </a>
    <a href="{{ route('reports.browse-publish') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-eye me-2 text-muted"></i>{{ __('Publish') }}
    </a>
  </div>

</section>
@endsection

@section('title-block')
<h1><i class="fas fa-list me-2"></i>{{ __('Browse Reports') }}</h1>
@endsection

@section('content')
<p class="text-muted mb-4">
  Use the sidebar filters to select a strong room or location, then click Search to filter physical storage records.
  You can also export strongroom data or view booked out items.
</p>

<div class="card mb-3">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <i class="fas fa-info-circle me-2"></i>{{ __('Available Actions') }}
  </div>
  <div class="card-body">
    <table class="table table-sm table-bordered mb-0">
      <thead>
        <tr>
          <th style="background:var(--ahg-primary);color:#fff">{{ __('Action') }}</th>
          <th style="background:var(--ahg-primary);color:#fff">{{ __('Description') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><i class="fas fa-search me-1 text-muted"></i>Search</td>
          <td>Filter physical storage records by the selected strong room and location</td>
        </tr>
        <tr>
          <td><i class="fas fa-file-export me-1 text-muted"></i>Strongrooms Export</td>
          <td>Export all strong room box labels as CSV</td>
        </tr>
        <tr>
          <td><i class="fas fa-sign-out-alt me-1 text-muted"></i>Booked Out</td>
          <td>Browse items that are currently booked out from physical storage</td>
        </tr>
        <tr>
          <td><i class="fas fa-eye me-1 text-muted"></i>Publish</td>
          <td>Manage publication status of archival descriptions</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
@endsection

@section('right')
<div class="card mb-3">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <i class="fas fa-link me-2"></i>{{ __('Quick Links') }}
  </div>
  <div class="list-group list-group-flush">
    <a href="{{ route('reports.dashboard') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-tachometer-alt me-2" style="width:18px"></i>{{ __('Dashboard') }}
    </a>
    <a href="{{ route('reports.descriptions') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-file-alt me-2" style="width:18px"></i>{{ __('Descriptions') }}
    </a>
    <a href="{{ route('reports.authorities') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-user me-2" style="width:18px"></i>{{ __('Authority Records') }}
    </a>
    <a href="{{ route('reports.repositories') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-university me-2" style="width:18px"></i>{{ __('Repositories') }}
    </a>
    <a href="{{ route('reports.accessions') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-inbox me-2" style="width:18px"></i>{{ __('Accessions') }}
    </a>
    <a href="{{ route('reports.donors') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-hand-holding-heart me-2" style="width:18px"></i>{{ __('Donors') }}
    </a>
    <a href="{{ route('reports.storage') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-box me-2" style="width:18px"></i>{{ __('Physical Storage') }}
    </a>
    <a href="{{ route('reports.taxonomy') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-tags me-2" style="width:18px"></i>{{ __('Taxonomies') }}
    </a>
    <a href="{{ route('reports.recent') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-clock me-2" style="width:18px"></i>{{ __('Recent Updates') }}
    </a>
    <a href="{{ route('reports.spatial') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-map-marker-alt me-2" style="width:18px"></i>{{ __('Spatial Analysis') }}
    </a>
    <a href="{{ route('reports.activity') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-history me-2" style="width:18px"></i>{{ __('User Activity') }}
    </a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <i class="fas fa-clipboard-check me-2"></i>{{ __('Audit Reports') }}
  </div>
  <div class="list-group list-group-flush">
    <a href="{{ route('reports.audit.actor') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-user me-2" style="width:18px"></i>{{ __('Audit Actors') }}
    </a>
    <a href="{{ route('reports.audit.description') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-file-alt me-2" style="width:18px"></i>{{ __('Audit Descriptions') }}
    </a>
    <a href="{{ route('reports.audit.donor') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-hand-holding-heart me-2" style="width:18px"></i>{{ __('Audit Donors') }}
    </a>
    <a href="{{ route('reports.audit.permissions') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-key me-2" style="width:18px"></i>{{ __('Audit Permissions') }}
    </a>
    <a href="{{ route('reports.audit.physical-storage') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-box me-2" style="width:18px"></i>{{ __('Audit Physical Storage') }}
    </a>
    <a href="{{ route('reports.audit.repository') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-university me-2" style="width:18px"></i>{{ __('Audit Repository') }}
    </a>
    <a href="{{ route('reports.audit.taxonomy') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-tags me-2" style="width:18px"></i>{{ __('Audit Taxonomy') }}
    </a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <i class="fas fa-wrench me-2"></i>{{ __('Tools') }}
  </div>
  <div class="list-group list-group-flush">
    <a href="{{ route('reports.select') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-file-export me-2" style="width:18px"></i>{{ __('Report Select') }}
    </a>
    <a href="{{ route('reports.report') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-chart-bar me-2" style="width:18px"></i>{{ __('Generic Report') }}
    </a>
    @if(Route::has('reports.builder.index'))
    <a href="{{ route('reports.builder.index') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-tools me-2" style="width:18px"></i>{{ __('Report Builder') }}
    </a>
    @endif
    <a href="{{ route('reports.report-access') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-shield-alt me-2" style="width:18px"></i>{{ __('Access Report') }}
    </a>
    <a href="{{ route('reports.report-accession') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-inbox me-2" style="width:18px"></i>{{ __('Accession Report') }}
    </a>
    <a href="{{ route('reports.report-authority-record') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-users me-2" style="width:18px"></i>{{ __('Authority Record Report') }}
    </a>
    <a href="{{ route('reports.report-donor') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-hand-holding-heart me-2" style="width:18px"></i>{{ __('Donor Report') }}
    </a>
    <a href="{{ route('reports.report-information-object') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-archive me-2" style="width:18px"></i>{{ __('Information Object Report') }}
    </a>
    <a href="{{ route('reports.report-physical-storage') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-boxes me-2" style="width:18px"></i>{{ __('Physical Storage Report') }}
    </a>
    <a href="{{ route('reports.report-repository') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-building me-2" style="width:18px"></i>{{ __('Repository Report') }}
    </a>
    <a href="{{ route('reports.report-spatial-analysis') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-map-marker-alt me-2" style="width:18px"></i>{{ __('Spatial Analysis Report') }}
    </a>
    <a href="{{ route('reports.report-taxonomy-audit') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-tags me-2" style="width:18px"></i>{{ __('Taxonomy Audit Report') }}
    </a>
    <a href="{{ route('reports.report-updates') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-sync me-2" style="width:18px"></i>{{ __('Updates Report') }}
    </a>
    <a href="{{ route('reports.report-user') }}" class="list-group-item list-group-item-action">
      <i class="fas fa-user-clock me-2" style="width:18px"></i>{{ __('User Report') }}
    </a>
  </div>
</div>
@endsection
