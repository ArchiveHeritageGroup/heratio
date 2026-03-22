@extends('theme::layouts.1col')
@section('title', 'Library Reports')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-chart-bar me-2"></i>Library Reports</h1><div class="card"><div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">Available Reports</h5></div><div class="list-group list-group-flush"><a href="{{ route("library.report-catalogue") }}" class="list-group-item list-group-item-action"><i class="fas fa-book me-2"></i>Catalogue</a><a href="{{ route("library.report-creators") }}" class="list-group-item list-group-item-action"><i class="fas fa-user-edit me-2"></i>Creators/Authors</a><a href="{{ route("library.report-publishers") }}" class="list-group-item list-group-item-action"><i class="fas fa-building me-2"></i>Publishers</a><a href="{{ route("library.report-subjects") }}" class="list-group-item list-group-item-action"><i class="fas fa-tags me-2"></i>Subjects</a><a href="{{ route("library.report-call-numbers") }}" class="list-group-item list-group-item-action"><i class="fas fa-sort-alpha-down me-2"></i>Call Numbers</a></div></div>
</div>
@endsection
