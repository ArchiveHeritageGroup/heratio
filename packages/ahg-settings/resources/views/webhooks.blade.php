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
        <button type="button" class="btn atom-btn-white btn-sm" data-bs-toggle="modal" data-bs-target="#createWebhookModal">
          <i class="fas fa-plus me-1"></i>Create Webhook
        </button>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">Webhooks notify external applications when records are created, updated, or deleted. Each webhook receives an HMAC signature for verification.</p>

        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead class="table-light">
              <tr><th>Name</th><th>URL</th><th>Events</th><th>Status</th><th>Deliveries</th><th>Actions</th></tr>
            </thead>
            <tbody>
              @forelse($webhooks ?? [] as $webhook)
                <tr>
                  <td><strong>{{ $webhook->name }}</strong></td>
                  <td><code class="small">{{ Str::limit($webhook->url, 40) }}</code></td>
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
                    <a href="#" class="btn btn-sm atom-btn-white"><i class="fas fa-edit"></i></a>
                    <a href="#" class="btn btn-sm atom-btn-white text-danger"><i class="fas fa-trash"></i></a>
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-broadcast-tower fa-2x d-block mb-2"></i>No webhooks configured.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <a href="{{ route('settings.index') }}" class="btn atom-btn-white">Back to Settings</a>
  </div>
</div>

{{-- Create Webhook Modal --}}
<div class="modal fade" id="createWebhookModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="{{ route('settings.webhooks') }}">
        @csrf
        <div class="modal-header"><h5 class="modal-title">Create Webhook</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Name <span class="badge bg-danger ms-1">Required</span></label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">URL <span class="badge bg-danger ms-1">Required</span></label>
            <input type="url" name="url" class="form-control" required placeholder="https://example.com/webhook">
          </div>
          <div class="mb-3">
            <label class="form-label">Events <span class="badge bg-secondary ms-1">Optional</span></label>
            @foreach(['item.created', 'item.updated', 'item.deleted', 'item.published'] as $ev)
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="events[]" value="{{ $ev }}" id="ev-{{ $ev }}">
                <label class="form-check-label" for="ev-{{ $ev }}">{{ $ev }} <span class="badge bg-secondary ms-1">Optional</span></label>
              </div>
            @endforeach
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn atom-btn-outline-success">Create</button>
        </div>
      </form>
    </div>
@endsection
