@extends('theme::layouts.1col')
@section('title', 'Trace Watermark')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-fingerprint me-2"></i>Trace Watermark</h1><div class="card"><div class="card-body"><form method="post" action="{{ route("acl.trace-watermark-result") }}" class="row g-3 mb-4">@csrf<div class="col-md-8"><label class="form-label">Watermark Code</label><input type="text" name="watermark_code" class="form-control" value="{{ $watermarkCode??"" }}" required></div><div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn atom-btn-white w-100"><i class="fas fa-search me-1"></i>Trace</button></div></form>@if($traceResult??null)<div class="alert alert-info"><strong>Traced to:</strong><br>User: {{ e($traceResult->username??"Unknown") }}<br>Date: {{ $traceResult->created_at??"" }}<br>Object: {{ e($traceResult->object_title??"") }}</div>@endif</div></div>
</div>
@endsection
