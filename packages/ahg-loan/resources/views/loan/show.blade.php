@extends('theme::layouts.1col')

@section('title', $loan->loan_number . ' - Loan Detail')
@section('body-class', 'view loan')

@section('content')

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Pagination --}}
  <div class="d-flex justify-content-between mb-2">
    @if($previousLoan ?? null)
      <a href="{{ route('loan.show', $previousLoan->id) }}" class="btn btn-sm atom-btn-white">
        <i class="fas fa-chevron-left me-1"></i>Previous
      </a>
    @else
      <span></span>
    @endif
    @if($nextLoan ?? null)
      <a href="{{ route('loan.show', $nextLoan->id) }}" class="btn btn-sm atom-btn-white">
        Next<i class="fas fa-chevron-right ms-1"></i>
      </a>
    @else
      <span></span>
    @endif
  </div>

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h1 class="mb-1">
        <i class="fas fa-handshake me-2"></i>{{ $loan->loan_number }}
        <span class="badge bg-{{ \AhgLoan\Services\LoanService::getStatusColour($loan->status) }} ms-2 fs-6">
          {{ ucwords(str_replace('_', ' ', $loan->status)) }}
        </span>
        @if($loan->loan_type === 'out')
          <span class="badge bg-info ms-1 fs-6"><i class="fas fa-arrow-right me-1"></i>Outgoing</span>
        @else
          <span class="badge bg-warning text-dark ms-1 fs-6"><i class="fas fa-arrow-left me-1"></i>Incoming</span>
        @endif
      </h1>
      @if($loan->title)
        <p class="text-muted mb-0 fs-5">{{ $loan->title }}</p>
      @endif
    </div>

    @auth
      <div class="d-flex gap-2 flex-wrap justify-content-end">
        <a href="{{ route('loan.edit', $loan->id) }}" class="btn btn-sm atom-btn-white">
          <i class="fas fa-edit me-1"></i>Edit
        </a>

        @if(count($validTransitions))
          <div class="dropdown">
            <button class="btn btn-sm atom-btn-white dropdown-toggle" type="button" data-bs-toggle="dropdown">
              <i class="fas fa-exchange-alt me-1"></i>Change Status
            </button>
            <ul class="dropdown-menu">
              @foreach($validTransitions as $nextStatus)
                <li>
                  <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#transitionModal"
                          onclick="document.getElementById('transition-new-status').value='{{ $nextStatus }}';">
                    <span class="badge bg-{{ \AhgLoan\Services\LoanService::getStatusColour($nextStatus) }} me-1">
                      {{ ucwords(str_replace('_', ' ', $nextStatus)) }}
                    </span>
                  </button>
                </li>
              @endforeach
            </ul>
          </div>
        @endif

        @if(in_array($loan->status, ['on_loan','dispatched','in_transit','received']))
          <button type="button" class="btn btn-sm atom-btn-outline-success" data-bs-toggle="modal" data-bs-target="#extendModal">
            <i class="fas fa-calendar-plus me-1"></i>Extend
          </button>
        @endif

        @if($loan->status === 'return_requested')
          <button type="button" class="btn btn-sm atom-btn-white" data-bs-toggle="modal" data-bs-target="#returnModal">
            <i class="fas fa-undo me-1"></i>Record Return
          </button>
        @endif

        @if(auth()->user()->is_admin ?? false)
          <form method="POST" action="{{ route('loan.delete', $loan->id) }}"
                onsubmit="return confirm('Are you sure you want to delete this loan?');" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm atom-btn-outline-danger">
              <i class="fas fa-trash me-1"></i>Delete
            </button>
          </form>
        @endif
      </div>
    @endauth
  </div>

  @php
    $isOverdue = $loan->end_date && $loan->end_date < now()->toDateString()
                 && in_array($loan->status, ['on_loan','dispatched','in_transit','received']);
  @endphp
  @if($isOverdue)
    <div class="alert alert-danger">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <strong>This loan is overdue!</strong> The end date was {{ \Carbon\Carbon::parse($loan->end_date)->format('Y-m-d') }}.
    </div>
  @endif

  {{-- Loan Details --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Loan Details</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <h6 class="border-bottom pb-2 mb-3">General Information</h6>

          <div class="row mb-2">
            <div class="col-sm-5 fw-bold">Loan Number</div>
            <div class="col-sm-7">{{ $loan->loan_number }}</div>
          </div>

          <div class="row mb-2">
            <div class="col-sm-5 fw-bold">Type</div>
            <div class="col-sm-7">{{ $loan->loan_type === 'out' ? 'Outgoing' : 'Incoming' }}</div>
          </div>

          <div class="row mb-2">
            <div class="col-sm-5 fw-bold">Sector</div>
            <div class="col-sm-7">{{ ucfirst($loan->sector) }}</div>
          </div>

          @if($loan->title)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Title</div>
              <div class="col-sm-7">{{ $loan->title }}</div>
            </div>
          @endif

          @if($loan->description)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Description</div>
              <div class="col-sm-7">{!! nl2br(e($loan->description)) !!}</div>
            </div>
          @endif

          @if($loan->purpose)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Purpose</div>
              <div class="col-sm-7">{{ ucfirst($loan->purpose) }}</div>
            </div>
          @endif

          @if($loan->notes)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Notes</div>
              <div class="col-sm-7">{!! nl2br(e($loan->notes)) !!}</div>
            </div>
          @endif

          <h6 class="border-bottom pb-2 mb-3 mt-4">Dates</h6>

          @if($loan->request_date)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Request Date</div>
              <div class="col-sm-7">{{ \Carbon\Carbon::parse($loan->request_date)->format('Y-m-d') }}</div>
            </div>
          @endif

          @if($loan->start_date)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Start Date</div>
              <div class="col-sm-7">{{ \Carbon\Carbon::parse($loan->start_date)->format('Y-m-d') }}</div>
            </div>
          @endif

          @if($loan->end_date)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">End Date</div>
              <div class="col-sm-7">
                {{ \Carbon\Carbon::parse($loan->end_date)->format('Y-m-d') }}
                @if($isOverdue)
                  <span class="badge bg-danger ms-1">Overdue</span>
                @endif
              </div>
            </div>
          @endif

          @if($loan->return_date)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Return Date</div>
              <div class="col-sm-7">{{ \Carbon\Carbon::parse($loan->return_date)->format('Y-m-d') }}</div>
            </div>
          @endif

          @if($loan->approved_date)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Approved Date</div>
              <div class="col-sm-7">{{ \Carbon\Carbon::parse($loan->approved_date)->format('Y-m-d H:i') }}</div>
            </div>
          @endif
        </div>

        <div class="col-md-6">
          <h6 class="border-bottom pb-2 mb-3">Partner Information</h6>

          <div class="row mb-2">
            <div class="col-sm-5 fw-bold">Institution</div>
            <div class="col-sm-7">{{ $loan->partner_institution }}</div>
          </div>

          @if($loan->partner_contact_name)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Contact Name</div>
              <div class="col-sm-7">{{ $loan->partner_contact_name }}</div>
            </div>
          @endif

          @if($loan->partner_contact_email)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Contact Email</div>
              <div class="col-sm-7"><a href="mailto:{{ $loan->partner_contact_email }}">{{ $loan->partner_contact_email }}</a></div>
            </div>
          @endif

          @if($loan->partner_contact_phone)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Contact Phone</div>
              <div class="col-sm-7">{{ $loan->partner_contact_phone }}</div>
            </div>
          @endif

          @if($loan->partner_address)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Address</div>
              <div class="col-sm-7">{!! nl2br(e($loan->partner_address)) !!}</div>
            </div>
          @endif

          <h6 class="border-bottom pb-2 mb-3 mt-4">Insurance &amp; Fees</h6>

          @if($loan->insurance_type)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Insurance Type</div>
              <div class="col-sm-7">{{ ucfirst(str_replace('_', ' ', $loan->insurance_type)) }}</div>
            </div>
          @endif

          @if($loan->insurance_value)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Insurance Value</div>
              <div class="col-sm-7">{{ $loan->insurance_currency ?? 'ZAR' }} {{ number_format($loan->insurance_value, 2) }}</div>
            </div>
          @endif

          @if($loan->insurance_policy_number)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Policy Number</div>
              <div class="col-sm-7">{{ $loan->insurance_policy_number }}</div>
            </div>
          @endif

          @if($loan->insurance_provider)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Insurance Provider</div>
              <div class="col-sm-7">{{ $loan->insurance_provider }}</div>
            </div>
          @endif

          @if($loan->loan_fee)
            <div class="row mb-2">
              <div class="col-sm-5 fw-bold">Loan Fee</div>
              <div class="col-sm-7">{{ $loan->loan_fee_currency ?? 'ZAR' }} {{ number_format($loan->loan_fee, 2) }}</div>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Objects --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-cubes me-2"></i>Loan Objects ({{ count($loan->objects) }})</h5>
    </div>
    <div class="card-body">
      @auth
        <form method="POST" action="{{ route('loan.add-object', $loan->id) }}" class="row g-2 mb-3">
          @csrf
          <div class="col-md-5">
            <div class="input-group input-group-sm">
              <span class="input-group-text"><i class="fas fa-search"></i></span>
              <input type="text" id="object-search" class="form-control" placeholder="Search objects by title or identifier..." autocomplete="off">
              <input type="hidden" name="object_id" id="object-id" required>
            </div>
            <div id="object-search-results" class="list-group position-absolute" style="z-index:1050; display:none; max-height:250px; overflow-y:auto;"></div>
          </div>
          <div class="col-md-2">
            <input type="number" name="insurance_value" class="form-control form-control-sm" placeholder="Insurance value" step="0.01" min="0">
          </div>
          <div class="col-md-3">
            <input type="text" name="special_requirements" class="form-control form-control-sm" placeholder="Special requirements">
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-sm atom-btn-outline-success w-100"><i class="fas fa-plus me-1"></i>Add Object</button>
          </div>
        </form>
      @endauth

      @if(count($loan->objects))
        <div class="table-responsive">
          <table class="table table-bordered table-sm mb-0">
            <thead>
              <tr>
                <th>Title</th>
                <th>Identifier</th>
                <th>Type</th>
                <th>Insurance Value</th>
                <th>Status</th>
                <th>Special Requirements</th>
                @auth <th class="text-center">Actions</th> @endauth
              </tr>
            </thead>
            <tbody>
              @foreach($loan->objects as $obj)
                <tr>
                  <td>
                    @if($obj->information_object_id)
                      <a href="{{ route('informationobject.show', $obj->information_object_id) }}">
                        {{ $obj->io_title ?: $obj->object_title ?: '[Untitled]' }}
                      </a>
                    @else
                      {{ $obj->object_title ?: '[Untitled]' }}
                    @endif
                  </td>
                  <td>{{ $obj->object_identifier ?: '-' }}</td>
                  <td>{{ $obj->object_type ?: '-' }}</td>
                  <td>{{ $obj->insurance_value ? number_format($obj->insurance_value, 2) : '-' }}</td>
                  <td>
                    <span class="badge bg-{{ $obj->status === 'returned' ? 'success' : ($obj->status === 'pending' ? 'secondary' : 'primary') }}">
                      {{ ucfirst($obj->status) }}
                    </span>
                  </td>
                  <td>{{ $obj->special_requirements ?: '-' }}</td>
                  @auth
                    <td class="text-center">
                      <form method="POST" action="{{ route('loan.remove-object', [$loan->id, $obj->id]) }}"
                            onsubmit="return confirm('Remove this object from the loan?');" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="Remove">
                          <i class="fas fa-times"></i>
                        </button>
                      </form>
                    </td>
                  @endauth
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i>No objects added to this loan yet.</p>
      @endif
    </div>
  </div>

  {{-- Documents --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Documents ({{ count($loan->documents) }})</h5>
    </div>
    <div class="card-body">
      @auth
        <form method="POST" action="{{ route('loan.upload-document', $loan->id) }}" enctype="multipart/form-data" class="row g-2 mb-3">
          @csrf
          <div class="col-md-4">
            <input type="file" name="document" class="form-control form-control-sm" required>
          </div>
          <div class="col-md-3">
            <select name="document_type" class="form-select form-select-sm" required>
              <option value="">Document type...</option>
              <option value="agreement">Loan Agreement</option>
              <option value="insurance">Insurance Certificate</option>
              <option value="condition_report">Condition Report</option>
              <option value="facility_report">Facility Report</option>
              <option value="correspondence">Correspondence</option>
              <option value="invoice">Invoice</option>
              <option value="receipt">Receipt</option>
              <option value="customs">Customs Document</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-sm atom-btn-white w-100 text-white"><i class="fas fa-upload me-1"></i>Upload</button>
          </div>
        </form>
      @endauth

      @if(count($loan->documents))
        <div class="table-responsive">
          <table class="table table-bordered table-sm mb-0">
            <thead>
              <tr>
                <th>File Name</th>
                <th>Type</th>
                <th>Size</th>
                <th>Uploaded</th>
              </tr>
            </thead>
            <tbody>
              @foreach($loan->documents as $doc)
                <tr>
                  <td>
                    @if($doc->file_path)
                      <a href="{{ url('/uploads/' . $doc->file_path) }}" target="_blank" title="Download {{ $doc->file_name }}">
                        <i class="fas fa-file me-1"></i>{{ $doc->file_name }}
                      </a>
                    @else
                      <i class="fas fa-file me-1"></i>{{ $doc->file_name }}
                    @endif
                  </td>
                  <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $doc->document_type)) }}</span></td>
                  <td>{{ $doc->file_size ? round($doc->file_size / 1024, 1) . ' KB' : '-' }}</td>
                  <td>{{ $doc->created_at ? \Carbon\Carbon::parse($doc->created_at)->format('Y-m-d H:i') : '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i>No documents uploaded yet.</p>
      @endif
    </div>
  </div>

  {{-- Extensions --}}
  <div class="card mb-4">
    <div class="card-header text-dark" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-calendar-plus me-2"></i>Extensions ({{ count($loan->extensions) }})</h5>
    </div>
    <div class="card-body">
      @if(count($loan->extensions))
        <div class="table-responsive">
          <table class="table table-bordered table-sm mb-0">
            <thead>
              <tr>
                <th>Previous End Date</th>
                <th>New End Date</th>
                <th>Reason</th>
                <th>Approved By</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              @foreach($loan->extensions as $ext)
                <tr>
                  <td>{{ $ext->previous_end_date }}</td>
                  <td>{{ $ext->new_end_date }}</td>
                  <td>{{ $ext->reason ?: '-' }}</td>
                  <td>{{ $ext->approved_by_name ?: '-' }}</td>
                  <td>{{ $ext->created_at ? \Carbon\Carbon::parse($ext->created_at)->format('Y-m-d H:i') : '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i>No extensions recorded.</p>
      @endif
    </div>
  </div>

  {{-- Condition Reports --}}
  @if(count($loan->condition_reports))
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Condition Reports ({{ count($loan->condition_reports) }})</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-sm mb-0">
            <thead>
              <tr>
                <th>Type</th>
                <th>Examination Date</th>
                <th>Examiner</th>
                <th>Overall Condition</th>
                <th>Has Damage</th>
                <th>Stable</th>
              </tr>
            </thead>
            <tbody>
              @foreach($loan->condition_reports as $cr)
                <tr>
                  <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $cr->report_type)) }}</span></td>
                  <td>{{ \Carbon\Carbon::parse($cr->examination_date)->format('Y-m-d') }}</td>
                  <td>{{ $cr->examiner_display_name ?: $cr->examiner_name ?: '-' }}</td>
                  <td>
                    @php
                      $condColour = match($cr->overall_condition) {
                        'excellent' => 'success',
                        'good' => 'info',
                        'fair' => 'warning',
                        'poor' => 'danger',
                        default => 'secondary',
                      };
                    @endphp
                    <span class="badge bg-{{ $condColour }}">{{ ucfirst($cr->overall_condition) }}</span>
                  </td>
                  <td>{{ $cr->has_damage ? 'Yes' : 'No' }}</td>
                  <td>{{ $cr->condition_stable ? 'Yes' : 'No' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- Facility Reports --}}
  @if(count($loan->facility_reports))
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-building me-2"></i>Facility Reports ({{ count($loan->facility_reports) }})</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-sm mb-0">
            <thead>
              <tr>
                <th>Venue</th>
                <th>Assessment Date</th>
                <th>Climate Control</th>
                <th>Security (24h / CCTV / Alarm)</th>
                <th>Fire Suppression</th>
                <th>Rating</th>
                <th>Approved</th>
              </tr>
            </thead>
            <tbody>
              @foreach($loan->facility_reports as $fr)
                <tr>
                  <td>{{ $fr->venue_name }}</td>
                  <td>{{ $fr->assessment_date ? \Carbon\Carbon::parse($fr->assessment_date)->format('Y-m-d') : '-' }}</td>
                  <td>{{ $fr->has_climate_control ? 'Yes' : 'No' }}</td>
                  <td>
                    {{ $fr->has_24hr_security ? 'Yes' : 'No' }} /
                    {{ $fr->has_cctv ? 'Yes' : 'No' }} /
                    {{ $fr->has_alarm_system ? 'Yes' : 'No' }}
                  </td>
                  <td>{{ $fr->has_fire_suppression ? ($fr->fire_suppression_type ?: 'Yes') : 'No' }}</td>
                  <td>
                    @php
                      $ratingColour = match($fr->overall_rating) {
                        'excellent' => 'success',
                        'good' => 'info',
                        'acceptable' => 'warning',
                        'poor', 'unacceptable' => 'danger',
                        default => 'secondary',
                      };
                    @endphp
                    <span class="badge bg-{{ $ratingColour }}">{{ ucfirst($fr->overall_rating ?? 'N/A') }}</span>
                  </td>
                  <td>{{ $fr->approved ? 'Yes' : 'No' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- Shipments --}}
  @if(count($loan->shipments))
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Shipments ({{ count($loan->shipments) }})</h5>
      </div>
      <div class="card-body">
        @foreach($loan->shipments as $shipment)
          <div class="border rounded p-3 mb-3">
            <div class="row">
              <div class="col-md-6">
                <strong>Type:</strong> {{ ucfirst($shipment->shipment_type) }}<br>
                <strong>Courier:</strong> {{ $shipment->courier_name ?: '-' }}<br>
                <strong>Tracking #:</strong> {{ $shipment->tracking_number ?: '-' }}<br>
                <strong>Status:</strong>
                <span class="badge bg-{{ $shipment->status === 'delivered' ? 'success' : ($shipment->status === 'in_transit' ? 'primary' : 'secondary') }}">
                  {{ ucfirst(str_replace('_', ' ', $shipment->status)) }}
                </span>
              </div>
              <div class="col-md-6">
                <strong>Scheduled Pickup:</strong> {{ $shipment->scheduled_pickup ? \Carbon\Carbon::parse($shipment->scheduled_pickup)->format('Y-m-d H:i') : '-' }}<br>
                <strong>Actual Pickup:</strong> {{ $shipment->actual_pickup ? \Carbon\Carbon::parse($shipment->actual_pickup)->format('Y-m-d H:i') : '-' }}<br>
                <strong>Scheduled Delivery:</strong> {{ $shipment->scheduled_delivery ? \Carbon\Carbon::parse($shipment->scheduled_delivery)->format('Y-m-d H:i') : '-' }}<br>
                <strong>Total Cost:</strong> {{ $shipment->total_cost ? ($shipment->cost_currency ?? 'ZAR') . ' ' . number_format($shipment->total_cost, 2) : '-' }}
              </div>
            </div>
            @if(count($shipment->events))
              <hr>
              <h6>Tracking Events</h6>
              <ul class="list-unstyled mb-0">
                @foreach($shipment->events as $event)
                  <li class="mb-1">
                    <i class="fas fa-circle text-primary me-1" style="font-size:0.5rem; vertical-align:middle;"></i>
                    <strong>{{ \Carbon\Carbon::parse($event->event_time)->format('Y-m-d H:i') }}</strong>
                    {{ $event->location ? '(' . $event->location . ')' : '' }}
                    - {{ $event->description ?: $event->event_type }}
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        @endforeach
      </div>
    </div>
  @endif

  {{-- Costs --}}
  @if(count($loan->costs))
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Costs ({{ count($loan->costs) }})</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-sm mb-0">
            <thead>
              <tr>
                <th>Type</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Vendor</th>
                <th>Invoice #</th>
                <th>Paid</th>
                <th>Paid By</th>
              </tr>
            </thead>
            <tbody>
              @php $totalCost = 0; @endphp
              @foreach($loan->costs as $cost)
                @php $totalCost += $cost->amount; @endphp
                <tr>
                  <td>{{ ucfirst(str_replace('_', ' ', $cost->cost_type)) }}</td>
                  <td>{{ $cost->description ?: '-' }}</td>
                  <td>{{ ($cost->currency ?? 'ZAR') . ' ' . number_format($cost->amount, 2) }}</td>
                  <td>{{ $cost->vendor ?: '-' }}</td>
                  <td>{{ $cost->invoice_number ?: '-' }}</td>
                  <td>
                    @if($cost->paid)
                      <span class="badge bg-success">Paid</span>
                      @if($cost->paid_date) {{ $cost->paid_date }} @endif
                    @else
                      <span class="badge bg-warning text-dark">Unpaid</span>
                    @endif
                  </td>
                  <td>{{ $cost->paid_by ? ucfirst($cost->paid_by) : '-' }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr class="table-secondary fw-bold">
                <td colspan="2">Total</td>
                <td>ZAR {{ number_format($totalCost, 2) }}</td>
                <td colspan="4"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- Status History --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-history me-2"></i>Status History</h5>
    </div>
    <div class="card-body">
      @if(count($loan->status_history))
        <div class="timeline">
          @foreach($loan->status_history as $entry)
            <div class="d-flex mb-3">
              <div class="me-3 text-center" style="min-width: 80px;">
                <div class="small text-muted">{{ \Carbon\Carbon::parse($entry->created_at)->format('Y-m-d') }}</div>
                <div class="small text-muted">{{ \Carbon\Carbon::parse($entry->created_at)->format('H:i') }}</div>
              </div>
              <div class="border-start border-3 border-primary ps-3">
                <div>
                  @if($entry->from_status)
                    <span class="badge bg-{{ \AhgLoan\Services\LoanService::getStatusColour($entry->from_status) }}">
                      {{ ucwords(str_replace('_', ' ', $entry->from_status)) }}
                    </span>
                    <i class="fas fa-arrow-right mx-1"></i>
                  @endif
                  <span class="badge bg-{{ \AhgLoan\Services\LoanService::getStatusColour($entry->to_status) }}">
                    {{ ucwords(str_replace('_', ' ', $entry->to_status)) }}
                  </span>
                </div>
                @if($entry->changed_by_name)
                  <div class="small text-muted mt-1">
                    <i class="fas fa-user me-1"></i>{{ $entry->changed_by_name }}
                  </div>
                @endif
                @if($entry->comment)
                  <div class="small mt-1 fst-italic">{{ $entry->comment }}</div>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      @else
        <p class="text-muted mb-0">No status history recorded.</p>
      @endif
    </div>
  </div>

  {{-- Transition Modal --}}
  <div class="modal fade" id="transitionModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="{{ route('loan.transition', $loan->id) }}">
          @csrf
          <input type="hidden" name="new_status" id="transition-new-status">
          <div class="modal-header">
            <h5 class="modal-title">Change Loan Status</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="transition-comment" class="form-label">Comment (optional) <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="comment" id="transition-comment" class="form-control" rows="3" placeholder="Reason for status change..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn atom-btn-outline-success">Confirm Transition</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- Extend Modal --}}
  <div class="modal fade" id="extendModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="{{ route('loan.extend', $loan->id) }}">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Extend Loan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="extend-date" class="form-label">New End Date <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <input type="date" name="new_end_date" id="extend-date" class="form-control" required
                     value="{{ $loan->end_date ? \Carbon\Carbon::parse($loan->end_date)->addMonths(3)->format('Y-m-d') : '' }}">
            </div>
            <div class="mb-3">
              <label for="extend-reason" class="form-label">Reason <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <textarea name="reason" id="extend-reason" class="form-control" rows="3" required placeholder="Reason for extension..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn atom-btn-white">Extend Loan</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- Return Modal --}}
  <div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="{{ route('loan.return', $loan->id) }}">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Record Loan Return</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="return-date" class="form-label">Return Date <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <input type="date" name="return_date" id="return-date" class="form-control" required
                     value="{{ now()->format('Y-m-d') }}">
            </div>
            <div class="mb-3">
              <label for="return-notes" class="form-label">Notes <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="notes" id="return-notes" class="form-control" rows="3" placeholder="Return notes..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn atom-btn-outline-success">Record Return</button>
          </div>
        </form>
      </div>
    </div>
  </div>

@endsection

@push('js')
<script>
  // Object search autocomplete
  (function() {
    const searchInput = document.getElementById('object-search');
    const resultsDiv = document.getElementById('object-search-results');
    const objectIdInput = document.getElementById('object-id');
    let debounceTimer;

    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
      clearTimeout(debounceTimer);
      const q = this.value.trim();
      if (q.length < 2) {
        resultsDiv.style.display = 'none';
        return;
      }
      debounceTimer = setTimeout(function() {
        fetch('{{ route("loan.search-objects") }}?q=' + encodeURIComponent(q))
          .then(r => r.json())
          .then(data => {
            resultsDiv.innerHTML = '';
            if (data.results && data.results.length) {
              data.results.forEach(function(item) {
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action py-1 px-2 small';
                a.textContent = (item.identifier ? '[' + item.identifier + '] ' : '') + (item.title || '[Untitled]');
                a.addEventListener('click', function(e) {
                  e.preventDefault();
                  objectIdInput.value = item.id;
                  searchInput.value = a.textContent;
                  resultsDiv.style.display = 'none';
                });
                resultsDiv.appendChild(a);
              });
              resultsDiv.style.display = 'block';
            } else {
              resultsDiv.style.display = 'none';
            }
          });
      }, 300);
    });

    document.addEventListener('click', function(e) {
      if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
        resultsDiv.style.display = 'none';
      }
    });
  })();
</script>
@endpush
