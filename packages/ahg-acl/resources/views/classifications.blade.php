@extends('theme::layouts.1col')

@section('title', 'Security Classifications')

@section('content')
<div class="container py-4">

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Admin</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.groups') }}">ACL</a></li>
      <li class="breadcrumb-item active" aria-current="page">Security Classifications</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-shield-alt me-2"></i> Security Classifications</h2>
    <a href="{{ route('acl.groups') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i> Back to ACL
    </a>
  </div>

  <div class="card">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i> Classification Levels</h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover mb-0">
          <thead>
            <tr>
              <th>Color</th>
              <th>Code</th>
              <th>Name</th>
              <th class="text-center">Level</th>
              <th>Description</th>
              <th class="text-center">2FA</th>
              <th class="text-center">Approval</th>
              <th class="text-center">Justification</th>
              <th class="text-center">Watermark</th>
              <th class="text-center">Download</th>
              <th class="text-center">Print</th>
              <th class="text-center">Copy</th>
            </tr>
          </thead>
          <tbody>
            @forelse($classifications as $cls)
              <tr>
                <td>
                  <span class="d-inline-block rounded-circle" style="width:20px; height:20px; background-color:{{ $cls->color ?? '#ccc' }};" title="{{ $cls->color }}"></span>
                </td>
                <td><code>{{ $cls->code }}</code></td>
                <td><strong>{{ $cls->name }}</strong></td>
                <td class="text-center"><span class="badge bg-secondary">{{ $cls->level }}</span></td>
                <td>{{ \Illuminate\Support\Str::limit($cls->description ?? '', 60) }}</td>
                <td class="text-center">
                  @if($cls->requires_2fa)
                    <i class="fas fa-check-circle text-success" title="Required"></i>
                  @else
                    <i class="fas fa-times-circle text-muted" title="Not required"></i>
                  @endif
                </td>
                <td class="text-center">
                  @if($cls->requires_approval)
                    <i class="fas fa-check-circle text-success" title="Required"></i>
                  @else
                    <i class="fas fa-times-circle text-muted" title="Not required"></i>
                  @endif
                </td>
                <td class="text-center">
                  @if($cls->requires_justification)
                    <i class="fas fa-check-circle text-success" title="Required"></i>
                  @else
                    <i class="fas fa-times-circle text-muted" title="Not required"></i>
                  @endif
                </td>
                <td class="text-center">
                  @if($cls->watermark_required)
                    <i class="fas fa-check-circle text-warning" title="Required"></i>
                  @else
                    <i class="fas fa-times-circle text-muted" title="Not required"></i>
                  @endif
                </td>
                <td class="text-center">
                  @if($cls->download_allowed)
                    <i class="fas fa-check-circle text-success" title="Allowed"></i>
                  @else
                    <i class="fas fa-ban text-danger" title="Blocked"></i>
                  @endif
                </td>
                <td class="text-center">
                  @if($cls->print_allowed)
                    <i class="fas fa-check-circle text-success" title="Allowed"></i>
                  @else
                    <i class="fas fa-ban text-danger" title="Blocked"></i>
                  @endif
                </td>
                <td class="text-center">
                  @if($cls->copy_allowed)
                    <i class="fas fa-check-circle text-success" title="Allowed"></i>
                  @else
                    <i class="fas fa-ban text-danger" title="Blocked"></i>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="12" class="text-center text-muted py-4">No classification levels defined.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
