{{-- Researcher Submissions Dashboard --}}
{{-- Cloned from AtoM: ahgResearcherPlugin/modules/researcher/templates/dashboardSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Researcher Workspace')
@section('body-class', 'researcher dashboard')

@section('content')
<div class="container-fluid py-3">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-1"><i class="bi bi-cloud-upload me-2"></i>Researcher Workspace</h4>
      <p class="text-muted mb-0">Upload collections, describe records, and submit for archivist review</p>
    </div>
    <div>
      <a href="{{ route('researcher.import') }}" class="btn btn-outline-primary me-2">
        <i class="bi bi-file-earmark-arrow-up me-1"></i>{{ __('Import Exchange') }}
      </a>
      <a href="{{ route('researcher.submissions') }}" class="btn btn-success">
        <i class="bi bi-plus-lg me-1"></i>{{ __('New Submission') }}
      </a>
    </div>
  </div>

  {{-- Flash messages --}}
  @if(session('notice') || session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('notice') ?: session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  {{-- Stats Cards --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3 col-xl-2">
      <div class="card text-center border-primary h-100">
        <div class="card-body py-3">
          <h3 class="mb-0 text-primary">{{ $stats['total'] }}</h3>
          <small class="text-muted">{{ __('Total') }}</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="card text-center border-secondary h-100">
        <div class="card-body py-3">
          <h3 class="mb-0 text-secondary">{{ $stats['draft'] }}</h3>
          <small class="text-muted">{{ __('Draft') }}</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="card text-center border-warning h-100">
        <div class="card-body py-3">
          <h3 class="mb-0 text-warning">{{ $stats['pending'] }}</h3>
          <small class="text-muted">{{ __('Pending Review') }}</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="card text-center border-success h-100">
        <div class="card-body py-3">
          <h3 class="mb-0 text-success">{{ $stats['approved'] }}</h3>
          <small class="text-muted">{{ __('Approved') }}</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="card text-center border-info h-100">
        <div class="card-body py-3">
          <h3 class="mb-0 text-info">{{ $stats['published'] }}</h3>
          <small class="text-muted">{{ __('Published') }}</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="card text-center border-danger h-100">
        <div class="card-body py-3">
          <h3 class="mb-0 text-danger">{{ $stats['returned'] + $stats['rejected'] }}</h3>
          <small class="text-muted">{{ __('Returned / Rejected') }}</small>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    {{-- Recent Submissions (main column) --}}
    <div class="{{ $hasResearch ? 'col-lg-8' : 'col-12' }}">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Submissions</h6>
          <a href="{{ route('researcher.submissions') }}" class="btn btn-sm btn-outline-primary">
            View All
          </a>
        </div>
        <div class="card-body p-0">
          @if(empty($recent))
            <div class="text-center text-muted py-5">
              <i class="bi bi-inbox" style="font-size: 2rem;"></i>
              <p class="mt-2 mb-0">No submissions yet. Create your first submission to get started.</p>
            </div>
          @else
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('Source') }}</th>
                    <th>{{ __('Items') }}</th>
                    <th>{{ __('Files') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Updated') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($recent as $sub)
                  <tr class="cursor-pointer" onclick="window.location='{{ route('researcher.submission.view', ['id' => $sub->id]) }}'">
                    <td>
                      <strong>{{ e($sub->title) }}</strong>
                      @if($isAdmin && !empty($sub->user_name))
                        <br><small class="text-muted">{{ e($sub->user_name) }}</small>
                      @endif
                    </td>
                    <td>
                      @if($sub->source_type === 'offline')
                        <span class="badge bg-secondary"><i class="bi bi-hdd me-1"></i>{{ __('Offline') }}</span>
                      @else
                        <span class="badge bg-primary"><i class="bi bi-cloud me-1"></i>{{ __('Online') }}</span>
                      @endif
                    </td>
                    <td>{{ $sub->total_items }}</td>
                    <td>{{ $sub->total_files }}</td>
                    <td>
                      @php
                        $statusColors = [
                          'draft' => 'secondary', 'submitted' => 'warning', 'under_review' => 'info',
                          'approved' => 'success', 'published' => 'primary', 'returned' => 'danger', 'rejected' => 'dark',
                        ];
                        $color = $statusColors[$sub->status] ?? 'secondary';
                      @endphp
                      <span class="badge bg-{{ $color }}">{{ ucfirst(str_replace('_', ' ', $sub->status)) }}</span>
                    </td>
                    <td><small class="text-muted">{{ date('d M Y', strtotime($sub->updated_at)) }}</small></td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>

    @if($hasResearch)
    {{-- Research Sidebar --}}
    <div class="col-lg-4">

      {{-- Researcher Profile --}}
      @if($researcherProfile)
      <div class="card mb-3">
        <div class="card-header bg-info text-white">
          <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Researcher Profile</h6>
        </div>
        <div class="card-body py-2">
          <strong>{{ e($researcherProfile->first_name . ' ' . $researcherProfile->last_name) }}</strong>
          @if(!empty($researcherProfile->institution))
            <br><small class="text-muted">{{ e($researcherProfile->institution) }}</small>
          @endif
          <span class="badge bg-{{ $researcherProfile->status === 'approved' ? 'success' : 'warning' }} ms-1">{{ ucfirst($researcherProfile->status) }}</span>
          <div class="mt-2">
            <a href="{{ route('research.dashboard') }}" class="btn btn-sm btn-outline-info w-100">
              <i class="bi bi-folder-open me-1"></i>{{ __('My Research Workspace') }}
            </a>
          </div>
        </div>
      </div>
      @endif

      {{-- Research Projects --}}
      @if(!empty($projects))
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>My Projects</h6>
          <span class="badge bg-secondary">{{ count($projects) }}</span>
        </div>
        <div class="list-group list-group-flush">
          @foreach($projects as $proj)
          <div class="list-group-item py-2">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <strong class="small">{{ e($proj->title) }}</strong>
                @php
                  $projStatusColors = ['active' => 'success', 'planning' => 'info', 'on_hold' => 'warning', 'completed' => 'secondary'];
                  $pc = $projStatusColors[$proj->status] ?? 'secondary';
                @endphp
                <br><span class="badge bg-{{ $pc }}" style="font-size:0.65rem;">{{ ucfirst($proj->status) }}</span>
                @if(!empty($proj->project_type))
                  <span class="badge bg-light text-dark" style="font-size:0.65rem;">{{ ucfirst($proj->project_type) }}</span>
                @endif
              </div>
            </div>
          </div>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Research Collections --}}
      @if(!empty($collections))
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="bi bi-collection me-2"></i>My Collections</h6>
          <span class="badge bg-secondary">{{ count($collections) }}</span>
        </div>
        <div class="list-group list-group-flush">
          @foreach($collections as $col)
          <div class="list-group-item py-2">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <strong class="small">{{ e($col->name) }}</strong>
                <br><small class="text-muted">{{ $col->item_count }} item{{ $col->item_count !== 1 ? 's' : '' }}</small>
              </div>
              @if($col->item_count > 0)
              <a href="{{ route('researcher.submissions') }}"
                 class="btn btn-sm btn-outline-success" title="{{ __('Create submission from this collection') }}">
                <i class="bi bi-box-arrow-in-right"></i>
              </a>
              @endif
            </div>
          </div>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Recent Annotations --}}
      @if(!empty($annotations))
      <div class="card mb-3">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-sticky me-2"></i>Recent Notes</h6>
        </div>
        <div class="list-group list-group-flush">
          @foreach($annotations as $ann)
          <div class="list-group-item py-2">
            <small>
              <strong>{{ e($ann->title ?? $ann->annotation_type) }}</strong>
              @if(!empty($ann->object_title))
                <br><span class="text-muted">on: {{ e($ann->object_title) }}</span>
              @endif
              <br><span class="text-muted">{{ date('d M Y', strtotime($ann->created_at)) }}</span>
            </small>
          </div>
          @endforeach
        </div>
      </div>
      @endif

      {{-- No research profile --}}
      @if(!$researcherProfile)
      <div class="card mb-3">
        <div class="card-body text-center text-muted">
          <i class="bi bi-person-plus" style="font-size: 1.5rem;"></i>
          <p class="small mt-2 mb-2">Register as a researcher to link your research workspace.</p>
          <a href="{{ route('research.publicRegister') }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-person-plus me-1"></i>{{ __('Register') }}
          </a>
        </div>
      </div>
      @endif

    </div>
    @endif

  </div>

</div>
@endsection
