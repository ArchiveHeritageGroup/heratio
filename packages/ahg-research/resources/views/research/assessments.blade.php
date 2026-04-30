@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'assessments'])@endsection
@section('title', 'Source Assessments')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Source Assessments</li>
    </ol>
</nav>

<h1 class="h2 mb-4"><i class="fas fa-clipboard-check text-primary me-2"></i>Source Assessments</h1>

@if(!empty($assessments))
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Record') }}</th>
                    <th>{{ __('Source Type') }}</th>
                    <th>{{ __('Form') }}</th>
                    <th>{{ __('Completeness') }}</th>
                    <th>{{ __('Trust Score') }}</th>
                    <th>{{ __('Assessed By') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($assessments as $a)
                <tr>
                    <td>
                        @if($a->object_slug)
                            <a href="{{ url('/' . $a->object_slug) }}">{{ e($a->object_title ?: 'Untitled') }}</a>
                        @else
                            {{ e($a->object_title ?: '#' . $a->object_id) }}
                        @endif
                    </td>
                    <td><span class="badge bg-{{ $a->source_type === 'primary' ? 'success' : ($a->source_type === 'secondary' ? 'info' : 'secondary') }}">{{ ucfirst($a->source_type) }}</span></td>
                    <td>{{ ucfirst(str_replace('_', ' ', $a->source_form ?? '')) }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $a->completeness ?? '')) }}</td>
                    <td>
                        @php $score = (int) ($a->trust_score ?? 0); @endphp
                        @if($score > 0)
                            <span class="fw-bold text-{{ $score >= 70 ? 'success' : ($score >= 40 ? 'warning' : 'danger') }}">{{ $score }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td><small>{{ e($a->researcher_name ?? '') }}</small></td>
                    <td><small>{{ $a->assessed_at ? date('M j, Y', strtotime($a->assessed_at)) : '' }}</small></td>
                    <td>
                        @if($a->object_slug)
                            <a href="{{ route('io.research.assessment', $a->object_slug) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="text-center py-5">
    <i class="fas fa-clipboard-check fa-4x text-muted mb-3 opacity-50"></i>
    <h4 class="text-muted">{{ __('No assessments yet') }}</h4>
    <p class="text-muted">Assess sources from the record detail page under Research Tools → Source Assessment.</p>
</div>
@endif
@endsection
