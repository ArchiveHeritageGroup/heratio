@extends('theme::layouts.1col')

@section('title', 'Preservation Policies')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
    <div class="col-md-3">
        @include('ahg-preservation::_menu')
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h1 class="mb-0"><i class="fas fa-clipboard-list"></i> Preservation Policies</h1>
        </div>
        <p class="text-muted mb-3">Active and inactive preservation policies</p>

        <div class="card mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Schedule</th>
                                <th>Last Run</th>
                                <th>Next Run</th>
                                <th>Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($policies as $policy)
                            <tr>
                                <td>{{ $policy->id }}</td>
                                <td class="fw-bold">{{ $policy->name }}</td>
                                <td><small class="text-muted">{{ Str::limit($policy->description ?? '', 60) }}</small></td>
                                <td><span class="badge bg-secondary">{{ $policy->policy_type ?? '' }}</span></td>
                                <td><code class="small">{{ $policy->schedule_cron ?? '-' }}</code></td>
                                <td class="text-nowrap"><small>{{ $policy->last_run_at ?? 'Never' }}</small></td>
                                <td class="text-nowrap"><small>{{ $policy->next_run_at ?? '-' }}</small></td>
                                <td>
                                    @if($policy->is_active ?? false)
                                        <span class="badge bg-success"><i class="fas fa-check-circle"></i> Active</span>
                                    @else
                                        <span class="badge bg-secondary"><i class="fas fa-pause-circle"></i> Inactive</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted py-3">No preservation policies defined</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- CLI Commands --}}
        <div class="card">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <i class="fas fa-terminal me-2"></i>CLI Commands
            </div>
            <div class="card-body">
                <p>Run fixity checks from command line:</p>
                <pre class="bg-dark text-light p-3 rounded">
# Check 100 objects not verified in 7+ days
php artisan preservation:fixity

# Check all objects with verbose output
php artisan preservation:fixity --all --verbose

# Custom limits
php artisan preservation:fixity --limit=500 --min-age=30</pre>

                <p class="mt-3">Add to crontab for scheduled runs:</p>
                <pre class="bg-dark text-light p-3 rounded">
# Daily fixity check at 2am
0 2 * * * cd /usr/share/nginx/heratio && php artisan preservation:fixity >> /var/log/heratio/fixity.log 2>&1</pre>
            </div>
        </div>
    </div>
</div>
@endsection
