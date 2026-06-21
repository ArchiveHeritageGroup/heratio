{{--
  Compliance Control Catalog - admin index. Vendor- and jurisdiction-agnostic:
  regulatory obligations mapped to controls + recommended configuration. Read-only
  governance reference backing the legal-mapping annex of the AI for RM/Archives
  framework. A queryable JSON artefact is at /admin/privacy/control-catalog.json.
--}}
@extends('theme::layouts.1col')

@section('title', __('Compliance Control Catalog'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Compliance Control Catalog') }}</h1>
            <p class="text-muted mb-0">{{ __('Regulatory obligations mapped to governance, privacy and access controls, with recommended configuration. Vendor- and jurisdiction-agnostic; add local regimes as mappings.') }}</p>
        </div>
        <a href="{{ route('ahgprivacy.control-catalog.json') }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
            <i class="fas fa-code me-1"></i>{{ __('JSON artefact') }}
        </a>
    </div>

    {{-- Filters: free-text search + regime lens --}}
    <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-sm-5 col-md-4">
            <label class="form-label small mb-1">{{ __('Search controls') }}</label>
            <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="{{ __('e.g. lawful basis, residency, audit') }}">
        </div>
        <div class="col-sm-5 col-md-4">
            <label class="form-label small mb-1">{{ __('View a regime') }}</label>
            <select name="regime" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All controls') }}</option>
                @foreach($regimes as $r)
                    <option value="{{ $r }}" @selected($regime === $r)>{{ $r }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Apply') }}</button>
            @if($q !== '' || $regime !== '')
                <a href="{{ route('ahgprivacy.control-catalog') }}" class="btn btn-link btn-sm">{{ __('Reset') }}</a>
            @endif
        </div>
    </form>

    @if($regime !== '')
        {{-- Regime lens: obligation -> control -> recommended config --}}
        <h2 class="h5 mb-2">{{ __('Obligations for') }} <span class="text-primary">{{ $regime }}</span></h2>
        @if(empty($regimeMappings))
            <div class="alert alert-info">{{ __('No mappings recorded for this regime yet.') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('Obligation') }}</th>
                            <th>{{ __('Control') }}</th>
                            <th>{{ __('Recommended configuration') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($regimeMappings as $m)
                            <tr>
                                <td>{{ $m['obligation'] ?? '' }}</td>
                                <td><span class="badge bg-dark">{{ $m['control_id'] }}</span> {{ $m['control_name'] ?? '' }}</td>
                                <td class="small text-muted">{{ $m['recommended_config'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <hr class="my-4">
    @endif

    {{-- Control catalogue --}}
    <h2 class="h5 mb-2">{{ __('Controls') }} <span class="text-muted small">({{ count($controls) }})</span></h2>
    @if(empty($controls))
        <div class="alert alert-warning">{{ __('The control catalogue is not available yet.') }}</div>
    @else
        <div class="row g-3">
            @foreach($controls as $c)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="badge bg-dark">{{ $c['control_id'] }}</span>
                                <span class="badge bg-light text-dark text-uppercase">{{ $c['category'] ?? '' }}</span>
                            </div>
                            <h3 class="h6 mb-2">{{ $c['control_name'] }}</h3>
                            @if(!empty($c['objective']))
                                <p class="small mb-2">{{ $c['objective'] }}</p>
                            @endif
                            @if(!empty($c['recommended_config']))
                                <p class="small text-muted mb-2"><strong>{{ __('Config:') }}</strong> {{ $c['recommended_config'] }}</p>
                            @endif
                            @if(!empty($c['standards_refs']))
                                <p class="small text-muted mb-0"><i class="fas fa-book me-1"></i>{{ $c['standards_refs'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
