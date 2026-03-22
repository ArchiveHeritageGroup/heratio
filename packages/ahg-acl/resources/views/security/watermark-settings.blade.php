@extends('theme::layouts.1col')
@section('title', 'Watermark Settings')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-copyright me-2"></i>Watermark Settings</h1><div class="card"><div class="card-body"><form method="post" action="{{ route("acl.watermark-settings-store") }}">@csrf<div class="row"><div class="col-md-6 mb-3"><label class="form-label">Default Watermark Type <span class="badge bg-secondary ms-1">Optional</span></label><select name="default_watermark_type_id" class="form-select"><option value="">None</option>@foreach($watermarkTypes??[] as $wt)<option value="{{ $wt->id }}" {{ ($settings->default_watermark_type_id??0)==$wt->id?"selected":"" }}>{{ e($wt->name) }}</option>@endforeach</select></div><div class="col-md-6 mb-3"><label class="form-label">Position <span class="badge bg-secondary ms-1">Optional</span></label><select name="default_position" class="form-select">@foreach(["center"=>"Center","top-left"=>"Top Left","top-right"=>"Top Right","bottom-left"=>"Bottom Left","bottom-right"=>"Bottom Right"] as $val=>$lab)<option value="{{ $val }}" {{ ($settings->default_position??"center")===$val?"selected":"" }}>{{ $lab }}</option>@endforeach</select></div></div><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>Save</button></form></div></div>
</div>
@endsection
