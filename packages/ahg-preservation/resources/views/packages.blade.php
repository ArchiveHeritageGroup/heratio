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

        {{-- Type Filter --}}
        <div class="mb-3">
            <div class="btn-group" role="group">
                <a href="{{ route('preservation.packages') }}" class="btn btn-sm {{ !$type ? 'atom-btn-white' : 'atom-btn-white' }}">All</a>
                <a href="{{ route('preservation.packages', ['type' => 'SIP']) }}" class="btn btn-sm {{ $type === 'SIP' ? 'atom-btn-white' : 'atom-btn-white' }}">SIP</a>
                <a href="{{ route('preservation.packages', ['type' => 'AIP']) }}" class="btn btn-sm {{ $type === 'AIP' ? 'atom-btn-outline-success' : 'atom-btn-outline-success' }}">AIP</a>
                <a href="{{ route('preservation.packages', ['type' => 'DIP']) }}" class="btn btn-sm {{ $type === 'DIP' ? 'atom-btn-white' : 'atom-btn-white' }}">DIP</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped mb-0">
                        <thead>
                            <tr style="background:var(--ahg-primary);color:#fff">
                                <th>ID</th>
                                <th>UUID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Format</th>
                                <th>Objects</th>
                                <th>Size</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($packages as $pkg)
                            <tr>
                                <td>{{ $pkg->id }}</td>
                                <td><code class="small">{{ Str::limit($pkg->uuid, 13) }}</code></td>
                                <td>{{ Str::limit($pkg->name, 40) }}</td>
                                <td>
                                    @if($pkg->package_type === 'SIP')
                                        <span class="badge bg-info">SIP</span>
                                    @elseif($pkg->package_type === 'AIP')
                                        <span class="badge bg-success">AIP</span>
                                    @elseif($pkg->package_type === 'DIP')
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
                                            <span class="badge bg-primary">Built</span>
                                            @break
                                        @case('validated')
                                            <span class="badge bg-success">Validated</span>
                                            @break
                                        @case('exported')
                                            <span class="badge bg-dark">Exported</span>
                                            @break
                                        @case('failed')
                                            <span class="badge bg-danger">Failed</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary">{{ ucfirst($pkg->status) }}</span>
                                    @endswitch
                                </td>
                                <td><small>{{ $pkg->package_format }}</small></td>
                                <td><span class="badge bg-primary">{{ $pkg->object_count }}</span></td>
                                <td><small>{{ $pkg->total_size ? number_format($pkg->total_size / 1048576, 1) . ' MB' : '-' }}</small></td>
                                <td class="text-nowrap"><small>{{ $pkg->created_at }}</small></td>
                                <td>
                                    <a href="{{ route('preservation.package-view', $pkg->id) }}" class="btn btn-sm atom-btn-white" title="View package">
                                        <i class="fas fa-eye"></i>
                                    </a>
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
