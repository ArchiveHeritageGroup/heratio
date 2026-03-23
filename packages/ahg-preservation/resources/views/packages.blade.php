@extends('theme::layouts.1col')

@section('title', 'OAIS Packages - Preservation')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
    <div class="col-md-3">
        @include('ahg-preservation::_menu')
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h1 class="mb-0"><i class="fas fa-box"></i> OAIS Packages</h1>
        </div>
        <p class="text-muted mb-3">Submission, Archival, and Dissemination Information Packages</p>

        {{-- Statistics Cards --}}
        @php
            $sipCount = $packages->where('package_type', 'SIP')->count() + $packages->where('package_type', 'sip')->count();
            $aipCount = $packages->where('package_type', 'AIP')->count() + $packages->where('package_type', 'aip')->count();
            $dipCount = $packages->where('package_type', 'DIP')->count() + $packages->where('package_type', 'dip')->count();
            $totalSize = $packages->sum('total_size');
        @endphp
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-white-50">Total Packages</h6>
                                <h2 class="mb-0">{{ number_format($packages->count()) }}</h2>
                                <small>{{ $totalSize ? number_format($totalSize / 1048576, 1) . ' MB' : '' }}</small>
                            </div>
                            <i class="fas fa-box fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-white-50">SIPs</h6>
                                <h2 class="mb-0">{{ number_format($sipCount) }}</h2>
                                <small>Submission</small>
                            </div>
                            <i class="fas fa-arrow-circle-right fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-white-50">AIPs</h6>
                                <h2 class="mb-0">{{ number_format($aipCount) }}</h2>
                                <small>Archival</small>
                            </div>
                            <i class="fas fa-archive fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">DIPs</h6>
                                <h2 class="mb-0">{{ number_format($dipCount) }}</h2>
                                <small>Dissemination</small>
                            </div>
                            <i class="fas fa-share-square fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Type Filter + Actions --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="btn-group" role="group">
                <a href="{{ route('preservation.packages') }}" class="btn btn-sm {{ !$type ? 'atom-btn-outline-success' : 'atom-btn-white' }}">All</a>
                <a href="{{ route('preservation.packages', ['type' => 'SIP']) }}" class="btn btn-sm {{ $type === 'SIP' ? 'atom-btn-outline-success' : 'atom-btn-white' }}">SIP</a>
                <a href="{{ route('preservation.packages', ['type' => 'AIP']) }}" class="btn btn-sm {{ $type === 'AIP' ? 'atom-btn-outline-success' : 'atom-btn-white' }}">AIP</a>
                <a href="{{ route('preservation.packages', ['type' => 'DIP']) }}" class="btn btn-sm {{ $type === 'DIP' ? 'atom-btn-outline-success' : 'atom-btn-white' }}">DIP</a>
            </div>
            <a href="{{ route('preservation.package-edit', 0) }}" class="btn btn-sm atom-btn-white">
                <i class="fas fa-plus me-1"></i>Create Package
            </a>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>UUID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Format</th>
                                <th>Objects</th>
                                <th>Size</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($packages as $pkg)
                            <tr>
                                <td>{{ $pkg->id }}</td>
                                <td><code class="small">{{ Str::limit($pkg->uuid, 13) }}</code></td>
                                <td>{{ Str::limit($pkg->name, 40) }}</td>
                                <td>
                                    @if(strtoupper($pkg->package_type) === 'SIP')
                                        <span class="badge bg-info">SIP</span>
                                    @elseif(strtoupper($pkg->package_type) === 'AIP')
                                        <span class="badge bg-success">AIP</span>
                                    @elseif(strtoupper($pkg->package_type) === 'DIP')
                                        <span class="badge bg-warning text-dark">DIP</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $pkg->package_type }}</span>
                                    @endif
                                </td>
                                <td>
                                    @switch($pkg->status)
                                        @case('draft')
                                            <span class="badge bg-secondary">Draft</span>
                                            @break
                                        @case('building')
                                            <span class="badge bg-info">Building</span>
                                            @break
                                        @case('built')
                                        @case('complete')
                                            <span class="badge bg-primary">{{ ucfirst($pkg->status) }}</span>
                                            @break
                                        @case('validated')
                                            <span class="badge bg-success">Validated</span>
                                            @break
                                        @case('exported')
                                            <span class="badge bg-dark">Exported</span>
                                            @break
                                        @case('failed')
                                        @case('error')
                                            <span class="badge bg-danger">{{ ucfirst($pkg->status) }}</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary">{{ ucfirst($pkg->status) }}</span>
                                    @endswitch
                                </td>
                                <td><small>{{ $pkg->package_format ?? '' }}</small></td>
                                <td><span class="badge bg-primary">{{ $pkg->object_count }}</span></td>
                                <td><small>{{ $pkg->total_size ? number_format($pkg->total_size / 1048576, 1) . ' MB' : '-' }}</small></td>
                                <td class="text-nowrap"><small>{{ $pkg->created_at }}</small></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('preservation.package-view', $pkg->id) }}" class="btn btn-sm atom-btn-white" title="View package">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if($pkg->status === 'draft')
                                        <a href="{{ route('preservation.package-edit', $pkg->id) }}" class="btn btn-sm atom-btn-white" title="Edit package">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endif
                                        @if($pkg->export_path ?? null)
                                        <a href="#" class="btn btn-sm atom-btn-white" title="Download export">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="10" class="text-center text-muted py-3">No packages found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
