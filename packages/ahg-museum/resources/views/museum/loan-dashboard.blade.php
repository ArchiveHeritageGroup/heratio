@extends('theme::layouts.1col')
@section('title', 'Loan Management')
@section('content')
<div class="container py-4">
<h1>Loan Management</h1><div class="row mb-4"><div class="col-md-2"><div class="card"><div class="card-body text-center"><h3>{{ $stats["total_loans"]??0 }}</h3><small>Total</small></div></div></div><div class="col-md-2"><div class="card bg-info text-white"><div class="card-body text-center"><h3>{{ $stats["active_loans_out"]??0 }}</h3><small>Out</small></div></div></div><div class="col-md-2"><div class="card bg-primary text-white"><div class="card-body text-center"><h3>{{ $stats["active_loans_in"]??0 }}</h3><small>In</small></div></div></div><div class="col-md-2"><div class="card bg-danger text-white"><div class="card-body text-center"><h3>{{ $stats["overdue"]??0 }}</h3><small>Overdue</small></div></div></div><div class="col-md-2"><div class="card bg-warning text-dark"><div class="card-body text-center"><h3>{{ $stats["due_this_month"]??0 }}</h3><small>Due Soon</small></div></div></div><div class="col-md-2"><div class="card bg-success text-white"><div class="card-body text-center"><h3>R{{ number_format($stats["total_insurance_value"]??0) }}</h3><small>Insured</small></div></div></div></div>
</div>
@endsection
