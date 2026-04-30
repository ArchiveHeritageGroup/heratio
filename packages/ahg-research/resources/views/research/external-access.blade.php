{{-- External Access - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'workspace'])@endsection
@section('title', 'External Access')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">External Access</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-globe text-primary me-2"></i>{{ __('External Access') }}</h1>
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">Shared Links</div>
            <div class="card-body p-0">
                @if(!empty($sharedLinks))
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>{{ __('Resource') }}</th><th>{{ __('Type') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Views') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach($sharedLinks as $link)
                        <tr>
                            <td>{{ e($link->resource_title ?? '') }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($link->access_type ?? '') }}</span></td>
                            <td class="small">{{ $link->expires_at ?? 'Never' }}</td>
                            <td>{{ $link->view_count ?? 0 }}</td>
                            <td><span class="badge bg-{{ ($link->is_active ?? false) ? 'success' : 'danger' }}">{{ ($link->is_active ?? false) ? 'Active' : 'Expired' }}</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="navigator.clipboard.writeText('{{ url('/external/' . ($link->token ?? '')) }}')"><i class="fas fa-copy"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Revoke this link?')">@csrf @method('DELETE') <input type="hidden" name="link_id" value="{{ $link->id }}"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button></form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="text-center py-4 text-muted">No shared links yet.</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">{{ __('Create Shared Link') }}</h6></div>
            <div class="card-body">
                <form method="POST">@csrf
                    <div class="mb-3"><label class="form-label">Resource <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label><select name="resource_id" class="form-select" required><option value="">-- Select --</option>
                        @foreach($resources ?? [] as $res)<option value="{{ $res->id }}">{{ e($res->title ?? '') }}</option>@endforeach
                    </select></div>
                    <div class="mb-3"><label class="form-label">Access Type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><select name="access_type" class="form-select"><option value="view">{{ __('View Only') }}</option><option value="download">{{ __('Download') }}</option><option value="annotate">{{ __('Annotate') }}</option></select></div>
                    <div class="mb-3"><label class="form-label">Expires <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="datetime-local" name="expires_at" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Password (optional) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="text" name="password" class="form-control"></div>
                    <button type="submit" class="btn atom-btn-white w-100"><i class="fas fa-link me-1"></i>{{ __('Create Link') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection