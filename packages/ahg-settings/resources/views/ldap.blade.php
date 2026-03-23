@extends('theme::layouts.1col')
@section('title', 'LDAP authentication')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-settings::_menu')
  </div>
  <div class="col-md-9">
    <h1>LDAP authentication</h1>

    <form method="post" action="{{ route('settings.ldap') }}">
      @csrf
      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#ldap-collapse">LDAP authentication settings</button></h2>
          <div id="ldap-collapse" class="accordion-collapse collapse show">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Host <span class="badge bg-danger ms-1">Required</span></label>
                <input type="text" name="settings[ldapHost]" class="form-control" value="{{ $settings['ldapHost'] ?? '' }}" placeholder="ldap.example.com">
              </div>
              <div class="mb-3">
                <label class="form-label">Port <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="number" name="settings[ldapPort]" class="form-control" value="{{ $settings['ldapPort'] ?? '389' }}" style="max-width:200px;">
              </div>
              <div class="mb-3">
                <label class="form-label">Base DN <span class="badge bg-danger ms-1">Required</span></label>
                <input type="text" name="settings[ldapBaseDn]" class="form-control" value="{{ $settings['ldapBaseDn'] ?? '' }}" placeholder="dc=example,dc=com">
              </div>
              <div class="mb-3">
                <label class="form-label">Bind Lookup Attribute <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="settings[ldapBindAttribute]" class="form-control" value="{{ $settings['ldapBindAttribute'] ?? 'uid' }}">
              </div>
            </div>
          </div>
        </div>
      </div>

      <section class="actions mb-3">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>
    </form>
  </div>
</div>
@endsection
