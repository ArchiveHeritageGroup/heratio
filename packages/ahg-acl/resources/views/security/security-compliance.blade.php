@extends('theme::layouts.1col')
@section('title', 'Security Compliance')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-balance-scale me-2"></i>Security Compliance</h1><div class="row g-3 mb-4"><div class="col-md-3"><div class="card border-success"><div class="card-body text-center"><h2 class="text-success">{{ $compliance["score"]??"N/A" }}%</h2><small>Compliance Score</small></div></div></div><div class="col-md-3"><div class="card"><div class="card-body text-center"><h3>{{ $compliance["issues"]??0 }}</h3><small>Open Issues</small></div></div></div><div class="col-md-3"><div class="card"><div class="card-body text-center"><h3>{{ $compliance["overdue_reviews"]??0 }}</h3><small>Overdue Reviews</small></div></div></div><div class="col-md-3"><div class="card"><div class="card-body text-center"><h3>{{ $compliance["expired_clearances"]??0 }}</h3><small>Expired Clearances</small></div></div></div></div>
</div>
@endsection
