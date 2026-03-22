@extends('theme::layouts.1col')
@section('title', 'Data Quality Dashboard')
@section('content')
<div class="container py-4">
<h1>Data Quality Dashboard</h1><div class="row g-3 mb-4"><div class="col-md-3"><div class="card" style="background:var(--ahg-primary);color:#fff"><div class="card-body text-center"><h2>{{ number_format($overallScore??0) }}%</h2><small>Overall Score</small></div></div></div><div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center"><h2>{{ number_format($analyzedRecords??0) }}</h2><small>Records Analyzed</small></div></div></div><div class="col-md-3"><div class="card bg-warning text-dark"><div class="card-body text-center"><h2>{{ $overallGrade["grade"]??"N/A" }}</h2><small>{{ $overallGrade["label"]??"" }}</small></div></div></div><div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h2>{{ count($missingFieldCounts??[]) }}</h2><small>Fields Tracked</small></div></div></div></div>
</div>
@endsection
