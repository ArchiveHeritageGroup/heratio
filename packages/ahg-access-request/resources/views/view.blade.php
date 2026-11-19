@extends('theme::layouts.1col')

@section('title', 'View Access Request')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('homepage') }}">{{ __('Home') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('accessRequest.myRequests') }}">{{ __('My Requests') }}</a></li>
                    <li class="breadcrumb-item active">{{ __('Request #:value1', ['value1' => $accessRequest->id]) }}</li>
                </ol>
            </nav>


            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header" style="background-color: var(--ahg-primary); color: #fff;">
                            <h5 class="mb-0"><i class="fas fa-key me-2"></i>{{ __('Access Request #:value1', ['value1' => $accessRequest->id]) }}</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 200px;">{{ __('Subject') }}</th>
                                    <td>{{ $accessRequest->subject ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Requester') }}</th>
                                    <td>{{ $accessRequest->user_name ?? 'Unknown' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Request Type') }}</th>
                                    <td>{{ $accessRequest->request_type ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Scope') }}</th>
                                    <td>
                                        @include('ahg-access-request::partials.scope-badge', ['request' => $accessRequest])
                                        @if(!empty($accessRequest->scope_object_id))
                                            @php
                                                $scopeSlug = \Illuminate\Support\Facades\DB::table('slug')
                                                    ->where('object_id', $accessRequest->scope_object_id)->value('slug');
                                            @endphp
                                            @if($scopeSlug)
                                                <a href="{{ url('/'.$scopeSlug) }}" class="small d-block mt-1">
                                                    <i class="fas fa-arrow-up-right-from-square me-1" aria-hidden="true"></i>{{ __('Open the requested record') }}
                                                </a>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>{{ __('Status') }}</th>
                                    <td>
                                        @include('ahg-access-request::partials.status-badge', ['status' => $accessRequest->status])
                                        @if($openStep)
                                            <span class="d-block small text-muted mt-1"><i class="fas fa-route me-1" aria-hidden="true"></i>{{ __('Waiting at workflow step: :step', ['step' => $openStep->step_name]) }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>{{ __('Created') }}</th>
                                    <td>{{ $accessRequest->created_at ?? '' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Description') }}</th>
                                    <td>{{ $accessRequest->description ?? '' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Justification') }}</th>
                                    <td style="white-space: pre-line;">{{ $accessRequest->justification ?? '' }}</td>
                                </tr>
                                @if(! empty($accessRequest->conditions))
                                    <tr>
                                        <th>{{ __('Conditions of release') }}</th>
                                        <td style="white-space: pre-line;">{{ $accessRequest->conditions }}</td>
                                    </tr>
                                @endif
                                @if(! empty($accessRequest->review_notes))
                                    <tr>
                                        <th>{{ __('Reviewer notes') }}</th>
                                        <td style="white-space: pre-line;">{{ $accessRequest->review_notes }}</td>
                                    </tr>
                                @endif
                            </table>
                        </div>
                    </div>

                    @if($scopes->count() > 1 || $files)
                        {{-- heratio#1768: every record the request names; heratio#1775: released files. --}}
                        <div class="card mb-4" id="files">
                            <div class="card-header"><h6 class="mb-0"><i class="fas fa-list me-2"></i>{{ __('Records in this request') }} ({{ $scopes->count() }})</h6></div>
                            <ul class="list-group list-group-flush">
                                @foreach($scopes as $sc)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        @if($sc->slug)
                                            <a href="{{ url('/'.$sc->slug) }}">{{ $sc->object_title ?: '#'.$sc->object_id }}</a>
                                        @else
                                            <span>{{ $sc->object_title ?: '#'.$sc->object_id }}</span>
                                        @endif
                                        @if(! empty($files[$sc->object_id]))
                                            <a href="{{ route('cart.download', $files[$sc->object_id]) }}" class="atom-btn-white btn-sm"><i class="fas fa-download me-1"></i>{{ __('Download original') }}</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(! empty($accessRequest->quote_amount))
                        {{-- heratio#1774: the quotation; the records are released once it is paid. --}}
                        <div class="card mb-4" id="quotation">
                            <div class="card-header"><h6 class="mb-0"><i class="fas fa-file-invoice me-2"></i>{{ __('Quotation') }}</h6></div>
                            <div class="card-body">
                                <p class="fs-5 mb-1">{{ $accessRequest->quote_currency ?? 'ZAR' }} {{ number_format((float) $accessRequest->quote_amount, 2) }}</p>
                                <p class="small text-muted mb-2">{{ __('Sent :date', ['date' => $accessRequest->quoted_at]) }}</p>
                                @if(! empty($accessRequest->quote_note))
                                    <p style="white-space: pre-line;">{{ $accessRequest->quote_note }}</p>
                                @endif
                                @if($accessRequest->status === 'quoted' && (int) $accessRequest->user_id === (int) auth()->id())
                                    @if(! empty($accessRequest->order_id))
                                        <a href="{{ route('cart.order-confirmation', $accessRequest->order_id) }}" class="btn atom-btn-white"><i class="fas fa-credit-card me-1"></i>{{ __('Go to payment') }}</a>
                                    @elseif(\Illuminate\Support\Facades\Route::has('cart.quote.accept'))
                                        <form method="post" action="{{ route('cart.quote.accept', $accessRequest->id) }}">
                                            @csrf
                                            <button type="submit" class="btn atom-btn-white"><i class="fas fa-check me-1"></i>{{ __('Accept and pay') }}</button>
                                        </form>
                                    @endif
                                    <p class="small text-muted mt-2 mb-0">{{ __('The records are released to you as soon as the payment is confirmed.') }}</p>
                                @elseif($accessRequest->status === 'quoted')
                                    <p class="small text-muted mb-0">{{ __('Waiting for the requester to pay.') }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($isStaff && in_array($accessRequest->status, ['pending', 'quoted'], true))
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">{{ __('Decision') }}</h6>
                            </div>
                            <div class="card-body">
                                <form method="post" action="{{ route('accessRequest.approve', $accessRequest->id) }}" class="mb-3">
                                    @csrf
                                    <label class="form-label" for="ar-conditions">{{ __('Conditions of release (optional)') }}</label>
                                    <textarea id="ar-conditions" name="conditions" class="form-control mb-2" rows="2" placeholder="{{ __('For example: credit the archive; no commercial use; low resolution only') }}"></textarea>
                                    <label class="form-label" for="ar-notes">{{ __('Notes to the requester (optional)') }}</label>
                                    <textarea id="ar-notes" name="notes" class="form-control mb-2" rows="2"></textarea>
                                    <button type="submit" class="btn atom-btn-white">
                                        <i class="fas fa-check me-1"></i>{{ __('Approve') }}
                                    </button>
                                    <span class="small text-muted ms-2">{{ __('With conditions filled in, the request is released with conditions.') }}</span>
                                </form>
                                @if($accessRequest->status === 'pending')
                                    <form method="post" action="{{ route('accessRequest.quote', $accessRequest->id) }}" class="mb-3 border-top pt-3">
                                        @csrf
                                        <label class="form-label" for="ar-amount">{{ __('Send a quotation') }}</label>
                                        <div class="input-group mb-2" style="max-width: 18rem;">
                                            <span class="input-group-text">{{ __('Amount') }}</span>
                                            <input id="ar-amount" type="number" name="amount" step="0.01" min="0.01" class="form-control" required>
                                        </div>
                                        <textarea name="quote_note" class="form-control mb-2" rows="2" aria-label="{{ __('What the quotation covers') }}" placeholder="{{ __('What the quotation covers') }}"></textarea>
                                        <button type="submit" class="btn atom-btn-white"><i class="fas fa-file-invoice me-1"></i>{{ __('Send quotation') }}</button>
                                        <span class="small text-muted ms-2">{{ __('The records are released only after payment.') }}</span>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('accessRequest.deny', $accessRequest->id) }}" class="border-top pt-3">
                                    @csrf
                                    <label class="form-label" for="ar-reason">{{ __('Reason for refusing') }}</label>
                                    <textarea id="ar-reason" name="reason" class="form-control mb-2" rows="2"></textarea>
                                    <button type="submit" class="btn atom-btn-outline-danger">
                                        <i class="fas fa-times me-1"></i>{{ __('Deny') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    {{-- heratio#1773: every step the request went through. --}}
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-clock-rotate-left me-2"></i>{{ __('History') }}</h6></div>
                        @if(empty($history))
                            <div class="card-body small text-muted">{{ __('No steps recorded yet.') }}</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>{{ __('When') }}</th><th>{{ __('Step') }}</th><th>{{ __('Action') }}</th><th>{{ __('By') }}</th><th>{{ __('Details') }}</th></tr></thead>
                                    <tbody>
                                        @foreach($history as $h)
                                            <tr>
                                                <td class="text-nowrap">{{ $h->at }}</td>
                                                <td>{{ $h->step ?? '' }}</td>
                                                <td>{{ ucfirst((string) $h->action) }}</td>
                                                <td>{{ $h->by ?? '' }}</td>
                                                <td>{{ $h->details ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('Request Info') }}</h6>
                        </div>
                        <div class="card-body small">
                            <p><strong>{{ __('Object:') }}</strong> {{ $accessRequest->object_slug ?? 'General request' }}</p>
                            <p><strong>{{ __('Updated:') }}</strong> {{ $accessRequest->updated_at ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
