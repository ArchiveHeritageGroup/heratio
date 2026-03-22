@extends('theme::layouts.1col')
@section('title', 'GRAP 103 Compliance')
@section('content')
<div class="container py-4">
<h1>GRAP 103 Financial Compliance Dashboard</h1><div class="row mb-4"><div class="col-md-3"><div class="card border-primary"><div class="card-body text-center"><h3 class="text-primary">{{ number_format($stats["total_assets"]??0) }}</h3><small>Total Heritage Assets</small></div></div></div><div class="col-md-3"><div class="card border-success"><div class="card-body text-center"><h3 class="text-success">{{ number_format($stats["valued_assets"]??0) }}</h3><small>Valued Assets</small></div></div></div><div class="col-md-3"><div class="card border-warning"><div class="card-body text-center"><h3 class="text-warning">{{ number_format($stats["unvalued_assets"]??0) }}</h3><small>Unvalued</small></div></div></div><div class="col-md-3"><div class="card border-info"><div class="card-body text-center"><h3 class="text-info">R{{ number_format($stats["total_value"]??0,2) }}</h3><small>Total Value</small></div></div></div></div>
</div>
@endsection
