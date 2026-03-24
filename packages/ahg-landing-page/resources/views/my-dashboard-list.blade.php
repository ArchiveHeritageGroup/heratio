{{-- User Dashboard List - migrated from ahgLandingPagePlugin/templates/myDashboardListSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'My Dashboards')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">My Dashboards</h1>
      <p class="text-muted mb-0">Manage your personal dashboards</p>
    </div>
    <a href="{{ route('landing-page.myDashboard.create') }}" class="btn btn-primary">
      <i class="bi bi-plus-lg"></i> Create Dashboard
    </a>
  </div>

  @if ($pages->isEmpty())
    <div class="text-center py-5">
      <i class="bi bi-grid-3x3-gap display-1 text-muted"></i>
      <h3 class="mt-3 text-muted">No Dashboards Yet</h3>
      <p class="text-muted">Create your first personal dashboard to get started</p>
      <a href="{{ route('landing-page.myDashboard.create') }}" class="btn btn-primary btn-lg mt-2">
        <i class="bi bi-plus-lg"></i> Create Dashboard
      </a>
    </div>
  @else
    <div class="row g-4">
      @foreach ($pages as $page)
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 {{ !$page->is_active ? 'border-warning' : '' }}">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="card-title mb-0">
                  {{ e($page->name) }}
                </h5>
                <div>
                  @if ($page->is_default ?? false)
                    <span class="badge bg-primary">Default</span>
                  @endif
                </div>
              </div>

              <p class="card-text text-muted small mb-3">
                @if ($page->description)
                  {{ e(\Illuminate\Support\Str::limit($page->description, 100)) }}
                @else
                  <em>No description</em>
                @endif
              </p>

              <div class="d-flex gap-2">
                <a href="{{ route('landing-page.myDashboard') }}"
                   class="btn btn-outline-secondary btn-sm flex-grow-1">
                  View
                </a>
                <a href="{{ route('landing-page.myDashboard') }}"
                   class="btn btn-primary btn-sm flex-grow-1">
                  Edit
                </a>
              </div>
            </div>
            <div class="card-footer bg-transparent text-muted small">
              Updated {{ $page->updated_at ? \Carbon\Carbon::parse($page->updated_at)->diffForHumans() : 'N/A' }}
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>
@endsection
