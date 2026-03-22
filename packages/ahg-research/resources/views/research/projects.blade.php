{{-- Research Projects - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('content')
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-project-diagram me-2"></i>My Projects</h1>
    <button type="button" class="btn atom-btn-outline-success" data-bs-toggle="modal" data-bs-target="#createProjectModal">
      <i class="fas fa-plus me-1"></i>New Project
    </button>
  </div>

  {{-- Status Filter --}}
  <div class="btn-group mb-3" role="group">
    <a href="{{ route('research.projects', ['status' => 'all']) }}" class="btn atom-btn-white {{ ($status ?? 'all') === 'all' ? 'active' : '' }}">All</a>
    <a href="{{ route('research.projects', ['status' => 'active']) }}" class="btn atom-btn-outline-success {{ ($status ?? '') === 'active' ? 'active' : '' }}">Active</a>
    <a href="{{ route('research.projects', ['status' => 'completed']) }}" class="btn atom-btn-white {{ ($status ?? '') === 'completed' ? 'active' : '' }}">Completed</a>
    <a href="{{ route('research.projects', ['status' => 'archived']) }}" class="btn atom-btn-white {{ ($status ?? '') === 'archived' ? 'active' : '' }}">Archived</a>
  </div>

  {{-- Project Grid --}}
  <div class="row">
    @forelse($projects ?? [] as $project)
      <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h5 class="card-title mb-0">
                <a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title) }}</a>
              </h5>
              <span class="badge bg-{{ ($project->status ?? '') === 'active' ? 'success' : (($project->status ?? '') === 'completed' ? 'primary' : 'secondary') }}">
                {{ ucfirst(e($project->status ?? 'active')) }}
              </span>
            </div>
            @if($project->description ?? false)
              <p class="card-text small text-muted">{{ e(\Illuminate\Support\Str::limit($project->description, 100)) }}</p>
            @endif
            <div class="small text-muted">
              @if($project->project_type ?? false)
                <span class="me-2"><i class="fas fa-tag me-1"></i>{{ e($project->project_type) }}</span>
              @endif
              @if($project->institution ?? false)
                <span><i class="fas fa-university me-1"></i>{{ e($project->institution) }}</span>
              @endif
            </div>
            @if(($project->start_date ?? false) || ($project->end_date ?? false))
              <div class="small text-muted mt-1">
                <i class="fas fa-calendar me-1"></i>
                {{ e($project->start_date ?? '?') }} - {{ e($project->end_date ?? 'ongoing') }}
              </div>
            @endif
          </div>
        </div>
      </div>
    @empty
      <div class="col-12">
        <div class="text-center py-5 text-muted">
          <i class="fas fa-project-diagram fa-3x mb-3"></i>
          <p>No projects yet. Create a project to organise your research.</p>
        </div>
      </div>
    @endforelse
  </div>

  @if(is_object($projects) && method_exists($projects, 'links'))
    <div class="d-flex justify-content-center">{{ $projects->links() }}</div>
  @endif

  {{-- Create Project Modal --}}
  <div class="modal fade" id="createProjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form action="{{ route('research.projects.store') }}" method="POST">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-project-diagram me-2"></i>New Project</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="proj_title" class="form-label">Title <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" name="title" id="proj_title" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="proj_description" class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="description" id="proj_description" class="form-control" rows="3"></textarea>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="proj_type" class="form-label">Project Type <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="project_type" id="proj_type" class="form-control" placeholder="e.g. thesis, dissertation, article">
              </div>
              <div class="col-md-6 mb-3">
                <label for="proj_institution" class="form-label">Institution <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="institution" id="proj_institution" class="form-control">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="proj_start" class="form-label">Start Date <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="date" name="start_date" id="proj_start" class="form-control">
              </div>
              <div class="col-md-6 mb-3">
                <label for="proj_end" class="form-label">End Date <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="date" name="end_date" id="proj_end" class="form-control">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-plus me-1"></i>Create</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
