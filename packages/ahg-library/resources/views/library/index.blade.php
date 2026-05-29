@extends('theme::layouts.1col')
@section('title', 'Library Dashboard')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-book me-2"></i>{{ __('Library Management') }}</h1>
<div class="d-flex gap-2 mb-2 flex-wrap"><a href="{{ route("library.browse") }}" class="btn atom-btn-white"><i class="fas fa-th-list me-1"></i>{{ __('Browse') }}</a><a href="{{ route("library.create") }}" class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>{{ __('Add New') }}</a><a href="{{ route("library.isbn-providers") }}" class="btn atom-btn-white"><i class="fas fa-barcode me-1"></i>{{ __('ISBN Providers') }}</a><a href="{{ route("library.reports") }}" class="btn atom-btn-white"><i class="fas fa-chart-bar me-1"></i>{{ __('Reports') }}</a></div>
{{-- Library admin sections (previously reachable by direct URL only). --}}
<div class="d-flex gap-2 mb-3 flex-wrap">
  <a href="{{ route('library.circulation') }}" class="btn atom-btn-white"><i class="fas fa-right-left me-1"></i>{{ __('Circulation') }}</a>
  <a href="{{ route('library.patrons') }}" class="btn atom-btn-white"><i class="fas fa-users me-1"></i>{{ __('Patrons') }}</a>
  <a href="{{ route('library.serials') }}" class="btn atom-btn-white"><i class="fas fa-newspaper me-1"></i>{{ __('Serials') }}</a>
  <a href="{{ route('library.acquisitions') }}" class="btn atom-btn-white"><i class="fas fa-cart-shopping me-1"></i>{{ __('Acquisitions') }}</a>
  <a href="{{ route('library.ill') }}" class="btn atom-btn-white"><i class="fas fa-handshake me-1"></i>{{ __('Inter-Library Loan') }}</a>
  <a href="{{ route('library.kbart') }}" class="btn atom-btn-white"><i class="fas fa-file-import me-1"></i>{{ __('KBART Feeds') }}</a>
  <a href="{{ route('library.marc-index') }}" class="btn atom-btn-white"><i class="fas fa-file-code me-1"></i>{{ __('MARC Editor') }}</a>
  <a href="{{ route('library.onix-index') }}" class="btn atom-btn-white"><i class="fas fa-file-import me-1"></i>{{ __('ONIX Ingestion') }}</a>
  <a href="{{ route('library.usage') }}" class="btn atom-btn-white"><i class="fas fa-chart-line me-1"></i>{{ __('Usage (COUNTER)') }}</a>
</div><div class="row g-3 mb-4"><div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body text-center"><h2>{{ number_format($totalItems??0) }}</h2><small>{{ __('Total Items') }}</small></div></div></div><div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h2>{{ number_format($recentCount??0) }}</h2><small>{{ __('Added (30d)') }}</small></div></div></div><div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center"><h2>{{ number_format($circulatingCount??0) }}</h2><small>{{ __('Circulating') }}</small></div></div></div><div class="col-md-3"><div class="card bg-warning text-dark"><div class="card-body text-center"><h2>{{ number_format($overdueCount??0) }}</h2><small>{{ __('Overdue') }}</small></div></div></div></div>
</div>
@endsection
