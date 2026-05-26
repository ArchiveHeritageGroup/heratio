@extends('theme::layouts.1col')
@section('title', 'Valuer Registry')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="bi bi-person-badge me-2"></i>{{ __('Valuer Registry') }}</h1>
      <a href="{{ route('heritage.valuer.create') }}" class="btn atom-btn-white"><i class="bi bi-plus-lg me-1"></i>{{ __('Add Valuer') }}</a>
    </div>
    <p class="text-muted">{{ __('Qualified valuers used for heritage asset revaluations (GRAP 103.41 / IPSAS 45.69).') }}</p>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="get" class="row g-2 mb-3">
      <div class="col-md-6"><input type="search" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="{{ __('Search name, credential, body, accreditation') }}"></div>
      <div class="col-md-3">
        <select name="active" class="form-select">
          <option value="">{{ __('All') }}</option>
          <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
          <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
        </select>
      </div>
      <div class="col-md-3"><button class="btn atom-btn-white w-100"><i class="bi bi-search me-1"></i>{{ __('Filter') }}</button></div>
    </form>

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="bi bi-person-badge me-2"></i>{{ __('Valuers') }}</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead><tr>
              <th>{{ __('Name') }}</th>
              <th>{{ __('Credential') }}</th>
              <th>{{ __('Body') }}</th>
              <th>{{ __('Accreditation #') }}</th>
              <th>{{ __('Specialisations') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Actions') }}</th>
            </tr></thead>
            <tbody>
              @forelse($items as $v)
                @php
                  $spec = $v->specialisations ? json_decode($v->specialisations, true) : [];
                @endphp
                <tr>
                  <td>{{ $v->name }}</td>
                  <td>{{ $v->credential ?: '-' }}</td>
                  <td>{{ $v->professional_body ?: '-' }}</td>
                  <td>{{ $v->accreditation_number ?: '-' }}</td>
                  <td>{{ $spec ? implode(', ', $spec) : '-' }}</td>
                  <td>
                    @if($v->active)
                      <span class="badge bg-success">{{ __('Active') }}</span>
                    @else
                      <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                    @endif
                  </td>
                  <td>
                    <a href="{{ route('heritage.valuer.edit', ['id' => $v->id]) }}" class="btn btn-sm atom-btn-white"><i class="bi bi-pencil"></i></a>
                    @if($v->active)
                      <form method="post" action="{{ route('heritage.valuer.destroy', ['id' => $v->id]) }}" class="d-inline" onsubmit="return confirm('{{ __('Deactivate this valuer?') }}');">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm atom-btn-white"><i class="bi bi-x-lg"></i></button>
                      </form>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted py-3">{{ __('No valuers registered yet.') }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    @if(method_exists($items, 'links'))
      <div class="mt-3">{{ $items->links() }}</div>
    @endif
  </div>
</div>
@endsection
