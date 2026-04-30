{{--
  Webhooks — event-based webhook management
  Cloned from AtoM ahgSettingsPlugin webhooksSuccess.php

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Webhooks')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1><i class="fas fa-broadcast-tower me-2"></i>Webhooks</h1>
@endsection

@section('content')
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-broadcast-tower me-2"></i>Webhook Management</h5>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createWebhookModal">
          <i class="fas fa-plus me-1"></i>Create Webhook
        </button>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">Webhooks notify external applications when records are created, updated, or deleted. Each webhook receives an HMAC signature for verification.</p>

        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead class="table-light">
              <tr>
                <th>{{ __('Name') }}</th>
                <th>URL</th>
                <th>{{ __('User') }}</th>
                <th>{{ __('Events') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Deliveries') }}</th>
                <th>{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($webhooks ?? [] as $webhook)
                <tr>
                  <td><strong>{{ $webhook->name }}</strong></td>
                  <td><code class="small">{{ Str::limit($webhook->url, 40) }}</code></td>
                  <td class="small">{{ $webhook->user_name ?? $webhook->user_id ?? '—' }}</td>
                  <td>
                    @foreach($webhook->events ?? [] as $event)
                      <span class="badge bg-{{ match($event) { 'item.created' => 'success', 'item.updated' => 'primary', 'item.deleted' => 'danger', default => 'secondary' } }}">{{ $event }}</span>
                    @endforeach
                  </td>
                  <td>
                    @if($webhook->is_active ?? false)
                      <span class="badge bg-success">Active</span>
                    @else
                      <span class="badge bg-secondary">Inactive</span>
                    @endif
                  </td>
                  <td>{{ $webhook->delivery_count ?? 0 }}</td>
                  <td>
                    <a href="#" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                    <a href="#" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></a>
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-broadcast-tower fa-2x d-block mb-2"></i>No webhooks configured.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>Back to Settings
    </a>

{{-- Create Webhook Modal --}}
<div class="modal fade" id="createWebhookModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="{{ route('settings.webhooks') }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">{{ __('Create Webhook') }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Name') }}</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">URL</label>
            <input type="url" name="url" class="form-control" required placeholder="{{ __('https://example.com/webhook') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Events') }}</label>
            @foreach(['item.created', 'item.updated', 'item.deleted', 'item.published'] as $ev)
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="events[]" value="{{ $ev }}" id="ev-{{ $ev }}">
                <label class="form-check-label" for="ev-{{ $ev }}">{{ $ev }}</label>
              </div>
            @endforeach
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
