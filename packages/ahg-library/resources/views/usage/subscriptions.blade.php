@extends('theme::layouts.1col')
@section('title', 'SUSHI Partner Subscriptions')

@section('content')
<div class="container py-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-server me-2"></i>SUSHI Partner Subscriptions
            </h1>
            <p class="text-muted small mb-0">
                ISO 18626 SUSHI v5 — manage content provider endpoints and connection credentials.
            </p>
        </div>
        <a href="{{ route('library.usage') }}" class="btn btn-outline-dark btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back to Usage
        </a>
    </div>

    {{-- Success / error flashes --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Connection test result (JSON) --}}
    @if(session('connection_test'))
        @php $test = session('connection_test'); @endphp
        <div class="alert alert-{{ $test['ok'] ? 'success' : 'danger' }} alert-dismissible fade show">
            <strong>Connection test for {{ $test['partner_code'] }}:</strong>
            @if($test['ok'])
                <span class="badge bg-success ms-2">OK</span>
                &mdash; {{ $test['partner_label'] }}
                &mdash; Services: {{ implode(', ', $test['services']) ?: 'none detected' }}
            @else
                <span class="badge bg-danger ms-2">FAILED</span>
                &mdash; {{ $test['error'] }}
            @endif
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Partner list --}}
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Active Partners</h6>
                    <span class="badge bg-primary">{{ count($subscriptions) }}</span>
                </div>
                @if(empty($subscriptions))
                    <div class="card-body text-muted text-center py-5">
                        <i class="fas fa-server fa-2x mb-3"></i>
                        <p class="mb-0">No SUSHI partners configured yet.</p>
                        <p class="small">Add your first partner using the form.</p>
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($subscriptions as $sub)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <code class="fw-bold">{{ $sub['partner_code'] }}</code>
                                        <br>
                                        <small class="text-muted">{{ $sub['base_url'] }}</small>
                                        @if(!empty($sub['report_types']))
                                            <br>
                                            @foreach($sub['report_types'] as $rt)
                                                <span class="badge bg-secondary me-1">{{ $rt }}</span>
                                            @endforeach
                                        @endif
                                        @if(!empty($sub['contact_email']))
                                            <br><small class="text-muted"><i class="fas fa-envelope me-1"></i>{{ $sub['contact_email'] }}</small>
                                        @endif
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('library.usage-subscriptions-test', ['partner_code' => $sub['partner_code']]) }}"
                                           class="btn btn-outline-primary test-btn"
                                           data-code="{{ $sub['partner_code'] }}">
                                            <i class="fas fa-plug me-1"></i>Test
                                        </a>
                                        <a href="{{ route('library.usage-subscriptions', ['delete_id' => $sub['id']]) }}"
                                           class="btn btn-outline-danger"
                                           onclick="return confirm('Remove partner &apos;{{ $sub['partner_code'] }}&apos;?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Add partner form --}}
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-plus me-2"></i>Add / Update Partner
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('library.usage-subscriptions-store') }}">
                        @csrf

                        <div class="mb-2">
                            <label class="form-label small">Partner Code <span class="text-danger">*</span></label>
                            <input type="text" name="partner_code"
                                   class="form-control form-control-sm @error('partner_code') is-invalid @enderror"
                                   value="{{ old('partner_code') }}"
                                   placeholder="e.g. sabinet, naz, ebscohost"
                                   pattern="[a-z0-9_]+" maxlength="50">
                            <small class="text-muted">Lowercase letters, numbers and underscores only.</small>
                            @error('partner_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label small">Contact Email <span class="text-danger">*</span></label>
                            <input type="email" name="contact_email"
                                   class="form-control form-control-sm @error('contact_email') is-invalid @endif"
                                   value="{{ old('contact_email') }}"
                                   placeholder="library@provider.com">
                            @error('contact_email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label small">SUSHI Base URL <span class="text-danger">*</span></label>
                            <input type="url" name="base_url"
                                   class="form-control form-control-sm @error('base_url') is-invalid @endif"
                                   value="{{ old('base_url') }}"
                                   placeholder="https://sushi.provider.co.za/sushi/v5">
                            <small class="text-muted">Root URL — path <code>/sushi/v5/reports/*</code> is appended automatically.</small>
                            @error('base_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label small">API Key (optional)</label>
                            <input type="password" name="api_key"
                                   class="form-control form-control-sm"
                                   placeholder="Bearer token">
                            <small class="text-muted">Stored encrypted at rest.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Report Types</label>
                            <div>
                                @foreach(['PR', 'TR', 'DR', 'IR'] as $rt)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="report_types[]"
                                               id="rt-{{ $rt }}" value="{{ $rt }}"
                                               @if(in_array($rt, old('report_types', [])) || in_array($rt, ['PR', 'TR'])) checked @endif>
                                        <label class="form-check-label small" for="rt-{{ $rt }}">{{ $rt }}</label>
                                    </div>
                                @endforeach
                            </div>
                            <small class="text-muted">PR (Platform) and TR (Title) are the most common.</small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-save me-1"></i>Save Partner
                        </button>
                    </form>
                </div>
            </div>

            {{-- Info card --}}
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>About SUSHI / COUNTER 5</h6>
                </div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item">
                        <strong>ISO 18626</strong> — Standardised Usage Statistics Harvesting Initiative
                    </li>
                    <li class="list-group-item">
                        <strong>COUNTER 5</strong> — Code of Practice Release 5 (2019)
                    </li>
                    <li class="list-group-item">
                        <strong>SUSHI</strong> — REST-based automated protocol; eliminates manual counter reports
                    </li>
                    <li class="list-group-item">
                        <strong>NAZ</strong>, SABINET, DALS — built-in South African library consortia presets
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // AJAX connection test — replaces page navigation with a JSON fetch
    document.querySelectorAll('.test-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var code = this.getAttribute('data-code');
            var url = this.getAttribute('href');
            var origHTML = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Testing…';
            this.disabled = true;

            fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    alert('[OK] ' + data.partner_label + '\nServices: ' + (data.services.join(', ') || 'none detected'));
                } else {
                    alert('[FAILED] ' + data.partner_code + '\n' + data.error);
                }
            })
            .catch(function (err) {
                alert('Connection test error: ' + err.message);
            })
            .finally(function () {
                btn.innerHTML = origHTML;
                btn.disabled = false;
            });
        });
    });
});
</script>
@endpush