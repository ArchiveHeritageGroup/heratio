{{--
 | Privacy Officers list view - Heratio
 |
 | Copyright (C) 2026 Johan Pieterse
 | Plain Sailing Information Systems
 | Email: johan@plansailingisystems
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
@extends('theme::layouts.1col')

@section('title', 'Privacy Officers')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('ahgprivacy.dashboard') }}" class="btn btn-outline-secondary btn-sm me-2">
                <i class="fas fa-arrow-left"></i>
            </a>
            <span class="h2"><i class="fas fa-user-tie me-2"></i>Privacy Officers</span>
        </div>
        <a href="{{ route('ahgprivacy.officer-add') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Add Officer
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        @if($officers->isEmpty())
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No privacy officers configured. Add an Information Officer to comply with applicable data protection requirements.
            </div>
        </div>
        @else
        @foreach($officers as $officer)
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        {{ $officer->name }}
                        @if(!$officer->is_active)
                        <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    @if($officer->title)
                    <p class="text-muted mb-2">{{ $officer->title }}</p>
                    @endif

                    <ul class="list-unstyled mb-0">
                        <li><i class="fas fa-envelope me-2 text-muted"></i><a href="mailto:{{ $officer->email }}">{{ $officer->email }}</a></li>
                        @if($officer->phone)
                        <li><i class="fas fa-phone me-2 text-muted"></i>{{ $officer->phone }}</li>
                        @endif
                        <li><i class="fas fa-globe me-2 text-muted"></i>
                            @php
                                $jInfo = $jurisdictions[$officer->jurisdiction] ?? null;
                                echo $jInfo ? e($jInfo['name']) : e(ucfirst($officer->jurisdiction));
                            @endphp
                        </li>
                        @if($officer->registration_number)
                        <li><i class="fas fa-id-card me-2 text-muted"></i>Reg: {{ $officer->registration_number }}</li>
                        @endif
                        @if($officer->appointed_date)
                        <li><i class="fas fa-calendar me-2 text-muted"></i>Appointed: {{ $officer->appointed_date }}</li>
                        @endif
                    </ul>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="{{ route('ahgprivacy.officer-edit', ['id' => $officer->id]) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                </div>
            </div>
        </div>
        @endforeach
        @endif
    </div>

    {{-- Registration Info --}}
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Registration Requirements</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <h6><span class="fi fi-za me-2"></span>POPIA (South Africa)</h6>
                    <p class="small">Information Officer must be registered with the Information Regulator. Deputy Information Officers should also be designated.</p>
                </div>
                <div class="col-md-4">
                    <h6><span class="fi fi-ng me-2"></span>NDPA (Nigeria)</h6>
                    <p class="small">Data Protection Officer required for major data controllers. Registration with NDPC.</p>
                </div>
                <div class="col-md-4">
                    <h6><span class="fi fi-eu me-2"></span>GDPR (EU)</h6>
                    <p class="small">DPO required for public authorities and large-scale processing. Contact details must be published.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
