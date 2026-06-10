{{-- heratio#1193 Gaussian splats: upload .ply/.splat/.ksplat, list + view. --}}
@extends('theme::layouts.1col')
@section('title', __('Gaussian splats'))

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
    <h1 class="h4 mb-0"><i class="fas fa-cube me-2 text-primary"></i>{{ __('Gaussian splats') }}</h1>
    <span class="text-muted small">{{ __('Photoreal radiance-field captures - rock-art panels, sites, objects') }}</span>
  </div>
  <p class="text-muted small">{{ __('Upload a trained Gaussian-splat scene (.ply, .splat or .ksplat) - produced off-platform with a tool like Postshot, Luma or nerfstudio - and explore it photoreal in the browser. Training from photos is not done here; this views the result.') }}</p>

  @if(session('splat_success'))<div class="alert alert-success alert-dismissible fade show">{{ session('splat_success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
  @if(session('splat_error'))<div class="alert alert-warning alert-dismissible fade show">{{ session('splat_error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ route('splats.store') }}" enctype="multipart/form-data" class="card card-body mb-4" style="max-width:680px">
    @csrf
    <div class="mb-2">
      <label class="form-label small mb-1">{{ __('Title') }}</label>
      <input type="text" name="title" class="form-control form-control-sm" placeholder="{{ __('e.g. Shelter 3 - painted frieze (splat)') }}" maxlength="200">
    </div>
    <div class="mb-2">
      <label class="form-label small mb-1">{{ __('Splat file') }} <span class="text-muted">(.ply, .splat, .ksplat)</span></label>
      <input type="file" name="splat" class="form-control form-control-sm" accept=".ply,.splat,.ksplat" required>
    </div>
    <div class="mb-2">
      <label class="form-label small mb-1">{{ __('Attach to record') }} <span class="text-muted">{{ __('(optional - record slug or numeric id; shows on that record page)') }}</span></label>
      <input type="text" name="record" class="form-control form-control-sm" placeholder="{{ __('e.g. desk') }}">
    </div>
    <div><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload me-1"></i>{{ __('Upload') }}</button></div>
  </form>

  @if(empty($splats))
    <div class="alert alert-info">{{ __('No splat captures yet.') }}</div>
  @else
    <table class="table table-sm table-hover align-middle">
      <thead class="table-light"><tr><th>{{ __('Title') }}</th><th>{{ __('Format') }}</th><th>{{ __('Size') }}</th><th>{{ __('Status') }}</th><th>{{ __('Attached record') }}</th><th></th></tr></thead>
      <tbody>
        @foreach($splats as $s)
          <tr>
            <td class="fw-bold">{{ $s->title }}</td>
            <td class="small text-muted">{{ strtoupper($s->format ?? '') }}</td>
            <td class="small text-muted">{{ $s->size_bytes ? number_format($s->size_bytes / 1048576, 1).' MB' : '—' }}</td>
            <td>@if($s->status === 'ready')<span class="badge bg-success">{{ __('Ready') }}</span>@else<span class="badge bg-danger" title="{{ $s->error }}">{{ __('Failed') }}</span>@endif</td>
            <td>
              <form method="POST" action="{{ route('splats.attach', ['id' => $s->id]) }}" class="d-flex gap-1 align-items-center">
                @csrf
                <input type="text" name="record" class="form-control form-control-sm" style="max-width:11rem" value="{{ $s->object_slug ?? '' }}" placeholder="{{ __('slug or id') }}">
                <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Attach / change') }}"><i class="fas fa-link"></i></button>
              </form>
              @if($s->object_title)<a href="/{{ $s->object_slug }}" target="_blank" rel="noopener" class="small text-muted">{{ \Illuminate\Support\Str::limit($s->object_title, 40) }}</a>@endif
            </td>
            <td class="text-end">
              @if($s->status === 'ready')
                <a href="{{ route('splats.show', ['slug' => $s->slug]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
