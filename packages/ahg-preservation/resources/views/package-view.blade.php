@extends('theme::layouts.1col')

@section('title', 'Package: ' . ($package->name ?? '') . ' - Preservation')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
    <div class="col-md-3">
        @include('ahg-preservation::_menu')
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h1 class="mb-0"><i class="fas fa-box-open"></i> {{ $package->name }}</h1>
            <div>
                @if($package->status === 'draft')
                <a href="{{ route('preservation.package-edit', $package->id) }}" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-edit me-1"></i>Edit
                </a>
                @endif
                <a href="{{ route('preservation.packages') }}" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-arrow-left me-1"></i>Back to Packages
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                {{-- Package Metadata --}}
                <div class="card mb-4">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-info-circle"></i> Package Metadata</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-bordered table-sm table-borderless">
                                    <tr><th class="text-muted" style="width:40%">UUID</th><td><code>{{ $package->uuid }}</code></td></tr>
                                    <tr><th class="text-muted">Type</th><td>
                                        @if(strtoupper($package->package_type) === 'SIP')
                                            <span class="badge bg-info">SIP</span> Submission Information Package
                                        @elseif(strtoupper($package->package_type) === 'AIP')
                                            <span class="badge bg-success">AIP</span> Archival Information Package
                                        @elseif(strtoupper($package->package_type) === 'DIP')
                                            <span class="badge bg-warning text-dark">DIP</span> Dissemination Information Package
                                        @else
                                            <span class="badge bg-secondary">{{ $package->package_type }}</span>
                                        @endif
                                    </td></tr>
                                    <tr><th class="text-muted">Status</th><td>
                                        @php
                                            $statusClass = [
                                                'draft' => 'secondary', 'building' => 'warning', 'built' => 'info',
                                                'complete' => 'info', 'validated' => 'success', 'exported' => 'dark',
                                                'error' => 'danger', 'failed' => 'danger'
                                            ][$package->status] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-{{ $statusClass }} fs-6">{{ ucfirst($package->status) }}</span>
                                    </td></tr>
                                    <tr><th class="text-muted">Format</th><td>{{ $package->package_format }} {{ $package->bagit_version ? '(v' . $package->bagit_version . ')' : '' }}</td></tr>
                                    <tr><th class="text-muted">Manifest Algorithm</th><td><code>{{ $package->manifest_algorithm ?? '-' }}</code></td></tr>
                                    <tr><th class="text-muted">Package Checksum</th><td><code class="small">{{ $package->package_checksum ?? '-' }}</code></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-bordered table-sm table-borderless">
                                    <tr><th class="text-muted" style="width:40%">Objects</th><td><span class="badge bg-primary">{{ $package->object_count }}</span></td></tr>
                                    <tr><th class="text-muted">Total Size</th><td>{{ $package->total_size ? number_format($package->total_size / 1048576, 2) . ' MB' : '-' }}</td></tr>
                                    <tr><th class="text-muted">Originator</th><td>{{ $package->originator ?? '-' }}</td></tr>
                                    <tr><th class="text-muted">Created By</th><td>{{ $package->created_by ?? '-' }}</td></tr>
                                    <tr><th class="text-muted">Created At</th><td>{{ $package->created_at }}</td></tr>
                                    <tr><th class="text-muted">Retention Period</th><td>{{ $package->retention_period ?? '-' }}</td></tr>
                                </table>
                            </div>
                        </div>
                        @if($package->description ?? null)
                            <div class="mt-2"><strong>Description:</strong> {{ $package->description }}</div>
                        @endif
                        @if($package->source_path ?? null)
                            <div class="mt-1"><strong>Source Path:</strong> <code>{{ $package->source_path }}</code></div>
                        @endif
                        @if($package->export_path ?? null)
                            <div class="mt-1"><strong>Export Path:</strong> <code>{{ $package->export_path }}</code></div>
                        @endif
                        @if($package->submission_agreement ?? null)
                            <div class="mt-1"><strong>Submission Agreement:</strong> {{ $package->submission_agreement }}</div>
                        @endif
                    </div>
                </div>

                {{-- Package Objects --}}
                <div class="card mb-4">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-file-archive"></i> Package Objects ({{ count($package->objects ?? []) }})</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>File Name</th>
                                        <th>Relative Path</th>
                                        <th>MIME Type</th>
                                        <th>PUID</th>
                                        <th>Size</th>
                                        <th>Checksum</th>
                                        <th>Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($package->objects ?? [] as $obj)
                                    <tr>
                                        <td>{{ $obj->sequence ?? '' }}</td>
                                        <td>{{ $obj->file_name ?? '' }}</td>
                                        <td><small><code>{{ Str::limit($obj->relative_path ?? '', 50) }}</code></small></td>
                                        <td><small>{{ $obj->mime_type ?? '-' }}</small></td>
                                        <td><code>{{ $obj->puid ?? '-' }}</code></td>
                                        <td><small>{{ ($obj->file_size ?? null) ? number_format($obj->file_size / 1024, 1) . ' KB' : '-' }}</small></td>
                                        <td><code class="small">{{ ($obj->checksum_value ?? null) ? Str::limit($obj->checksum_value, 16) : '-' }}</code></td>
                                        <td><span class="badge bg-secondary">{{ $obj->object_role ?? 'payload' }}</span></td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="8" class="text-center text-muted py-3">No objects in this package</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Package Events --}}
                <div class="card mb-4">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-history"></i> Package Events ({{ count($package->events ?? []) }})</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Type</th>
                                        <th>Outcome</th>
                                        <th>Detail</th>
                                        <th>Agent</th>
                                        <th>Created By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($package->events ?? [] as $event)
                                    <tr>
                                        <td class="text-nowrap"><small>{{ $event->event_datetime ?? '' }}</small></td>
                                        <td><span class="badge bg-secondary">{{ $event->event_type ?? '' }}</span></td>
                                        <td>
                                            @if(($event->event_outcome ?? '') === 'success')
                                                <span class="badge bg-success">Success</span>
                                            @elseif(($event->event_outcome ?? '') === 'failure')
                                                <span class="badge bg-danger">Failure</span>
                                            @else
                                                <span class="badge bg-info">{{ ucfirst($event->event_outcome ?? 'unknown') }}</span>
                                            @endif
                                        </td>
                                        <td><small>{{ Str::limit($event->event_detail ?? '', 80) }}</small></td>
                                        <td><small>{{ $event->agent_value ?? $event->agent_type ?? '-' }}</small></td>
                                        <td><small>{{ $event->created_by ?? '-' }}</small></td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">No package events recorded</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
                {{-- Timeline --}}
                <div class="card mb-4">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <i class="fas fa-calendar-alt me-1"></i> Timeline
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Created</span>
                            <span>{{ $package->created_at }}</span>
                        </li>
                        @if($package->created_by ?? null)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Created By</span>
                            <span>{{ $package->created_by }}</span>
                        </li>
                        @endif
                        @if($package->built_at ?? null)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Built</span>
                            <span class="text-success">{{ $package->built_at }}</span>
                        </li>
                        @endif
                        @if($package->validated_at ?? null)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Validated</span>
                            <span class="text-primary">{{ $package->validated_at }}</span>
                        </li>
                        @endif
                        @if($package->exported_at ?? null)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Exported</span>
                            <span class="text-success">{{ $package->exported_at }}</span>
                        </li>
                        @endif
                    </ul>
                </div>

                {{-- File Paths --}}
                @if(($package->source_path ?? null) || ($package->export_path ?? null))
                <div class="card mb-4">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <i class="fas fa-folder me-1"></i> File Paths
                    </div>
                    <div class="card-body">
                        @if($package->source_path ?? null)
                        <dt>Source Path</dt>
                        <dd><code class="small">{{ $package->source_path }}</code></dd>
                        @endif
                        @if($package->export_path ?? null)
                        <dt>Export Path</dt>
                        <dd><code class="small">{{ $package->export_path }}</code></dd>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Related Packages --}}
                @php
                    $parentPackage = $parentPackage ?? null;
                    $childPackages = $childPackages ?? [];
                @endphp
                @if($parentPackage || !empty($childPackages))
                <div class="card mb-4">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <i class="fas fa-sitemap me-1"></i> Related Packages
                    </div>
                    <ul class="list-group list-group-flush">
                        @if($parentPackage)
                        <li class="list-group-item">
                            <small class="text-muted d-block">Parent Package</small>
                            <a href="{{ route('preservation.package-view', $parentPackage->id) }}">
                                <i class="fas fa-arrow-up me-1"></i>{{ $parentPackage->name }}
                            </a>
                            @php $pTypeColor = ['sip' => 'info', 'aip' => 'success', 'dip' => 'warning'][strtolower($parentPackage->package_type)] ?? 'secondary'; @endphp
                            <span class="badge bg-{{ $pTypeColor }} ms-2">{{ strtoupper($parentPackage->package_type) }}</span>
                        </li>
                        @endif
                        @foreach($childPackages as $child)
                        <li class="list-group-item">
                            <small class="text-muted d-block">Derived Package</small>
                            <a href="{{ route('preservation.package-view', $child->id) }}">
                                <i class="fas fa-arrow-down me-1"></i>{{ $child->name }}
                            </a>
                            @php $cTypeColor = ['sip' => 'info', 'aip' => 'success', 'dip' => 'warning'][strtolower($child->package_type)] ?? 'secondary'; @endphp
                            <span class="badge bg-{{ $cTypeColor }} ms-2">{{ strtoupper($child->package_type) }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{-- BagIt Structure --}}
                @if(($package->source_path ?? null) && is_dir($package->source_path ?? ''))
                <div class="card">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <i class="fas fa-file-code me-1"></i> BagIt Structure
                    </div>
                    <div class="card-body">
                        <pre class="mb-0 small bg-light p-2 rounded">{{ $package->uuid }}/
  bagit.txt
  bag-info.txt
  manifest-{{ $package->manifest_algorithm ?? 'sha256' }}.txt
  tagmanifest-{{ $package->manifest_algorithm ?? 'sha256' }}.txt
  data/
@php $pkgObjects = $package->objects ?? []; @endphp
@foreach(array_slice(is_array($pkgObjects) ? $pkgObjects : $pkgObjects->toArray(), 0, 3) as $obj)
    {{ basename($obj->relative_path ?? $obj->file_name ?? '') }}
@endforeach
@if(count($pkgObjects) > 3)
    ... ({{ count($pkgObjects) - 3 }} more files)
@endif
</pre>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
