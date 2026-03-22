@extends('theme::layouts.1col')
@section('title', 'Library Sidebar')
@section('content')
<div class="container py-4">
<div class="list-group"><a href="{{ route("library.browse") }}" class="list-group-item list-group-item-action"><i class="fas fa-book me-2"></i>Browse</a><a href="{{ route("library.create") }}" class="list-group-item list-group-item-action"><i class="fas fa-plus me-2"></i>Add New</a><a href="{{ route("library.isbn-providers") }}" class="list-group-item list-group-item-action"><i class="fas fa-barcode me-2"></i>ISBN Providers</a><a href="{{ route("library.reports") }}" class="list-group-item list-group-item-action"><i class="fas fa-chart-bar me-2"></i>Reports</a></div>
</div>
@endsection
