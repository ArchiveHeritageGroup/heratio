{{-- Landing Page List - Admin View - migrated from ahgLandingPagePlugin/templates/listSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Landing Pages')

@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">{{ __('Landing Pages') }}</h1>
      <p class="text-muted mb-0">Manage your site's landing pages</p>
    </div>
    <a href="{{ route('landing-page.create') }}" class="btn btn-primary">
      <i class="bi bi-plus-lg"></i> {{ __('Create New Page') }}
    </a>
  </div>

  @if ($pages->isEmpty())
    <div class="text-center py-5">
      <i class="bi bi-file-earmark-plus display-1 text-muted"></i>
      <h3 class="mt-3 text-muted">{{ __('No Landing Pages Yet') }}</h3>
      <p class="text-muted">Create your first landing page to get started</p>
      <a href="{{ route('landing-page.create') }}" class="btn btn-primary btn-lg mt-2">
        <i class="bi bi-plus-lg"></i> {{ __('Create Landing Page') }}
      </a>
    </div>
  @else
    <div class="row g-4">
      @foreach ($pages as $page)
        <div class="col-md-6 col-lg-4">
          <div class="card page-list-card h-100 {{ !$page->is_active ? 'border-warning' : '' }}">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="card-title mb-0">
                  {{ e($page->name) }}
                </h5>
                <div>
                  @if ($page->is_default)
                    <span class="badge bg-primary">{{ __('Default') }}</span>
                  @endif
                  @if (!$page->is_active)
                    <span class="badge bg-warning text-dark">{{ __('Inactive') }}</span>
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

              <div class="d-flex justify-content-between align-items-center text-muted small mb-3">
                <span>
                  <i class="bi bi-grid-3x3-gap"></i>
                  {{ $page->block_count ?? 0 }} blocks
                </span>
                <span>
                  <code>/{{ e($page->slug) }}</code>
                </span>
              </div>

              <div class="d-flex gap-2">
                <a href="{{ route('landing-page.edit', $page->id) }}"
                   class="btn btn-primary btn-sm flex-grow-1">
                  Edit
                </a>
                <a href="{{ route('landing-page.show', $page->slug) }}"
                   class="btn btn-outline-secondary btn-sm" target="_blank" title="{{ __('Preview') }}">
                  Preview
                </a>
                @if ($page->is_active)
                  <a href="{{ route('landing-page.show', $page->slug) }}"
                     class="btn btn-outline-secondary btn-sm" target="_blank" title="{{ __('View Live') }}">
                    View
                  </a>
                @endif
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
