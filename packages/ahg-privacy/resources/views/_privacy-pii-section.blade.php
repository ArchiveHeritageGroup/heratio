{{--
  Privacy & PII status panel — visible to authenticated users on the IO show page.

  Vars: $resource (object with ->id and ->slug).

  PII presence is detected directly from privacy_visual_redaction:
    - any pending region   ⇒ "PII detected — review recommended"
    - any approved region  ⇒ "Record contains redacted PII"
  The panel is hidden when neither applies (no PII flags on this record).

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    if (! auth()->check() || empty($resource->id ?? null) || ! Schema::hasTable('privacy_visual_redaction')) {
        return;
    }

    $base = DB::table('privacy_visual_redaction')->where('object_id', (int) $resource->id);
    $hasRedacted = (clone $base)->where('status', 'approved')->exists();
    $hasPii      = (clone $base)->whereIn('status', ['pending', 'review'])->exists();

    if (! $hasRedacted && ! $hasPii) return;

    $reviewHref = \Illuminate\Support\Facades\Route::has('privacy.piiReview')
        ? route('privacy.piiReview', ['slug' => $resource->slug ?? null])
        : url('/privacy/pii/review/' . urlencode($resource->slug ?? ''));
@endphp
<section class="card mb-4">
    <div class="card-header bg-warning">
        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>{{ __('Privacy & PII') }}</h5>
    </div>
    <div class="card-body">
        @if ($hasRedacted)
            <div class="alert alert-info mb-2 py-2">
                <i class="fas fa-eye-slash me-2"></i>{{ __('This record contains redacted personally identifiable information (PII).') }}
            </div>
        @endif
        @if ($hasPii && ! $hasRedacted)
            <div class="alert alert-warning mb-2 py-2">
                <i class="fas fa-exclamation-triangle me-2"></i>{{ __('PII detected in this record — review recommended.') }}
            </div>
        @endif
        <a href="{{ $reviewHref }}" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-search me-1"></i>{{ __('Review PII') }}
        </a>
    </div>
</section>
