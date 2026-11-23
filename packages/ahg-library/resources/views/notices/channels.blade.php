@extends('theme::layouts.1col')
@section('title', __('Notice Channels'))
@section('content')
<div class="container py-4">
    <h2 class="mb-3"><i class="fas fa-bell me-2"></i>{{ __('Notice Channels') }}</h2>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-warning">{{ session('error') }}</div>@endif

    @unless($smsReady)
        <div class="alert alert-warning">{{ __('No SMS gateway is configured, so SMS notices are logged as skipped and the next channel is tried.') }}</div>
    @endunless

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Print queue') }}</h5></div>
        <div class="card-body">
            <p>{{ __('Notices waiting for a print run: :n', ['n' => $queued]) }}
                @if($queued > $maxRun)<span class="text-muted">{{ __('Each run takes up to :max notices.', ['max' => $maxRun]) }}</span>@endif
            </p>
            <form method="POST" action="{{ route('library.notice-channel.run-create') }}">
                @csrf
                <button class="btn btn-primary" @disabled($queued === 0)><i class="fas fa-print me-1"></i>{{ __('Create print run') }}</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead><tr><th>{{ __('Run') }}</th><th>{{ __('Created') }}</th><th class="text-end">{{ __('Notices') }}</th><th class="text-end">{{ __('PDF') }}</th></tr></thead>
                <tbody>
                @forelse($runs as $run)
                    <tr>
                        <td>{{ $run->id }}</td><td>{{ $run->created_at }}</td><td class="text-end">{{ $run->item_count }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('library.notice-channel.run-pdf', $run->id) }}"><i class="fas fa-file-pdf me-1"></i>{{ __('Download') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted text-center py-3">{{ __('No print runs yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ __('Notice log by channel') }}</h5></div>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead><tr><th>{{ __('Channel') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Notices') }}</th></tr></thead>
                <tbody>
                @forelse($summary as $s)
                    <tr><td>{{ $channels[$s->channel] ?? $s->channel }}</td><td>{{ $statuses[$s->status] ?? $s->status }}</td><td class="text-end">{{ $s->total }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted text-center py-3">{{ __('No notices logged yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
