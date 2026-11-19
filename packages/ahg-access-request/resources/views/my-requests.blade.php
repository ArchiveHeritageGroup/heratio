@extends('theme::layouts.1col')

@section('title', 'My Access Requests')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('homepage') }}">{{ __('Home') }}</a></li>
                    <li class="breadcrumb-item active">{{ __('My Access Requests') }}</li>
                </ol>
            </nav>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4><i class="fas fa-list me-2"></i>{{ __('My Access Requests') }}</h4>
                <a href="{{ route('accessRequest.create') }}" class="atom-btn-white">
                    <i class="fas fa-plus me-1"></i>{{ __('New Request') }}
                </a>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    @if($requests->isEmpty())
                        <div class="p-4 text-center text-muted">
                            <p>{{ __('You have no access requests.') }}</p>
                            <a href="{{ route('accessRequest.create') }}" class="atom-btn-white">{{ __('Create your first request') }}</a>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('ID') }}</th>
                                        <th>{{ __('Subject') }}</th>
                                        <th>{{ __('Type') }}</th>
                                        <th>{{ __('Scope') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Created') }}</th>
                                        <th>{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $__releases = \Illuminate\Support\Facades\Route::has('cart.download') ? \AhgCore\Services\ReleaseLinkService::forAccessRequests((int) auth()->id()) : []; @endphp
                                    @foreach($requests as $req)
                                        <tr>
                                            <td>{{ $req->id }}</td>
                                            <td>{{ $req->subject ?? 'N/A' }}</td>
                                            <td>{{ $req->request_type ?? 'N/A' }}</td>
                                            <td>@include('ahg-access-request::partials.scope-badge', ['request' => $req])</td>
                                            <td>
                                                @include('ahg-access-request::partials.status-badge', ['status' => $req->status])
                                            </td>
                                            <td>{{ $req->created_at ?? '' }}</td>
                                            <td>
                                                <a href="{{ route('accessRequest.view', $req->id) }}" class="atom-btn-white btn-sm">{{ __('View') }}</a>
                                                @php $__files = $__releases[$req->id] ?? []; @endphp
                                                @if(count($__files) === 1)
                                                    {{-- heratio#1775: the original released by this approval. --}}
                                                    <a href="{{ route('cart.download', reset($__files)) }}" class="atom-btn-white btn-sm"><i class="fas fa-download me-1"></i>{{ __('Download original') }}</a>
                                                @elseif(count($__files) > 1)
                                                    <a href="{{ route('accessRequest.view', $req->id) }}#files" class="atom-btn-white btn-sm"><i class="fas fa-download me-1"></i>{{ __(':count files', ['count' => count($__files)]) }}</a>
                                                @endif
                                                @if($req->status === 'quoted')
                                                    <a href="{{ route('accessRequest.view', $req->id) }}#quotation" class="atom-btn-white btn-sm"><i class="fas fa-file-invoice me-1"></i>{{ __('View quotation') }}</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3">
                            {{ $requests->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
