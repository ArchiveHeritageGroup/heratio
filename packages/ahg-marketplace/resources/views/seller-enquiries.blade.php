{{--
  Marketplace — Seller Enquiries Inbox

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/sellerEnquiriesSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Enquiries') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace seller-enquiries')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Enquiries') }}</li>
  </ol>
</nav>

@if(session('success') || session('notice'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') ?? session('notice') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<h1 class="h3 mb-4">{{ __('Enquiries') }}</h1>

@if(empty($enquiries) || count($enquiries) === 0)
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-envelope fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('No enquiries yet') }}</h5>
      <p class="text-muted">{{ __('Enquiries from potential buyers will appear here.') }}</p>
    </div>
  </div>
@else
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Listing') }}</th>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Email') }}</th>
            <th>{{ __('Subject') }}</th>
            <th>{{ __('Status') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($enquiries as $enq)
            @php
              $statusClass = match($enq->status ?? '') {
                'new' => 'primary',
                'read' => 'info',
                'replied' => 'success',
                'closed' => 'secondary',
                default => 'secondary',
              };
            @endphp
            <tr>
              <td class="small text-muted">{{ !empty($enq->created_at) ? date('d M Y', strtotime($enq->created_at)) : '' }}</td>
              <td>
                <a href="{{ route('ahgmarketplace.listing', ['slug' => $enq->listing_slug ?? '']) }}" class="text-decoration-none">{{ $enq->listing_title ?? '-' }}</a>
              </td>
              <td class="small">{{ $enq->name ?? '-' }}</td>
              <td class="small">{{ $enq->email ?? '' }}</td>
              <td class="small">{{ $enq->subject ?? '-' }}</td>
              <td><span class="badge bg-{{ $statusClass }}">{{ ucfirst($enq->status ?? '-') }}</span></td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#enquiry-{{ (int) $enq->id }}">
                  <i class="fas fa-eye me-1"></i>{{ __('View') }}
                </button>
              </td>
            </tr>
            <tr class="collapse" id="enquiry-{{ (int) $enq->id }}">
              <td colspan="7">
                <div class="p-3 bg-light rounded">
                  <p class="mb-2"><strong>{{ __('Message:') }}</strong></p>
                  <p class="mb-3">{!! nl2br(e($enq->message ?? '')) !!}</p>

                  @if(!empty($enq->reply))
                    <div class="border-start border-3 border-success ps-3 mb-3">
                      <p class="mb-1"><strong class="text-success">{{ __('Your Reply:') }}</strong></p>
                      <p class="mb-0">{!! nl2br(e($enq->reply)) !!}</p>
                      @if(!empty($enq->replied_at))
                        <small class="text-muted">{{ date('d M Y H:i', strtotime($enq->replied_at)) }}</small>
                      @endif
                    </div>
                  @endif

                  @if(($enq->status ?? '') !== 'replied' && ($enq->status ?? '') !== 'closed')
                    <form method="POST" action="{{ route('ahgmarketplace.seller-enquiries.post') }}">
                      @csrf
                      <input type="hidden" name="form_action" value="reply">
                      <input type="hidden" name="enquiry_id" value="{{ (int) $enq->id }}">
                      <div class="mb-2">
                        <label for="reply-{{ (int) $enq->id }}" class="form-label">{{ __('Reply') }}</label>
                        <textarea class="form-control" id="reply-{{ (int) $enq->id }}" name="reply" rows="3" required placeholder="{{ __('Type your reply...') }}"></textarea>
                      </div>
                      <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-reply me-1"></i>{{ __('Send Reply') }}
                      </button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  @php $totalPages = (int) ceil(($total ?? 0) / ($limit ?? 20)); @endphp
  @if($totalPages > 1)
    <nav class="mt-4" aria-label="{{ __('Pagination') }}">
      <ul class="pagination justify-content-center">
        <li class="page-item{{ ($page ?? 1) <= 1 ? ' disabled' : '' }}">
          <a class="page-link" href="?page={{ ($page ?? 1) - 1 }}">&laquo;</a>
        </li>
        @for($i = max(1, ($page ?? 1) - 2); $i <= min($totalPages, ($page ?? 1) + 2); $i++)
          <li class="page-item{{ $i === ($page ?? 1) ? ' active' : '' }}">
            <a class="page-link" href="?page={{ $i }}">{{ $i }}</a>
          </li>
        @endfor
        <li class="page-item{{ ($page ?? 1) >= $totalPages ? ' disabled' : '' }}">
          <a class="page-link" href="?page={{ ($page ?? 1) + 1 }}">&raquo;</a>
        </li>
      </ul>
    </nav>
  @endif
@endif
@endsection
