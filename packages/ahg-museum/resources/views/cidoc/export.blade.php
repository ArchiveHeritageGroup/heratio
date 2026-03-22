@extends('theme::layouts.1col')
@section('title', 'CIDOC-CRM Export')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-share-alt me-2"></i>CIDOC-CRM Export</h1><div class="card"><div class="card-body"><form method="post" action="{{ route("museum.cidoc-export-download") }}">@csrf<div class="row"><div class="col-md-4 mb-3"><label class="form-label">Format <span class="badge bg-secondary ms-1">Optional</span></label><select name="format" class="form-select">@foreach($formats??[] as $fid=>$fdef)<option value="{{ $fid }}">{{ e($fdef["label"]??$fid) }}</option>@endforeach</select></div><div class="col-md-4 mb-3"><div class="form-check mt-4"><input type="checkbox" id="linkedData" name="linkedData" value="1" class="form-check-input"><label for="linkedData" class="form-check-label">Include Linked Data URIs</label></div></div><div class="col-md-4 mb-3 d-flex align-items-end"><button type="submit" class="btn atom-btn-white w-100"><i class="fas fa-download me-1"></i>Export</button></div></div></form></div></div>
</div>
@endsection
