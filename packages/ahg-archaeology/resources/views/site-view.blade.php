{{-- Archaeological site detail --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h1 class="h4 mb-1">{{ $site->title ?: __('Untitled site') }}</h1>
      <div class="text-muted small">
        {{ $site->site_number }}
        @if($site->national_site_number) &middot; {{ $site->national_site_number }} @endif
      </div>
    </div>
    <a href="{{ route('archaeology.sites') }}" class="btn btn-outline-secondary btn-sm">&larr; {{ __('All sites') }}</a>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card mb-3">
        <div class="card-header">{{ __('Site') }}</div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-sm-4">{{ __('Type') }}</dt><dd class="col-sm-8">{{ $site->site_type_name ?: '-' }}</dd>
            <dt class="col-sm-4">{{ __('Period') }}</dt><dd class="col-sm-8">{{ $site->period_name ?: '-' }}</dd>
            <dt class="col-sm-4">{{ __('Date range') }}</dt>
            <dd class="col-sm-8">
              {{ $site->date_earliest ?: '?' }} &ndash; {{ $site->date_latest ?: '?' }}
              @if($site->dating_note)<div class="text-muted">{{ $site->dating_note }}</div>@endif
            </dd>
            <dt class="col-sm-4">{{ __('Region') }}</dt><dd class="col-sm-8">{{ $site->region ?: '-' }}</dd>
            <dt class="col-sm-4">{{ __('Locality') }}</dt><dd class="col-sm-8">{{ $site->locality ?: '-' }}</dd>
            @if($site->location_description)
              <dt class="col-sm-4">{{ __('Location') }}</dt><dd class="col-sm-8">{{ $site->location_description }}</dd>
            @endif
            <dt class="col-sm-4">{{ __('Area') }}</dt>
            <dd class="col-sm-8">{{ $site->area_sqm ? number_format($site->area_sqm, 2).' m&sup2;' : '-' }}</dd>
          </dl>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">{{ __('Investigation') }}</div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-sm-4">{{ __('Excavated') }}</dt>
            <dd class="col-sm-8">
              @if($site->excavated)
                <span class="badge bg-info">{{ __('Yes') }}</span>
                @if($site->excavation_years) {{ $site->excavation_years }} @endif
              @else
                {{ __('Not excavated') }}
              @endif
            </dd>
            @if($site->excavator)
              <dt class="col-sm-4">{{ __('Excavator') }}</dt><dd class="col-sm-8">{{ $site->excavator }}</dd>
            @endif
            @if($site->excavation_institution)
              <dt class="col-sm-4">{{ __('Institution') }}</dt><dd class="col-sm-8">{{ $site->excavation_institution }}</dd>
            @endif
            @if($site->permit_number)
              <dt class="col-sm-4">{{ __('Permit') }}</dt><dd class="col-sm-8">{{ $site->permit_number }}</dd>
            @endif
            @if($site->discovered_by)
              <dt class="col-sm-4">{{ __('Discovered by') }}</dt>
              <dd class="col-sm-8">{{ $site->discovered_by }}
                @if($site->discovery_date) ({{ $site->discovery_date }}) @endif
              </dd>
            @endif
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card mb-3">
        <div class="card-header">{{ __('Location') }}</div>
        <div class="card-body small">
          @if($site->latitude && $site->longitude)
            <div class="font-monospace">{{ $site->latitude }}, {{ $site->longitude }}</div>
            @if($site->elevation_m)<div class="text-muted">{{ __('Elevation') }}: {{ $site->elevation_m }} m</div>@endif
            @if($site->spatial_accuracy_m)
              <div class="text-muted">{{ __('Accuracy') }}: &plusmn;{{ $site->spatial_accuracy_m }} m</div>
            @else
              {{-- Blank accuracy is not the same as an exact fix; say so rather
                   than let a bare coordinate imply precision it may not have. --}}
              <div class="text-warning">{{ __('Positional accuracy not recorded') }}</div>
            @endif
          @else
            <span class="text-muted">{{ __('No coordinates recorded.') }}</span>
          @endif
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">{{ __('Management') }}</div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-sm-5">{{ __('Protection') }}</dt>
            <dd class="col-sm-7">{{ $site->protection_status_name ?: __('Unassessed') }}</dd>
            <dt class="col-sm-5">{{ __('Research potential') }}</dt>
            <dd class="col-sm-7">{{ $site->research_potential ?: '-' }}</dd>
          </dl>
          @if($site->threats)
            <hr class="my-2">
            <div class="small"><strong>{{ __('Threats') }}:</strong> {{ $site->threats }}</div>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header">{{ __('Assemblage') }}</div>
        <div class="card-body p-0">
          @if($assemblage->isEmpty())
            <p class="text-muted small p-3 mb-0">{{ __('No finds linked to this site.') }}</p>
          @else
            <table class="table table-sm mb-0">
              <thead><tr>
                <th>{{ __('Material') }}</th>
                <th class="text-end">{{ __('Records') }}</th>
                <th class="text-end">{{ __('Items') }}</th>
              </tr></thead>
              <tbody>
              @foreach($assemblage as $row)
                <tr>
                  <td>{{ $row->material }}</td>
                  <td class="text-end text-muted">{{ number_format($row->records) }}</td>
                  <td class="text-end">{{ number_format($row->items) }}</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          @endif
        </div>
      </div>
    </div>
  </div>

  <h2 class="h5 mt-4 mb-2">{{ __('Finds from this site') }}</h2>
  @if($finds->isEmpty())
    <div class="alert alert-info small">{{ __('No finds recorded against this site.') }}</div>
  @else
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead><tr>
          <th>{{ __('Accession no.') }}</th>
          <th>{{ __('Object type') }}</th>
          <th>{{ __('Material') }}</th>
          <th>{{ __('Context') }}</th>
          <th class="text-end">{{ __('Items') }}</th>
        </tr></thead>
        <tbody>
        @foreach($finds as $f)
          <tr>
            <td><a href="{{ route('archaeology.object', $f->id) }}">{{ $f->accession_number }}</a></td>
            <td>{{ $f->object_type_name ?: '-' }}</td>
            <td>{{ $f->material_name ?: '-' }}</td>
            <td>{{ $f->context_reference ?: '-' }}</td>
            <td class="text-end">{{ number_format($f->item_count) }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    {{ $finds->links() }}
  @endif

</div>
@endsection
