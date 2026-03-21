@extends('theme::layouts.1col')

@section('title', 'Manage jobs')
@section('body-class', 'browse jobs')

@section('title-block')
  <h1>Manage jobs</h1>
@endsection

@section('content')
  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>Start date</th>
            <th>End date</th>
            <th>Job name</th>
            <th>Job status</th>
            <th>Info</th>
            <th>User</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $job)
            <tr>
              <td>{{ $job['created_at'] ? \Carbon\Carbon::parse($job['created_at'])->format('Y-m-d g:i A') : '' }}</td>
              <td>{{ $job['completed_at'] ? \Carbon\Carbon::parse($job['completed_at'])->format('Y-m-d g:i A') : '' }}</td>
              <td>
                <a href="{{ route('job.show', $job['id']) }}">
                  {{ $job['name'] ?: '[Unknown]' }}
                </a>
              </td>
              <td>
                @if($job['status_id'] == 184)
                  <span class="text-success">Completed</span>
                @elseif($job['status_id'] == 185)
                  <span class="text-danger">Error</span>
                @else
                  <span class="text-primary">In progress</span>
                @endif
              </td>
              <td>
                @if(!empty($job['output']))
                  <a href="{{ route('job.show', $job['id']) }}">Full report</a>
                @endif
              </td>
              <td>{{ $job['user_name'] ?: $job['username'] ?: '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="alert alert-info">No jobs found.</div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])

  <section class="actions mb-3">
    <a href="{{ route('job.browse', request()->query()) }}" class="btn atom-btn-outline-light">
      Refresh
    </a>
    <a href="{{ route('job.browse', array_merge(request()->query(), ['autorefresh' => 1])) }}" class="btn atom-btn-outline-light">
      Auto refresh
    </a>
    <a href="{{ route('job.export-csv') }}" class="btn atom-btn-outline-light">
      Export history CSV
    </a>
  </section>

  @if(request('autorefresh'))
    <script>setTimeout(function() { location.reload(); }, 5000);</script>
  @endif

@push('css')
<style>
.table thead th {
  background-color: var(--ahg-primary, #005837);
  color: var(--ahg-card-header-text, #fff);
  border-color: var(--ahg-primary, #005837);
}
</style>
@endpush
@endsection
