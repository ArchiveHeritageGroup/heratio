@extends('theme::layouts.1col')

@section('title', __('KBART refresh log'))

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ __('KBART refresh log') }}</h1>
        <div>
            <a href="{{ route('library.kbart-remote') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back to feeds') }}</a>
        </div>
    </div>

    <p class="text-muted">{{ __('Per-fetch history for every registered KBART feed. Status, diff counts, and sample of added/removed titles are recorded each time the scheduler runs.') }}</p>

    @if ($rows->isEmpty())
        <div class="alert alert-info">{{ __('No KBART fetches recorded yet. The scheduler runs daily at 01:00, or trigger a manual refresh from the feeds page.') }}</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>{{ __('When') }}</th>
                        <th>{{ __('Feed') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Rows') }}</th>
                        <th class="text-end">{{ __('+Added') }}</th>
                        <th class="text-end">{{ __('-Removed') }}</th>
                        <th class="text-end">{{ __('Changed') }}</th>
                        <th>{{ __('Sample / error') }}</th>
                        <th class="text-end">{{ __('Elapsed') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td><small>{{ $r->created_at }}</small></td>
                        <td>
                            <strong>{{ $r->feed_name ?? '#'.$r->feed_id }}</strong><br>
                            <small class="text-muted">{{ $r->feed_url }}</small>
                        </td>
                        <td>
                            <span class="badge bg-{{ $r->status === 'success' ? 'success' : ($r->status === 'skipped' ? 'secondary' : 'danger') }}">{{ $r->status }}</span>
                        </td>
                        <td class="text-end">{{ $r->row_count }}</td>
                        <td class="text-end text-success">{{ $r->added > 0 ? '+'.$r->added : 0 }}</td>
                        <td class="text-end text-danger">{{ $r->removed > 0 ? '-'.$r->removed : 0 }}</td>
                        <td class="text-end text-warning">{{ $r->changed }}</td>
                        <td>
                            @if ($r->error)
                                <small class="text-danger">{{ \Illuminate\Support\Str::limit($r->error, 120) }}</small>
                            @elseif ($r->diff_sample)
                                @php $sample = json_decode($r->diff_sample, true); @endphp
                                @if (is_array($sample) && !empty($sample))
                                    <small>
                                        @foreach (array_slice($sample, 0, 3) as $s)
                                            <span class="badge bg-light text-dark border me-1">{{ $s['op'] }}: {{ \Illuminate\Support\Str::limit($s['title'] ?? $s['id'], 30) }}</span>
                                        @endforeach
                                    </small>
                                @endif
                            @endif
                        </td>
                        <td class="text-end"><small>{{ $r->elapsed_ms }} ms</small></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{ $rows->links() }}
    @endif
</div>
@endsection
