<x-library-layout>
  @section('title', 'New Trading Partner')

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4">New EDI Trading Partner</h2>
    <a href="{{ route('library.trading-partners.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>

  <form method="POST" action="{{ route('library.trading-partners.store') }}">
    @csrf

    <div class="row g-3">
      {{-- Basic --}}
      <div class="col-md-6">
        <label class="form-label">EDI Partner Code *</label>
        <input name="edi_partner_code" value="{{ old('edi_partner_code') }}" class="form-control" maxlength="20" required>
        @error('edi_partner_code') <div class="text-danger small">{{ $message }}</div> @enderror
      </div>
      <div class="col-md-6">
        <label class="form-label">Linked Vendor</label>
        <select name="vendor_id" class="form-select">
          <option value="">— none —</option>
          @foreach($vendors as $v)
            <option value="{{ $v->id }}" {{ old('vendor_id')==$v->id ? 'selected' : '' }}>
              {{ $v->name }} ({{ $v->code }})
            </option>
          @endforeach
        </select>
      </div>

      {{-- EDI type --}}
      <div class="col-md-4">
        <label class="form-label">EDI Type *</label>
        <select name="edi_type" id="edi_type" class="form-select" required>
          @foreach(['EANCOM','X12','UN/EDIFACT','CUSTOM'] as $t)
            <option value="{{ $t }}" {{ old('edi_type', 'EANCOM')==$t ? 'selected' : '' }}>{{ $t }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Message Profile *</label>
        <select name="message_profile" id="message_profile" class="form-select" required>
          <option value="EANCOM_S93" {{ old('message_profile')=='EANCOM_S93' ? 'selected' : '' }}>EANCOM S93</option>
          <option value="EANCOM_S94" {{ old('message_profile')=='EANCOM_S94' ? 'selected' : '' }}>EANCOM S94</option>
          <option value="X12_850" {{ old('message_profile')=='X12_850' ? 'selected' : '' }}>X12 850</option>
          <option value="CUSTOM" {{ old('message_profile')=='CUSTOM' ? 'selected' : '' }}>Custom</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Endpoint Type *</label>
        <select name="endpoint_type" id="endpoint_type" class="form-select" required>
          @foreach(['SFTP','AS2','HTTP_HTTPS','EMAIL','MANUAL'] as $e)
            <option value="{{ $e }}" {{ old('endpoint_type', 'SFTP')==$e ? 'selected' : '' }}>{{ $e }}</option>
          @endforeach
        </select>
      </div>

      {{-- SFTP config --}}
      <div class="col-12" id="cfg_sftp">
        <div class="card bg-light">
          <div class="card-header">SFTP Configuration</div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label">Host *</label>
                <input name="endpoint_config[host]" value="{{ old('endpoint_config.host') }}" class="form-control">
              </div>
              <div class="col-md-2">
                <label class="form-label">Port</label>
                <input name="endpoint_config[port]" value="{{ old('endpoint_config.port', 22) }}" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">Username</label>
                <input name="endpoint_config[username]" value="{{ old('endpoint_config.username') }}" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">Password</label>
                <input name="endpoint_config[password]" type="password" value="{{ old('endpoint_config.password') }}" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">Outbound Directory</label>
                <input name="outbound_directory" value="{{ old('outbound_directory', '/outbox/') }}" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">Inbound Directory</label>
                <input name="inbound_directory" value="{{ old('inbound_directory', '/inbox/') }}" class="form-control">
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- AS2 config --}}
      <div class="col-12" id="cfg_as2" style="display:none">
        <div class="card bg-light">
          <div class="card-header">AS2 Configuration</div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-md-8">
                <label class="form-label">AS2 URL *</label>
                <input name="endpoint_config[as2_url]" value="{{ old('endpoint_config.as2_url') }}" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label">Receiver ID</label>
                <input name="endpoint_config[as2_receiver_id]" value="{{ old('endpoint_config.as2_receiver_id') }}" class="form-control">
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- HTTP config --}}
      <div class="col-12" id="cfg_http" style="display:none">
        <div class="card bg-light">
          <div class="card-header">HTTP/HTTPS Configuration</div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label">Endpoint URL *</label>
                <input name="endpoint_config[url]" value="{{ old('endpoint_config.url') }}" class="form-control">
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- EMAIL config --}}
      <div class="col-12" id="cfg_email" style="display:none">
        <div class="card bg-light">
          <div class="card-header">Email EDI Configuration</div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label">SMTP Host</label>
                <input name="endpoint_config[smtp_host]" value="{{ old('endpoint_config.smtp_host') }}" class="form-control">
              </div>
              <div class="col-md-2">
                <label class="form-label">Port</label>
                <input name="endpoint_config[smtp_port]" value="{{ old('endpoint_config.smtp_port', 587) }}" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">From</label>
                <input name="endpoint_config[smtp_from]" value="{{ old('endpoint_config.smtp_from') }}" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">To (EDI mailbox)</label>
                <input name="endpoint_config[smtp_to]" value="{{ old('endpoint_config.smtp_to') }}" class="form-control">
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Flags --}}
      <div class="col-md-4">
        <div class="form-check">
          <input type="checkbox" name="is_active" value="1" id="is_active" class="form-check-input" checked>
          <label for="is_active" class="form-check-label">Active</label>
        </div>
        <div class="form-check mt-2">
          <input type="checkbox" name="test_mode" value="1" id="test_mode" class="form-check-input" checked>
          <label for="test_mode" class="form-check-label">Test Mode</label>
        </div>
        <div class="form-check mt-2">
          <input type="checkbox" name="acknowledgement_required" value="1" id="ack_req" class="form-check-input" checked>
          <label for="ack_req" class="form-check-label">ACK Required</label>
        </div>
      </div>

      {{-- Notes --}}
      <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-primary">Save Partner</button>
      </div>
    </div>
  </form>
</x-library-layout>

@push('scripts')
<script>
document.getElementById('endpoint_type').addEventListener('change', function() {
  ['sftp','as2','http','email'].forEach(t => document.getElementById('cfg_' + t).style.display = 'none');
  const map = {SFTP:'sftp',AS2:'as2',HTTP_HTTPS:'http',EMAIL:'email',MANUAL:null};
  const id = map[this.value];
  if (id) document.getElementById('cfg_' + id).style.display = 'block';
});
</script>
@endpush
