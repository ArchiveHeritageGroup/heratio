<x-library-layout>
  @section('title', 'Edit Trading Partner')

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4">Edit: {{ $partner->edi_partner_code }}</h2>
    <a href="{{ route('library.trading-partners.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>

  <form method="POST" action="{{ route('library.trading-partners.update', $partner->id) }}">
    @csrf @method('PATCH')

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">EDI Partner Code *</label>
        <input name="edi_partner_code" value="{{ old('edi_partner_code', $partner->edi_partner_code) }}" class="form-control" maxlength="20" required>
        @error('edi_partner_code') <div class="text-danger small">{{ $message }}</div> @enderror
      </div>
      <div class="col-md-6">
        <label class="form-label">Linked Vendor</label>
        <select name="vendor_id" class="form-select">
          <option value="">— none —</option>
          @foreach($vendors as $v)
            <option value="{{ $v->id }}" {{ old('vendor_id', $partner->vendor_id)==$v->id ? 'selected' : '' }}>
              {{ $v->name }} ({{ $v->code }})
            </option>
          @endforeach
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">EDI Type *</label>
        <select name="edi_type" id="edi_type" class="form-select" required>
          @foreach(['EANCOM','X12','UN/EDIFACT','CUSTOM'] as $t)
            <option value="{{ $t }}" {{ old('edi_type', $partner->edi_type)==$t ? 'selected' : '' }}>{{ $t }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Message Profile *</label>
        <select name="message_profile" class="form-select" required>
          @foreach(['EANCOM_S93','EANCOM_S94','X12_850','CUSTOM'] as $p)
            <option value="{{ $p }}" {{ old('message_profile', $partner->message_profile)==$p ? 'selected' : '' }}>{{ $p }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Endpoint Type *</label>
        <select name="endpoint_type" id="endpoint_type" class="form-select" required>
          @foreach(['SFTP','AS2','HTTP_HTTPS','EMAIL','MANUAL'] as $e)
            <option value="{{ $e }}" {{ old('endpoint_type', $partner->endpoint_type)==$e ? 'selected' : '' }}>{{ $e }}</option>
          @endforeach
        </select>
      </div>

      {{-- Endpoint config (all types shown based on saved type) --}}
      @php $cfg = $partner->endpoint_config ?? []; @endphp

      <div class="col-12" id="cfg_sftp">
        <div class="card bg-light">
          <div class="card-header">SFTP Configuration</div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-md-4"><label class="form-label">Host</label><input name="endpoint_config[host]" value="{{ old('endpoint_config.host', $cfg['host'] ?? '') }}" class="form-control"></div>
              <div class="col-md-2"><label class="form-label">Port</label><input name="endpoint_config[port]" value="{{ old('endpoint_config.port', $cfg['port'] ?? 22) }}" class="form-control"></div>
              <div class="col-md-3"><label class="form-label">Username</label><input name="endpoint_config[username]" value="{{ old('endpoint_config.username', $cfg['username'] ?? '') }}" class="form-control"></div>
              <div class="col-md-3"><label class="form-label">Password</label><input name="endpoint_config[password]" type="password" value="{{ old('endpoint_config.password', $cfg['password'] ?? '') }}" class="form-control" placeholder="leave blank to keep"></div>
              <div class="col-md-6"><label class="form-label">Outbound Directory</label><input name="outbound_directory" value="{{ old('outbound_directory', $partner->outbound_directory) }}" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Inbound Directory</label><input name="inbound_directory" value="{{ old('inbound_directory', $partner->inbound_directory) }}" class="form-control"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12" id="cfg_as2" style="display:none">
        <div class="card bg-light">
          <div class="card-header">AS2 Configuration</div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-md-8"><label class="form-label">AS2 URL</label><input name="endpoint_config[as2_url]" value="{{ old('endpoint_config.as2_url', $cfg['as2_url'] ?? '') }}" class="form-control"></div>
              <div class="col-md-4"><label class="form-label">Receiver ID</label><input name="endpoint_config[as2_receiver_id]" value="{{ old('endpoint_config.as2_receiver_id', $cfg['as2_receiver_id'] ?? '') }}" class="form-control"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12" id="cfg_http" style="display:none">
        <div class="card bg-light">
          <div class="card-header">HTTP/HTTPS Configuration</div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-12"><label class="form-label">Endpoint URL</label><input name="endpoint_config[url]" value="{{ old('endpoint_config.url', $cfg['url'] ?? '') }}" class="form-control"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12" id="cfg_email" style="display:none">
        <div class="card bg-light">
          <div class="card-header">Email EDI Configuration</div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-md-4"><label class="form-label">SMTP Host</label><input name="endpoint_config[smtp_host]" value="{{ old('endpoint_config.smtp_host', $cfg['smtp_host'] ?? '') }}" class="form-control"></div>
              <div class="col-md-2"><label class="form-label">Port</label><input name="endpoint_config[smtp_port]" value="{{ old('endpoint_config.smtp_port', $cfg['smtp_port'] ?? 587) }}" class="form-control"></div>
              <div class="col-md-3"><label class="form-label">From</label><input name="endpoint_config[smtp_from]" value="{{ old('endpoint_config.smtp_from', $cfg['smtp_from'] ?? '') }}" class="form-control"></div>
              <div class="col-md-3"><label class="form-label">To (EDI mailbox)</label><input name="endpoint_config[smtp_to]" value="{{ old('endpoint_config.smtp_to', $cfg['smtp_to'] ?? '') }}" class="form-control"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="form-check">
          <input type="checkbox" name="is_active" value="1" id="is_active" class="form-check-input" {{ old('is_active', $partner->is_active) ? 'checked' : '' }}>
          <label for="is_active" class="form-check-label">Active</label>
        </div>
        <div class="form-check mt-2">
          <input type="checkbox" name="test_mode" value="1" id="test_mode" class="form-check-input" {{ old('test_mode', $partner->test_mode) ? 'checked' : '' }}>
          <label for="test_mode" class="form-check-label">Test Mode</label>
        </div>
        <div class="form-check mt-2">
          <input type="checkbox" name="acknowledgement_required" value="1" id="ack_req" class="form-check-input" {{ old('acknowledgement_required', $partner->acknowledgement_required) ? 'checked' : '' }}>
          <label for="ack_req" class="form-check-label">ACK Required</label>
        </div>
      </div>

      <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $partner->notes) }}</textarea>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-primary">Update Partner</button>
      </div>
    </div>
  </form>
</x-library-layout>

@push('scripts')
<script>
(function() {
  var map = {SFTP:'sftp',AS2:'as2',HTTP_HTTPS:'http',EMAIL:'email',MANUAL:null};
  function show() {
    ['sftp','as2','http','email'].forEach(function(t){document.getElementById('cfg_'+t).style.display='none';});
    var id = map[document.getElementById('endpoint_type').value];
    if (id) document.getElementById('cfg_'+id).style.display = 'block';
  }
  document.getElementById('endpoint_type').addEventListener('change', show);
  show();
})();
</script>
@endpush
