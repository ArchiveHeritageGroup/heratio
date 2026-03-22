@extends('theme::layouts.1col')

@section('title', 'Request to Publish')
@section('body-class', 'browse request-publish')

@section('content')
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- Table not configured --}}
  @if(!$tableExists)
    <div class="multiline-header d-flex align-items-center mb-3">
      <i class="fas fa-paper-plane fa-2x text-primary me-3" aria-hidden="true"></i>
      <div>
        <h1 class="h3 mb-0">Request to Publish</h1>
        <p class="text-muted mb-0">Manage image publication requests</p>
      </div>
    </div>
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle me-2"></i>
      The Request to Publish feature is not configured. The required database tables
      (<code>request_to_publish</code> and <code>request_to_publish_i18n</code>) do not exist.
      Please contact your system administrator.
    </div>
    @return
  @endif

  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-paper-plane fa-2x text-primary me-3" aria-hidden="true"></i>
    <div>
      <h1 class="h3 mb-0">Request to Publish</h1>
    </div>
  </div>

  {{-- Status Tabs --}}
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white p-0" style="background:var(--ahg-primary);color:#fff">
      <ul class="nav nav-tabs card-header-tabs" role="tablist">
        <li class="nav-item">
          <a class="nav-link {{ $status === 'all' ? 'active' : '' }}"
             href="{{ route('request-publish.browse', array_merge(request()->except('status', 'page'), ['status' => 'all'])) }}">
            <i class="fas fa-list me-1"></i>All Requests
            <span class="badge bg-secondary ms-1">{{ $allCount }}</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ $status === 'review' ? 'active' : '' }}"
             href="{{ route('request-publish.browse', array_merge(request()->except('status', 'page'), ['status' => 'review'])) }}">
            <i class="fas fa-clock me-1"></i>In Review
            <span class="badge bg-warning text-dark ms-1">{{ $pendingCount }}</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ $status === 'rejected' ? 'active' : '' }}"
             href="{{ route('request-publish.browse', array_merge(request()->except('status', 'page'), ['status' => 'rejected'])) }}">
            <i class="fas fa-times me-1"></i>Rejected
            <span class="badge bg-danger ms-1">{{ $rejectedCount }}</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ $status === 'approved' ? 'active' : '' }}"
             href="{{ route('request-publish.browse', array_merge(request()->except('status', 'page'), ['status' => 'approved'])) }}">
            <i class="fas fa-check me-1"></i>Approved
            <span class="badge bg-success ms-1">{{ $approvedCount }}</span>
          </a>
        </li>
      </ul>
    </div>

    {{-- Sort controls --}}
    <div class="card-body border-bottom py-2">
      <div class="d-flex flex-wrap gap-2 justify-content-end">
        <div class="btn-group btn-group-sm" role="group" aria-label="Sort options">
          <span class="btn atom-btn-white disabled">Sort by:</span>
          <a href="{{ route('request-publish.browse', array_merge(request()->except('sort', 'page'), ['sort' => 'nameUp'])) }}"
             class="btn atom-btn-white {{ $sort === 'nameUp' ? 'active' : '' }}">
            Name <i class="fas fa-arrow-up"></i>
          </a>
          <a href="{{ route('request-publish.browse', array_merge(request()->except('sort', 'page'), ['sort' => 'nameDown'])) }}"
             class="btn atom-btn-white {{ $sort === 'nameDown' ? 'active' : '' }}">
            Name <i class="fas fa-arrow-down"></i>
          </a>
          <a href="{{ route('request-publish.browse', array_merge(request()->except('sort', 'page'), ['sort' => 'instUp'])) }}"
             class="btn atom-btn-white {{ $sort === 'instUp' ? 'active' : '' }}">
            Institution <i class="fas fa-arrow-up"></i>
          </a>
          <a href="{{ route('request-publish.browse', array_merge(request()->except('sort', 'page'), ['sort' => 'instDown'])) }}"
             class="btn atom-btn-white {{ $sort === 'instDown' ? 'active' : '' }}">
            Institution <i class="fas fa-arrow-down"></i>
          </a>
        </div>
      </div>
    </div>

    <div class="card-body p-0">
      @if($pager->getNbResults())
        <div class="table-responsive">
          <table class="table table-bordered table-hover table-striped mb-0">
            <thead>
              <tr>
                <th style="width: 100px;">Status</th>
                <th>Archival Item</th>
                <th>Requester</th>
                <th>Institution</th>
                <th>Planned Use</th>
                <th>Need By</th>
                <th>Submitted</th>
                <th style="width: 80px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($pager->getResults() as $i => $row)
                @php
                  $statusId = (int) ($row['status_id'] ?? 0);
                  $statusLabel = \AhgRequestPublish\Controllers\RequestPublishController::getStatusLabel($statusId);
                  $statusBadge = \AhgRequestPublish\Controllers\RequestPublishController::getStatusBadgeClass($statusId);
                @endphp
                <tr>
                  <td>
                    <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                  </td>
                  <td>
                    @if(!empty($row['object_title']))
                      <a href="/{{ $row['object_slug'] ?? '' }}">
                        <i class="fas fa-file-alt me-1 text-muted"></i>{{ $row['object_title'] }}
                      </a>
                      @if(!empty($row['object_identifier']))
                        <br><small class="text-muted">{{ $row['object_identifier'] }}</small>
                      @endif
                    @elseif(!empty($row['object_id']))
                      <span class="text-muted">Object #{{ $row['object_id'] }}</span>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td>
                    <strong>{{ ($row['rtp_name'] ?? '') . ' ' . ($row['rtp_surname'] ?? '') }}</strong>
                  </td>
                  <td>
                    @if(!empty($row['rtp_institution']))
                      {{ $row['rtp_institution'] }}
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td>
                    {{ $row['rtp_planned_use'] ?? '' }}
                  </td>
                  <td>
                    @if(!empty($row['rtp_need_image_by']))
                      <span class="badge bg-info text-dark">
                        <i class="fas fa-calendar me-1"></i>{{ \Carbon\Carbon::parse($row['rtp_need_image_by'])->format('d M Y') }}
                      </span>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td>
                    @if(!empty($row['created_at']))
                      <small>{{ \Carbon\Carbon::parse($row['created_at'])->format('d M Y') }}</small>
                    @endif
                    @if(!empty($row['completed_at']))
                      <br><small class="text-success">
                        <i class="fas fa-check me-1"></i>{{ \Carbon\Carbon::parse($row['completed_at'])->format('d M Y') }}
                      </small>
                    @endif
                  </td>
                  <td class="text-center">
                    <a href="{{ route('request-publish.edit', $row['id']) }}"
                       class="btn btn-sm atom-btn-white" title="Review">
                      <i class="fas fa-eye"></i>
                    </a>
                  </td>
                </tr>

                {{-- Expandable details accordion --}}
                <tr>
                  <td colspan="8" class="p-0 border-0">
                    <div class="accordion accordion-flush" id="details-{{ $row['id'] }}">
                      <div class="accordion-item border-0">
                        <h2 class="accordion-header" id="heading-{{ $row['id'] }}">
                          <button class="accordion-button collapsed py-1 px-3 small text-muted"
                                  type="button" data-bs-toggle="collapse"
                                  data-bs-target="#collapse-{{ $row['id'] }}"
                                  aria-expanded="false" aria-controls="collapse-{{ $row['id'] }}">
                            <i class="fas fa-chevron-down me-1"></i> Details
                          </button>
                        </h2>
                        <div id="collapse-{{ $row['id'] }}" class="accordion-collapse collapse"
                             aria-labelledby="heading-{{ $row['id'] }}"
                             data-bs-parent="#details-{{ $row['id'] }}">
                          <div class="accordion-body bg-light py-2 px-3">
                            <div class="row">
                              <div class="col-md-4">
                                <label class="form-label text-muted small fw-semibold mb-0">Motivation <span class="badge bg-secondary ms-1">Optional</span></label>
                                <p class="small mb-2">{{ $row['rtp_motivation'] ?: '-' }}</p>
                              </div>
                              <div class="col-md-4">
                                <label class="form-label text-muted small fw-semibold mb-0">Planned Use <span class="badge bg-secondary ms-1">Optional</span></label>
                                <p class="small mb-2">{{ $row['rtp_planned_use'] ?: '-' }}</p>
                              </div>
                              <div class="col-md-4">
                                <label class="form-label text-muted small fw-semibold mb-0">Admin Notes <span class="badge bg-secondary ms-1">Optional</span></label>
                                <p class="small mb-2">{{ $row['rtp_admin_notes'] ?: '-' }}</p>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @include('ahg-core::components.pager', ['pager' => $pager])
      @else
        <div class="text-center py-5">
          <i class="fas fa-paper-plane fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">No requests found</h5>
          <p class="text-muted mb-0">There are no publication requests matching your filter.</p>
        </div>
      @endif
    </div>
  </div>
@endsection
