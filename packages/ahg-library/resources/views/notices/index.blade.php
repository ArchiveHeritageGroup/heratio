@extends('theme::layouts.1col')
@section('title', __('Notice Templates'))
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <div>
            <h2 class="mb-0"><i class="fas fa-envelope-open-text me-2"></i>{{ __('Notice Templates') }}</h2>
            <span class="badge bg-primary mt-1">{{ __('Circulation') }}</span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <p class="text-muted">
        {{ __('These templates drive the tiered overdue notices sent by the daily ahg:library-overdue-notices command and the hold-ready notice. Edit the subject, body and the number of days overdue that triggers each tier.') }}
    </p>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Notice Type') }}</th>
                        <th>{{ __('Channel') }}</th>
                        <th>{{ __('Subject') }}</th>
                        <th class="text-end">{{ __('Trigger (days overdue)') }}</th>
                        <th class="text-center">{{ __('Active') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $t)
                        <tr>
                            <td><code>{{ e($t->notice_type) }}</code></td>
                            <td>{{ e($t->channel) }}</td>
                            <td>{{ e(\Illuminate\Support\Str::limit($t->subject, 60)) }}</td>
                            <td class="text-end">{{ (int) $t->trigger_days_overdue }}</td>
                            <td class="text-center">
                                @if($t->is_active)
                                    <span class="badge bg-success">{{ __('Yes') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('library.notice-templates.edit', $t->id) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-pen"></i> {{ __('Edit') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted text-center py-3">
                                {{ __('No notice templates found. Run the library migrations to seed the defaults.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3 small text-muted">
        {{ __('Available placeholder tokens:') }}
        @foreach($tokens as $tok)<code class="me-1">&#123;&#123;{{ $tok }}&#125;&#125;</code>@endforeach
    </div>
</div>
@endsection
