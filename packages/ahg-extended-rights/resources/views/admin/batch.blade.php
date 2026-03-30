@extends('ahg-theme-b5::layout')

@section('title', 'Batch Rights Operations')

@section('content')
<div class="container-fluid mt-3">
  @include('ahg-extended-rights::admin._sidebar')

  <h1><i class="fas fa-layer-group"></i> Batch Rights Operations</h1>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Batch Assign Rights</h5></div>
    <div class="card-body">
      <form method="POST" action="{{ route('ext-rights-admin.batch-store') }}">
        @csrf
        <div class="row">
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Operation</label>
              <select name="operation" class="form-select" required>
                <option value="assign_statement">Assign Rights Statement</option>
                <option value="assign_cc">Assign CC License</option>
                <option value="assign_tk">Assign TK Label</option>
                <option value="create_embargo">Create Embargo</option>
                <option value="clear_rights">Clear All Rights</option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Rights Statement</label>
              <select name="statement_id" class="form-select">
                <option value="">— Select —</option>
                @foreach($statements ?? [] as $stmt)
                  <option value="{{ $stmt->id }}">{{ e($stmt->name ?? '') }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">CC License</label>
              <select name="cc_license_id" class="form-select">
                <option value="">— Select —</option>
                @foreach($ccLicenses ?? [] as $cc)
                  <option value="{{ $cc->id }}">{{ e($cc->name ?? '') }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Embargo Type</label>
              <select name="embargo_type" class="form-select">
                <option value="full">Full</option>
                <option value="metadata_only">Metadata Only</option>
                <option value="digital_only">Digital Object Only</option>
                <option value="partial">Partial</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">End Date</label>
              <input type="date" name="end_date" class="form-control">
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Object IDs (one per line or comma-separated)</label>
          <textarea name="object_ids" class="form-control" rows="4" placeholder="123&#10;456&#10;789" required></textarea>
        </div>

        <div class="form-check mb-3">
          <input type="checkbox" name="apply_to_children" value="1" class="form-check-input" id="batchChildren">
          <label class="form-check-label" for="batchChildren">Apply to child records</label>
        </div>

        <button type="submit" class="btn btn-primary" onclick="return confirm('Apply batch operation to these objects?')">
          <i class="fas fa-play"></i> Execute Batch
        </button>
      </form>
    </div>
  </div>

  {{-- Recent Batch Operations --}}
  @if(!empty($recentBatches))
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Recent Batch Operations</h5></div>
    <div class="card-body table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>Date</th><th>Operation</th><th>Objects</th><th>By</th><th>Result</th></tr></thead>
        <tbody>
          @foreach($recentBatches as $batch)
          <tr>
            <td>{{ $batch->created_at ?? '' }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $batch->action ?? '')) }}</td>
            <td>{{ $batch->object_count ?? 0 }}</td>
            <td>{{ e($batch->performed_by ?? '') }}</td>
            <td><span class="badge bg-{{ ($batch->result ?? '') === 'success' ? 'success' : 'danger' }}">{{ ucfirst($batch->result ?? '') }}</span></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif
</div>
@endsection
