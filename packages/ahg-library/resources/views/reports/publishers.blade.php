{{-- Publishers Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Publishers Report')
@section('body-class', 'library-reports publishers')
@section('sidebar')
<div class="sidebar-content">
  <h4>{{ __('Library Reports') }}</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('library.report-catalogue') }}"><i class="fas fa-book me-2"></i>Catalogue</a></li>
    <li><a href="{{ route('library.report-creators') }}"><i class="fas fa-user-edit me-2"></i>Creators</a></li>
    <li><a href="{{ route('library.report-subjects') }}"><i class="fas fa-tags me-2"></i>Subjects</a></li>
    <li><a href="{{ route('library.report-publishers') }}"><i class="fas fa-building me-2"></i>Publishers</a></li>
    <li><a href="{{ route('library.report-call-numbers') }}"><i class="fas fa-sort-alpha-down me-2"></i>Call Numbers</a></li>
  </ul>
  <hr>
  <a href="{{ route('library.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
</div>
@endsection
@section('title-block')<h1><i class="fas fa-building me-2"></i>Publishers</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($publishers) }}</strong> publishers found</div>
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark"><tr><th>{{ __('Publisher') }}</th><th>{{ __('Place') }}</th><th class="text-end">{{ __('Titles') }}</th></tr></thead>
    <tbody>
      @forelse($publishers as $p)
      <tr><td><strong>{{ e($p->publisher ?? '') }}</strong></td><td>{{ e($p->publication_place ?? '') }}</td><td class="text-end"><span class="badge bg-primary">{{ $p->title_count ?? 0 }}</span></td></tr>
      @empty
      <tr><td colspan="3" class="text-muted text-center py-3">No publishers.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
