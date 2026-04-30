@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-walking me-2"></i>Walk-in Researcher Registration</h1>@endsection
@section('content')
<div class="card mb-3">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">{{ __('Select Reading Room') }}</h5></div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Reading Room <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="room_id" class="form-select">
                    <option value="">-- Select Room --</option>
                    @foreach($rooms as $room)
                    <option value="{{ $room->id }}" {{ $roomId == $room->id ? 'selected' : '' }}>{{ e($room->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-arrow-right me-1"></i>Go</button>
            </div>
        </form>
    </div>
</div>

@if($roomId && $currentRoom)
<div class="row">
    <div class="col-md-7">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
                <h5 class="mb-0">Current Visitors - {{ e($currentRoom->name) }}</h5>
                <span class="badge bg-primary">{{ count($currentWalkIns) }} visitor(s)</span>
            </div>
            <div class="card-body p-0">
                @if(count($currentWalkIns) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Organization') }}</th>
                                <th>{{ __('Purpose') }}</th>
                                <th>{{ __('Checked In') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($currentWalkIns as $visitor)
                            <tr>
                                <td>{{ e($visitor->first_name) }} {{ e($visitor->last_name) }}</td>
                                <td>{{ e($visitor->organization ?? '-') }}</td>
                                <td>{{ e($visitor->purpose ?? '-') }}</td>
                                <td>{{ $visitor->check_in_time ? substr($visitor->check_in_time, 0, 5) : '-' }}</td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="form_action" value="checkout">
                                        <input type="hidden" name="visitor_id" value="{{ $visitor->id }}">
                                        <input type="hidden" name="room_id" value="{{ $roomId }}">
                                        <button type="submit" class="btn atom-btn-outline-danger btn-sm" title="{{ __('Check Out') }}"><i class="fas fa-sign-out-alt me-1"></i>Check Out</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center text-muted py-3">No visitors currently checked in.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Register Walk-in Visitor</h5></div>
            <div class="card-body">
                <form method="POST">
                    @csrf
                    <input type="hidden" name="form_action" value="register">
                    <input type="hidden" name="room_id" value="{{ $roomId }}">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="email" class="form-control" name="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="text" class="form-control" name="phone">
                    </div>
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="form-label">ID Type <span class="badge bg-secondary ms-1">Optional</span></label>
                            <select name="id_type" class="form-select">
                                <option value="">-- Select --</option>
                                @foreach(\Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy', 'id_type')->where('is_active', 1)->orderBy('sort_order')->get() as $idType)
                                    <option value="{{ $idType->code }}">{{ $idType->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-7 mb-3">
                            <label class="form-label">ID Number <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" class="form-control" name="id_number">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Organization / Institution <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="text" class="form-control" name="organization">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purpose of Visit <span class="badge bg-secondary ms-1">Optional</span></label>
                        <select name="purpose" class="form-select">
                            <option value="research">{{ __('Research') }}</option>
                            <option value="genealogy">{{ __('Genealogy') }}</option>
                            <option value="academic">{{ __('Academic') }}</option>
                            <option value="personal">{{ __('Personal Interest') }}</option>
                            <option value="professional">{{ __('Professional') }}</option>
                            <option value="other">{{ __('Other') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Research Topic <span class="badge bg-danger ms-1">Required</span></label>
                        <input type="text" class="form-control" name="research_topic">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="rules_acknowledged" id="rulesAck" value="1" required>
                        <label class="form-check-label" for="rulesAck">Visitor acknowledges reading room rules <span class="badge bg-secondary ms-1">Optional</span></label>
                    </div>
                    <button type="submit" class="btn atom-btn-outline-success w-100"><i class="fas fa-user-check me-1"></i>Register Visitor</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<div class="card mt-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Walk-In Visitors</h6></div>
    <div class="card-body">
        <p class="text-muted mb-2">Walk-in visitors are unregistered users who need quick access to the reading room.</p>
        <ul class="mb-0 small">
            <li><i class="fas fa-times-circle text-danger me-1"></i>They do not have a researcher account</li>
            <li><i class="fas fa-times-circle text-danger me-1"></i>Cannot request materials in advance</li>
            <li><i class="fas fa-eye text-info me-1"></i>Limited to browsing open-access materials</li>
            <li><i class="fas fa-user-plus text-success me-1"></i>Can be converted to registered researchers if needed</li>
        </ul>
    </div>
</div>
@endsection
