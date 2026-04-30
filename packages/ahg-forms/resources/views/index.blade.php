@extends('theme::layouts.1col')

@section('title', 'Form Templates')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-wpforms me-2"></i>{{ __('Form Templates') }}</h1>
            <p class="text-muted">Manage configurable metadata entry forms</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('forms.template.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> {{ __('New Template') }}
            </a>
            <a href="{{ route('forms.library') }}" class="btn btn-outline-secondary">
                <i class="fas fa-upload me-1"></i> {{ __('Import') }}
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h4>{{ array_sum((array) ($stats['templates_by_type'] ?? [0])) }}</h4>
                    <p class="mb-0">Total Templates</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h4>{{ $stats['active_assignments'] ?? 0 }}</h4>
                    <p class="mb-0">Active Assignments</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h4>{{ $stats['pending_drafts'] ?? 0 }}</h4>
                    <p class="mb-0">Pending Drafts</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h4>{{ $stats['submissions_30_days'] ?? 0 }}</h4>
                    <p class="mb-0">Submissions (30 days)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-list me-2"></i>{{ __('Templates') }}</h5>
                    <p class="card-text">Create and manage form templates with drag-drop field builder.</p>
                    <a href="{{ route('forms.templates') }}" class="btn btn-outline-primary">Manage Templates</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-link me-2"></i>{{ __('Assignments') }}</h5>
                    <p class="card-text">Assign templates to repositories and description levels.</p>
                    <a href="{{ route('forms.assignments') }}" class="btn btn-outline-primary">Manage Assignments</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-book me-2"></i>{{ __('Library') }}</h5>
                    <p class="card-text">Pre-built templates: ISAD-G, Dublin Core, Accession forms.</p>
                    <a href="{{ route('forms.library') }}" class="btn btn-outline-primary">Browse Library</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Templates List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">{{ __('Form Templates') }}</h5>
        </div>
        <div class="card-body p-0">
            @if($templates->isEmpty())
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No form templates found. Create one or import from the library.</p>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Fields') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($templates as $template)
                            @php
                                $fieldCount = \Illuminate\Support\Facades\DB::table('ahg_form_field')
                                    ->where('template_id', $template->id)
                                    ->count();
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ e($template->name) }}</strong>
                                    @if(!empty($template->is_system))
                                        <span class="badge bg-secondary">{{ __('System') }}</span>
                                    @endif
                                    @if(!empty($template->is_default))
                                        <span class="badge bg-primary">{{ __('Default') }}</span>
                                    @endif
                                    @if($template->description)
                                        <br><small class="text-muted">{{ e($template->description) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ e($template->form_type) }}</span>
                                </td>
                                <td>{{ $fieldCount }}</td>
                                <td>
                                    @if(!empty($template->is_active))
                                        <span class="badge bg-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge bg-warning">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('forms.builder', $template->id) }}"
                                           class="btn btn-outline-primary" title="{{ __('Edit Fields') }}">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="{{ route('forms.template.create', ['edit' => $template->id]) }}"
                                           class="btn btn-outline-secondary" title="{{ __('Settings') }}">
                                            <i class="fas fa-cog"></i>
                                        </a>
                                        <form method="POST" action="{{ route('forms.index') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="action" value="duplicate">
                                            <input type="hidden" name="id" value="{{ $template->id }}">
                                            <button type="submit" class="btn btn-outline-info btn-sm" title="{{ __('Clone') }}">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </form>
                                        <a href="{{ route('forms.index', ['export' => $template->id]) }}"
                                           class="btn btn-outline-success" title="{{ __('Export JSON') }}">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        @if(empty($template->is_system))
                                            <form method="POST" action="{{ route('forms.index') }}" class="d-inline"
                                                  onsubmit="return confirm('Delete this template?')">
                                                @csrf
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="{{ $template->id }}">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ __('Delete') }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
