@extends('theme::layouts.1col')

@section('title', 'Preservation Reports')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
    <div class="col-md-3">
        @include('ahg-preservation::_menu')
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h1 class="mb-0"><i class="fas fa-chart-bar"></i> Preservation Reports</h1>
        </div>
        <p class="text-muted mb-3">Identify objects requiring preservation attention</p>

        {{-- Summary Stats --}}
        @php
            $checksumCoverage = 0;
            $totalObjects = \Illuminate\Support\Facades\DB::table('digital_object')->count();
            if ($totalObjects > 0) {
                $withChecksums = \Illuminate\Support\Facades\DB::table('preservation_checksum')->distinct('digital_object_id')->count('digital_object_id');
                $checksumCoverage = round(($withChecksums / $totalObjects) * 100, 1);
            }
        @endphp
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary">{{ $checksumCoverage }}%</h3>
                        <p class="mb-0">Checksum Coverage</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        @php $fixityFailures = \Illuminate\Support\Facades\DB::table('preservation_fixity_check')->where('status', 'fail')->count(); @endphp
                        <h3 class="{{ $fixityFailures > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($fixityFailures) }}</h3>
                        <p class="mb-0">Fixity Failures</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        @php $atRiskCount = \Illuminate\Support\Facades\DB::table('preservation_format')->whereIn('risk_level', ['high','critical'])->count(); @endphp
                        <h3 class="{{ $atRiskCount > 0 ? 'text-warning' : 'text-success' }}">{{ number_format($atRiskCount) }}</h3>
                        <p class="mb-0">At-Risk Formats</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Objects Without Checksums --}}
        <div class="card mb-4">
            <div class="card-header bg-danger bg-opacity-10">
                <i class="fas fa-fingerprint text-danger"></i> Objects Without Checksums
                <span class="badge bg-danger float-end">{{ count($noChecksums) }} found</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Path</th>
                                <th>MIME Type</th>
                                <th>Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($noChecksums as $obj)
                            <tr>
                                <td>{{ $obj->id }}</td>
                                <td>{{ Str::limit($obj->name ?? '', 50) }}</td>
                                <td><code class="small">{{ Str::limit($obj->path ?? '', 40) }}</code></td>
                                <td><small>{{ $obj->mime_type ?? '-' }}</small></td>
                                <td><small>{{ ($obj->byte_size ?? null) ? number_format($obj->byte_size / 1024, 1) . ' KB' : '-' }}</small></td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-success py-3"><i class="fas fa-check-circle"></i> All objects have checksums</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Stale Fixity Checks (>90 days) --}}
        <div class="card mb-4">
            <div class="card-header bg-warning bg-opacity-10">
                <i class="fas fa-clock text-warning"></i> Stale Fixity Checks (> 90 days)
                <span class="badge bg-warning text-dark float-end">{{ count($staleFixity) }} found</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Checksum ID</th>
                                <th>File</th>
                                <th>Algorithm</th>
                                <th>Checksum</th>
                                <th>Generated</th>
                                <th>Last Verified</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($staleFixity as $item)
                            <tr>
                                <td>{{ $item->id }}</td>
                                <td><small>{{ $item->file_name ?? 'Object #' . ($item->digital_object_id ?? '') }}</small></td>
                                <td><code>{{ $item->algorithm ?? '' }}</code></td>
                                <td><code class="small">{{ Str::limit($item->checksum_value ?? '', 16) }}</code></td>
                                <td class="text-nowrap"><small>{{ $item->generated_at ?? '' }}</small></td>
                                <td class="text-nowrap">
                                    @if($item->verified_at ?? null)
                                        <small class="text-warning">{{ $item->verified_at }}</small>
                                    @else
                                        <span class="badge bg-danger">Never verified</span>
                                    @endif
                                </td>
                                <td>
                                    @if(($item->verification_status ?? '') === 'verified')
                                        <span class="badge bg-success">Verified</span>
                                    @elseif(($item->verification_status ?? '') === 'failed')
                                        <span class="badge bg-danger">Failed</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($item->verification_status ?? 'pending') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-success py-3"><i class="fas fa-check-circle"></i> All fixity checks are current</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- High-Risk Formats --}}
        <div class="card mb-4">
            <div class="card-header bg-danger bg-opacity-10">
                <i class="fas fa-exclamation-triangle text-danger"></i> High-Risk Format Objects
                <span class="badge bg-danger float-end">{{ count($highRisk) }} found</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>File</th>
                                <th>Format</th>
                                <th>MIME Type</th>
                                <th>PUID</th>
                                <th>Risk Level</th>
                                <th>Preservation Action</th>
                                <th>Identified</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($highRisk as $item)
                            <tr>
                                <td>{{ $item->id }}</td>
                                <td><small>{{ $item->file_name ?? 'Object #' . ($item->digital_object_id ?? '') }}</small></td>
                                <td>{{ $item->registry_format_name ?? $item->format_name ?? '-' }}</td>
                                <td><small>{{ $item->mime_type ?? '-' }}</small></td>
                                <td><code>{{ $item->puid ?? '-' }}</code></td>
                                <td>
                                    @if(($item->risk_level ?? '') === 'critical')
                                        <span class="badge bg-danger">Critical</span>
                                    @else
                                        <span class="badge bg-warning text-dark">High</span>
                                    @endif
                                </td>
                                <td><small>{{ $item->preservation_action ?? '-' }}</small></td>
                                <td class="text-nowrap"><small>{{ $item->identification_date ?? '-' }}</small></td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-success py-3"><i class="fas fa-check-circle"></i> No high-risk format objects found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
