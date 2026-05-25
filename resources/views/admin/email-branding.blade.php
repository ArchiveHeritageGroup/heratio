@extends('theme::layouts.1col')
@section('title', __('Email branding'))
@section('body-class', 'admin')

@section('content')
<div class="container my-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0"><i class="bi bi-envelope-paper me-2"></i>{{ __('Per-tenant email branding') }}</h1>
        <a href="/admin/settings" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>{{ __('Back to settings') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
        </div>
    @endif

    <p class="text-muted">
        {{ __('Customise the logo, accent colours, footer text and sender identity used on outgoing emails for each tenant. Global Heratio branding is used when no row is set.') }}
    </p>

    <form method="get" class="mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label for="tenant_id" class="form-label">{{ __('Tenant') }}</label>
                <select name="tenant_id" id="tenant_id" class="form-select" onchange="this.form.submit()">
                    @forelse ($tenants as $t)
                        <option value="{{ $t->id }}" @selected($t->id == $selectedTenantId)>
                            {{ $t->name }} ({{ $t->code }})@if ($t->is_default) - {{ __('default') }}@endif
                        </option>
                    @empty
                        <option value="0">{{ __('No tenants configured') }}</option>
                    @endforelse
                </select>
            </div>
        </div>
    </form>

    @if ($selectedTenantId > 0)
        <form method="post" action="{{ route('admin.email.branding.save') }}">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $selectedTenantId }}">

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-image me-2"></i>{{ __('Logo') }}</div>
                <div class="card-body">
                    <label class="form-label" for="logo_url">{{ __('Logo URL') }}</label>
                    <input type="url" id="logo_url" name="logo_url" class="form-control"
                        value="{{ old('logo_url', $row->logo_url ?? '') }}"
                        placeholder="https://...">
                    <div class="form-text">{{ __('Absolute URL to a PNG/SVG. Recommended: 48px tall, < 240px wide.') }}</div>
                    @error('logo_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-palette me-2"></i>{{ __('Colours') }}</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="primary_color">{{ __('Primary colour') }}</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color"
                                value="{{ old('primary_color', $row->primary_color ?? '#0d6efd') }}"
                                onchange="document.getElementById('primary_color').value = this.value">
                            <input type="text" id="primary_color" name="primary_color" class="form-control"
                                value="{{ old('primary_color', $row->primary_color ?? '') }}"
                                placeholder="#0d6efd" pattern="^#[A-Fa-f0-9]{3,8}$">
                        </div>
                        @error('primary_color')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="secondary_color">{{ __('Secondary colour') }}</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color"
                                value="{{ old('secondary_color', $row->secondary_color ?? '#6c757d') }}"
                                onchange="document.getElementById('secondary_color').value = this.value">
                            <input type="text" id="secondary_color" name="secondary_color" class="form-control"
                                value="{{ old('secondary_color', $row->secondary_color ?? '') }}"
                                placeholder="#6c757d" pattern="^#[A-Fa-f0-9]{3,8}$">
                        </div>
                        @error('secondary_color')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-card-text me-2"></i>{{ __('Footer text') }}</div>
                <div class="card-body">
                    <label class="form-label" for="footer_text_html">{{ __('Footer HTML') }}</label>
                    <textarea id="footer_text_html" name="footer_text_html" class="form-control" rows="4">{{ old('footer_text_html', $row->footer_text_html ?? '') }}</textarea>
                    <div class="form-text">{{ __('Rendered inside the email footer block. HTML allowed; keep it short.') }}</div>
                    @error('footer_text_html')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person-badge me-2"></i>{{ __('Sender') }}</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="sender_name">{{ __('Sender name') }}</label>
                        <input type="text" id="sender_name" name="sender_name" class="form-control"
                            value="{{ old('sender_name', $row->sender_name ?? '') }}"
                            placeholder="{{ config('mail.from.name') }}">
                        @error('sender_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="sender_email_override">{{ __('Sender email override') }}</label>
                        <input type="email" id="sender_email_override" name="sender_email_override" class="form-control"
                            value="{{ old('sender_email_override', $row->sender_email_override ?? '') }}"
                            placeholder="{{ config('mail.from.address') }}">
                        <div class="form-text">{{ __('Leave blank to use the system-wide From address.') }}</div>
                        @error('sender_email_override')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="/admin/settings" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>{{ __('Save branding') }}
                </button>
            </div>
        </form>
    @endif
</div>
@endsection
