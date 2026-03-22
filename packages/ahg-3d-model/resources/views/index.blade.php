@extends('theme::layouts.1col')

@section('title', '3D Models')
@section('body-class', 'browse model3d')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-cubes me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">3D Models</h1>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">{{ number_format($total ?? 0) }} models in the archive</p>
    <a href="{{ route('admin.3d-models.settings') }}" class="btn atom-btn-white"><i class="fas fa-cog me-1"></i>Settings</a>
  </div>
  @if(isset($models) && count($models))
    <div class="table-responsive"><table class="table table-bordered table-hover mb-0">
      <thead><tr style="background:var(--ahg-primary);color:#fff"><th style="width:60px"></th><th>Model</th><th>Object</th><th>Format</th><th>Size</th><th>Status</th><th style="width:100px">Actions</th></tr></thead>
      <tbody>@foreach($models as $m)<tr>
        <td>@if($m->thumbnail)<img src="/uploads/{{ $m->thumbnail }}" class="rounded" style="width:50px;height:50px;object-fit:cover">@else<div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" style="width:50px;height:50px"><i class="fas fa-cube"></i></div>@endif</td>
        <td><a href="{{ route('admin.3d-models.view', $m->id) }}"><strong>{{ $m->model_title ?: $m->original_filename }}</strong></a>@if($m->is_primary)<span class="badge bg-primary ms-1">Primary</span>@endif @if($m->ar_enabled)<span class="badge bg-success ms-1">AR</span>@endif</td>
        <td>@if($m->object_slug)<a href="{{ url($m->object_slug) }}">{{ Str::limit($m->object_title ?: 'Untitled', 40) }}</a>@else<span class="text-muted">-</span>@endif</td>
        <td><span class="badge bg-secondary">{{ strtoupper($m->format) }}</span></td>
        <td><small>{{ number_format($m->file_size/1048576, 2) }} MB</small></td>
        <td>@if($m->is_public)<span class="text-success"><i class="fas fa-check-circle"></i> Public</span>@else<span class="text-warning"><i class="fas fa-eye-slash"></i> Hidden</span>@endif</td>
        <td><div class="btn-group btn-group-sm"><a href="{{ route('admin.3d-models.view', $m->id) }}" class="btn atom-btn-white" title="View"><i class="fas fa-eye"></i></a><a href="{{ route('admin.3d-models.edit', $m->id) }}" class="btn atom-btn-white" title="Edit"><i class="fas fa-edit"></i></a></div></td>
      </tr>@endforeach</tbody>
    </table></div>
    @if(isset($pager))@include('ahg-core::components.pager', ['pager' => $pager])@endif
  @else
    <div class="card"><div class="card-body text-center py-5"><i class="fas fa-cube fa-4x text-muted mb-3"></i><h4>No 3D Models Yet</h4><p class="text-muted">Upload 3D models from individual object pages.</p></div></div>
  @endif
@endsection
