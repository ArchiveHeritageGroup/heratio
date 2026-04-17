{{--
 | Extended Rights Dashboard
 |
 | Copyright (C) 2026 Johan Pieterse
 | Plain Sailing Information Systems
 | Email: johan@plansailingisystems.co.za
 |
 | This file is part of Heratio.
 |
 | Heratio is free software: you can redistribute it and/or modify
 | it under the terms of the GNU Affero General Public License as published by
 | the Free Software Foundation, either version 3 of the License, or
 | (at your option) any later version.
 |
 | Heratio is distributed in the hope that it will be useful,
 | but WITHOUT ANY WARRANTY; without even the implied warranty of
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 | GNU Affero General Public License for more details.
 |
 | You should have received a copy of the GNU Affero General Public License
 | along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 --}}
@extends('theme::layouts.2col')

@section('title', __('Extended Rights Dashboard'))
@section('body-class', 'admin rights-admin')

@section('sidebar')
  @include('ahg-extended-rights::admin._sidebar')
@endsection

@section('title-block')
  <h1 class="mb-0"><i class="fas fa-copyright me-2"></i>{{ __('Extended Rights Dashboard') }}</h1>
@endsection

@section('content')
  {{-- Statistics Cards --}}
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card text-white bg-primary">
        <div class="card-body">
          <h5 class="card-title">{{ __('Objects with Rights') }}</h5>
          <h2>{{ number_format($stats['total_rights_records'] ?? 0) }}</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-warning">
        <div class="card-body">
          <h5 class="card-title">{{ __('Active Embargoes') }}</h5>
          <h2>{{ number_format($stats['active_embargoes'] ?? 0) }}</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-danger">
        <div class="card-body">
          <h5 class="card-title">{{ __('Expiring Soon') }}</h5>
          <h2>{{ number_format($stats['expiring_soon'] ?? 0) }}</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-info">
        <div class="card-body">
          <h5 class="card-title">{{ __('Inherited Rights') }}</h5>
          <h2>{{ number_format($stats['tk_label_assignments'] ?? 0) }}</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    {{-- Rights Statements --}}
    <div class="col-md-6 mb-4">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>{{ __('By Rights Statement') }}</h5>
        </div>
        <div class="card-body">
          @if (!empty($stats['by_rights_statement']))
          <table class="table table-sm">
            <thead><tr><th>{{ __('Statement') }}</th><th class="text-end">{{ __('Count') }}</th></tr></thead>
            <tbody>
              @foreach ($stats['by_rights_statement'] as $code => $count)
              <tr>
                <td><span class="badge bg-secondary me-1">{{ $code }}</span></td>
                <td class="text-end">{{ number_format($count) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
          @else
          <p class="text-muted">{{ __('No rights statements assigned yet.') }}</p>
          @endif
        </div>
      </div>
    </div>

    {{-- CC Licenses --}}
    <div class="col-md-6 mb-4">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0"><i class="fab fa-creative-commons me-2"></i>{{ __('By CC License') }}</h5>
        </div>
        <div class="card-body">
          @if (!empty($stats['by_cc_license']))
          <table class="table table-sm">
            <thead><tr><th>{{ __('License') }}</th><th class="text-end">{{ __('Count') }}</th></tr></thead>
            <tbody>
              @foreach ($stats['by_cc_license'] as $code => $count)
              <tr>
                <td><span class="badge bg-success me-1">{{ $code }}</span></td>
                <td class="text-end">{{ number_format($count) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
          @else
          <p class="text-muted">{{ __('No CC licenses assigned yet.') }}</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Quick Actions --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>{{ __('Quick Actions') }}</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-3">
          <a href="{{ url('/admin/rights/batch') }}" class="btn btn-outline-primary w-100 mb-2">
            <i class="fas fa-layer-group me-1"></i>{{ __('Batch Assign Rights') }}
          </a>
        </div>
        <div class="col-md-3">
          <a href="{{ url('/admin/rights/browse') }}" class="btn btn-outline-warning w-100 mb-2">
            <i class="fas fa-lock me-1"></i>{{ __('Manage Embargoes') }}
          </a>
        </div>
        <div class="col-md-3">
          <a href="{{ url('/admin/rights/export') }}" class="btn btn-outline-success w-100 mb-2">
            <i class="fas fa-download me-1"></i>{{ __('Export Rights') }}
          </a>
        </div>
        <div class="col-md-3">
          <a href="{{ url('/admin/settings') }}" class="btn btn-outline-secondary w-100 mb-2">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}
          </a>
        </div>
      </div>
    </div>
  </div>
@endsection
