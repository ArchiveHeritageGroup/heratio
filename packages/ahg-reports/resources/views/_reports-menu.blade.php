<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="reportsMenuDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-chart-bar"></i>
        <span class="d-none d-lg-inline ms-1">Reports</span>
    </a>
    <ul class="dropdown-menu dropdown-menu-end mega-menu" aria-labelledby="reportsMenuDropdown">
        <li class="mega-menu-content">
            <div class="row">
                <div class="col-md-4">
                    <h6 class="dropdown-header"><i class="fas fa-file-alt me-2"></i>Reports</h6>
                    <a class="dropdown-item" href="{{ route('reports.descriptions') }}"><i class="fas fa-archive me-2"></i>Archival Descriptions</a>
                    <a class="dropdown-item" href="{{ route('reports.authorities') }}"><i class="fas fa-users me-2"></i>Authority Records</a>
                    <a class="dropdown-item" href="{{ route('reports.repositories') }}"><i class="fas fa-building me-2"></i>Repositories</a>
                    <a class="dropdown-item" href="{{ route('reports.accessions') }}"><i class="fas fa-inbox me-2"></i>Accessions</a>
                    <a class="dropdown-item" href="{{ route('reports.spatial') }}"><i class="fas fa-map-marker-alt me-2"></i>Spatial Analysis</a>
                    <div class="dropdown-divider"></div>
                    <h6 class="dropdown-header"><i class="fas fa-clipboard-check me-2"></i>Audit</h6>
                    <a class="dropdown-item" href="{{ route('reports.audit.actor') }}"><i class="fas fa-user me-2"></i>Audit Actors</a>
                    <a class="dropdown-item" href="{{ route('reports.audit.description') }}"><i class="fas fa-file-alt me-2"></i>Audit Descriptions</a>
                </div>
                <div class="col-md-4">
                    <h6 class="dropdown-header"><i class="fas fa-tachometer-alt me-2"></i>Dashboards</h6>
                    <a class="dropdown-item" href="{{ route('reports.dashboard') }}"><i class="fas fa-tachometer-alt me-2"></i>Reports Dashboard</a>
                    @if(Route::has('reports.builder.index'))
                    <a class="dropdown-item" href="{{ route('reports.builder.index') }}"><i class="fas fa-wrench me-2"></i>Report Builder</a>
                    @endif
                </div>
                <div class="col-md-4">
                    <h6 class="dropdown-header"><i class="fas fa-download me-2"></i>Export</h6>
                    <a class="dropdown-item" href="{{ route('reports.select') }}"><i class="fas fa-file-export me-2"></i>Select Report</a>
                </div>
            </div>
        </li>
    </ul>
</li>