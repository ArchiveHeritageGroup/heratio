@extends('theme::layouts.1col')

@section('title', 'Feedback')
@section('body-class', 'browse feedback')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-comments me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Feedback Management</h1>
    </div>
  </div>

  <div class="row">
    {{-- Sidebar --}}
    <div class="col-md-3">
      <div class="card mb-3">
        <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-filter me-1"></i> Filter
        </div>
        <div class="list-group list-group-flush">
          <a href="{{ route('feedback.browse', array_merge(request()->except('status', 'page'), ['status' => 'all'])) }}"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $status === 'all' ? 'active' : '' }}">
            All Feedback
            <span class="badge bg-secondary rounded-pill">{{ $totalCount }}</span>
          </a>
          <a href="{{ route('feedback.browse', array_merge(request()->except('status', 'page'), ['status' => 'pending'])) }}"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $status === 'pending' ? 'active' : '' }}">
            Pending
            <span class="badge bg-warning text-dark rounded-pill">{{ $pendingCount }}</span>
          </a>
          <a href="{{ route('feedback.browse', array_merge(request()->except('status', 'page'), ['status' => 'completed'])) }}"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $status === 'completed' ? 'active' : '' }}">
            Completed
            <span class="badge bg-success rounded-pill">{{ $completedCount }}</span>
          </a>
        </div>
      </div>

      <a href="{{ route('feedback.general') }}" class="btn atom-btn-white w-100">
        <i class="fas fa-plus me-1"></i> Add General Feedback
      </a>
    </div>

    {{-- Main content --}}
    <div class="col-md-9">
      {{-- Sort controls --}}
      <div class="d-flex flex-wrap gap-2 mb-3 justify-content-end">
        <div class="btn-group btn-group-sm" role="group" aria-label="Sort options">
          <span class="btn atom-btn-white disabled">Sort by:</span>
          <a href="{{ route('feedback.browse', array_merge(request()->except('sort', 'page'), ['sort' => 'nameUp'])) }}"
             class="btn atom-btn-white {{ $sort === 'nameUp' ? 'active' : '' }}">
            Name <i class="fas fa-arrow-up"></i>
          </a>
          <a href="{{ route('feedback.browse', array_merge(request()->except('sort', 'page'), ['sort' => 'nameDown'])) }}"
             class="btn atom-btn-white {{ $sort === 'nameDown' ? 'active' : '' }}">
            Name <i class="fas fa-arrow-down"></i>
          </a>
          <a href="{{ route('feedback.browse', array_merge(request()->except('sort', 'page'), ['sort' => 'dateUp'])) }}"
             class="btn atom-btn-white {{ $sort === 'dateUp' ? 'active' : '' }}">
            Date <i class="fas fa-arrow-up"></i>
          </a>
          <a href="{{ route('feedback.browse', array_merge(request()->except('sort', 'page'), ['sort' => 'dateDown'])) }}"
             class="btn atom-btn-white {{ $sort === 'dateDown' ? 'active' : '' }}">
            Date <i class="fas fa-arrow-down"></i>
          </a>
        </div>
      </div>

      @if($pager->getNbResults())
        <div class="table-responsive mb-3">
          <table class="table table-bordered table-hover mb-0">
            <thead>
              <tr style="background:var(--ahg-primary);color:#fff">
                <th style="width:40px">#</th>
                <th>Subject/Record</th>
                <th>Type</th>
                <th>Remarks</th>
                <th>Contact</th>
                <th>Date</th>
                <th style="width:120px">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($pager->getResults() as $i => $row)
                @php
                  $rowNum = ($pager->getPage() - 1) * $pager->getMaxPerPage() + $i + 1;

                  // Type badge mapping
                  $typeMap = [
                      0 => ['label' => 'General',    'class' => 'bg-secondary'],
                      1 => ['label' => 'Error',      'class' => 'bg-danger'],
                      2 => ['label' => 'Suggestion', 'class' => 'bg-info text-dark'],
                      3 => ['label' => 'Correction', 'class' => 'bg-primary'],
                      4 => ['label' => 'Assistance', 'class' => 'bg-warning text-dark'],
                  ];
                  $typeId = (int) ($row['feed_type_id'] ?? 0);
                  $typeInfo = $typeMap[$typeId] ?? $typeMap[0];

                  // Status badge
                  $statusVal = $row['status'] ?? 'pending';
                  $statusBadge = $statusVal === 'completed'
                      ? '<span class="badge bg-success">Completed</span>'
                      : '<span class="badge bg-warning text-dark">Pending</span>';
                @endphp
                <tr>
                  <td class="text-muted">{{ $rowNum }}</td>
                  <td>
                    <a href="{{ route('feedback.edit', $row['id']) }}">
                      {{ $row['name'] ?: '[Untitled]' }}
                    </a>
                    @if(!empty($row['parent_id']))
                      <br><a href="{{ url('/' . $row['parent_id']) }}" class="small text-muted" title="View related record">
                        <i class="fas fa-link me-1"></i>{{ $row['parent_id'] }}
                      </a>
                    @endif
                    <br>{!! $statusBadge !!}
                  </td>
                  <td>
                    <span class="badge {{ $typeInfo['class'] }}">{{ $typeInfo['label'] }}</span>
                  </td>
                  <td title="{{ $row['remarks'] ?? '' }}">
                    {{ \Illuminate\Support\Str::limit($row['remarks'] ?? '', 60) }}
                  </td>
                  <td>
                    @if(!empty($row['feed_name']) || !empty($row['feed_surname']))
                      {{ $row['feed_name'] ?? '' }} {{ $row['feed_surname'] ?? '' }}
                    @endif
                    @if(!empty($row['feed_email']))
                      <br><small class="text-muted">{{ $row['feed_email'] }}</small>
                    @endif
                  </td>
                  <td>
                    @if(!empty($row['created_at']))
                      {{ \Carbon\Carbon::parse($row['created_at'])->format('d M Y') }}
                    @endif
                    @if(!empty($row['completed_at']))
                      <br><small class="text-success">
                        <i class="fas fa-check me-1"></i>{{ \Carbon\Carbon::parse($row['completed_at'])->format('d M Y') }}
                      </small>
                    @endif
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <a href="{{ route('feedback.edit', $row['id']) }}" class="btn btn-sm atom-btn-white" title="Edit">
                        <i class="fas fa-edit"></i>
                      </a>
                      <form method="POST" action="{{ route('feedback.destroy', $row['id']) }}"
                            onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                        @csrf
                        <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="Delete">
                          <i class="fas fa-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @include('ahg-core::components.pager', ['pager' => $pager])
      @else
        <div class="alert alert-info">
          <i class="fas fa-info-circle me-2"></i>No feedback items found for the selected filter.
        </div>
      @endif
    </div>
  </div>
@endsection
