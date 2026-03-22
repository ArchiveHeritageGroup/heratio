@extends('theme::layouts.1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ $user ? 'Edit' : 'Add new' }} user</h1>
    @if($user)
      <span class="small">{{ $user->authorized_form_of_name ?? $user->username }}</span>
    @endif
  </div>
@endsection

@section('content')
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ $user ? route('user.update', $user->slug) : route('user.store') }}" autocomplete="off">
    @csrf

    <div class="accordion mb-3">

      {{-- Basic info --}}
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#basicInfo-collapse" aria-expanded="true">Basic info</button>
        </h2>
        <div id="basicInfo-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
              <input type="text" name="username" id="username" class="form-control" required autocomplete="off"
                     value="{{ old('username', $user->username ?? '') }}">
            </div>

            <div class="mb-3">
              <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" id="email" class="form-control" required autocomplete="off"
                     value="{{ old('email', $user->email ?? '') }}">
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="password" class="form-label">
                  Password
                  @if(!$user)<span class="text-danger">*</span>@endif
                </label>
                <input type="password" name="password" id="password" class="form-control"
                       {{ $user ? '' : 'required' }} autocomplete="new-password">
                <div class="progress mt-1" style="height: 5px;" id="passwordStrengthBar">
                  <div class="progress-bar" role="progressbar" style="width: 0%;" id="passwordStrengthFill"></div>
                </div>
                <div class="form-text" id="passwordStrengthText">
                  @if($user) Leave blank to keep current password. @endif
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="confirm_password" class="form-label">Confirm password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" autocomplete="new-password">
                <div class="form-text" id="passwordMatchText"></div>
              </div>
            </div>

            <div class="mb-3">
              <label for="active" class="form-label">Active</label>
              <select name="active" id="active" class="form-select">
                <option value="1" {{ old('active', $user ? $user->active : 1) == 1 ? 'selected' : '' }}>Active</option>
                <option value="0" {{ old('active', $user ? $user->active : 1) == 0 ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      {{-- Profile --}}
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#profile-collapse" aria-expanded="true">Profile</button>
        </h2>
        <div id="profile-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="authorized_form_of_name" class="form-label">Authorized form of name</label>
              <input type="text" name="authorized_form_of_name" id="authorized_form_of_name" class="form-control"
                     value="{{ old('authorized_form_of_name', $user->authorized_form_of_name ?? '') }}">
              <div class="form-text">Display name for this user (from actor record).</div>
            </div>
          </div>
        </div>
      </div>

      {{-- Contact information --}}
      @php $contact = $user->contact ?? null; @endphp
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#contactInfo-collapse" aria-expanded="true">Contact information</button>
        </h2>
        <div id="contactInfo-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="contact_telephone" class="form-label">Telephone</label>
                <input type="text" name="contact_telephone" id="contact_telephone" class="form-control"
                       value="{{ old('contact_telephone', $contact->telephone ?? '') }}">
              </div>
              <div class="col-md-6 mb-3">
                <label for="contact_fax" class="form-label">Fax</label>
                <input type="text" name="contact_fax" id="contact_fax" class="form-control"
                       value="{{ old('contact_fax', $contact->fax ?? '') }}">
              </div>
            </div>

            <div class="mb-3">
              <label for="contact_street_address" class="form-label">Street address</label>
              <input type="text" name="contact_street_address" id="contact_street_address" class="form-control"
                     value="{{ old('contact_street_address', $contact->street_address ?? '') }}">
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="contact_city" class="form-label">City</label>
                <input type="text" name="contact_city" id="contact_city" class="form-control"
                       value="{{ old('contact_city', $contact->city ?? '') }}">
              </div>
              <div class="col-md-4 mb-3">
                <label for="contact_region" class="form-label">Region/province</label>
                <input type="text" name="contact_region" id="contact_region" class="form-control"
                       value="{{ old('contact_region', $contact->region ?? '') }}">
              </div>
              <div class="col-md-4 mb-3">
                <label for="contact_postal_code" class="form-label">Postal code</label>
                <input type="text" name="contact_postal_code" id="contact_postal_code" class="form-control"
                       value="{{ old('contact_postal_code', $contact->postal_code ?? '') }}">
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="contact_country_code" class="form-label">Country</label>
                <input type="text" name="contact_country_code" id="contact_country_code" class="form-control"
                       value="{{ old('contact_country_code', $contact->country_code ?? '') }}">
              </div>
              <div class="col-md-6 mb-3">
                <label for="contact_website" class="form-label">Website</label>
                <input type="url" name="contact_website" id="contact_website" class="form-control"
                       value="{{ old('contact_website', $contact->website ?? '') }}">
              </div>
            </div>

            <div class="mb-3">
              <label for="contact_note" class="form-label">Note</label>
              <textarea name="contact_note" id="contact_note" class="form-control" rows="2">{{ old('contact_note', $contact->contact_note ?? '') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      {{-- Access control (User groups) --}}
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#accessControl-collapse" aria-expanded="true">Access control</button>
        </h2>
        <div id="accessControl-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">
            @php
              $currentGroupIds = [];
              if ($user && !empty($user->groups)) {
                  $currentGroupIds = array_map(fn($g) => (int) $g->id, $user->groups);
              }
            @endphp

            <div class="mb-3">
              <label for="groups" class="form-label">User groups</label>
              <select name="groups[]" id="groups" class="form-select" multiple size="{{ min(max(count($assignableGroups), 3), 8) }}">
                @foreach($assignableGroups as $group)
                  <option value="{{ $group->id }}" {{ in_array((int) $group->id, old('groups', $currentGroupIds)) ? 'selected' : '' }}>
                    {{ $group->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text">Hold Ctrl/Cmd to select multiple groups.</div>
            </div>

            @if(empty($assignableGroups))
              <p class="text-muted mb-0">No assignable groups found.</p>
            @endif
          </div>
        </div>
      </div>

      {{-- Allowed languages for translation --}}
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#translate-collapse" aria-expanded="true">Allowed languages for translation</button>
        </h2>
        <div id="translate-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">
            @php
              $currentTranslate = $user->translateLanguages ?? [];
            @endphp

            @if(!empty($availableLanguages))
              <div class="mb-3">
                <label for="translate" class="form-label">Translate</label>
                <select name="translate[]" id="translate" class="form-select" multiple size="{{ min(max(count($availableLanguages), 3), 8) }}">
                  @foreach($availableLanguages as $lang)
                    <option value="{{ $lang }}" {{ in_array($lang, old('translate', $currentTranslate)) ? 'selected' : '' }}>
                      {{ locale_get_display_language($lang, app()->getLocale()) ?: $lang }}
                    </option>
                  @endforeach
                </select>
                <div class="form-text">Hold Ctrl/Cmd to select multiple languages. User will be allowed to translate content into selected languages.</div>
              </div>
            @else
              <p class="text-muted mb-0">No languages configured. Add languages in Admin &gt; Settings &gt; I18n.</p>
            @endif
          </div>
        </div>
      </div>

      {{-- API keys (edit only) --}}
      @if($user)
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#apiKeys-collapse" aria-expanded="true">API keys</button>
        </h2>
        <div id="apiKeys-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">
            @php
              $restApiKey = \Illuminate\Support\Facades\DB::table('property')
                  ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
                  ->where('property.object_id', $user->id)
                  ->where('property.name', 'restApiKey')
                  ->value('property_i18n.value');
              $oaiApiKey = \Illuminate\Support\Facades\DB::table('property')
                  ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
                  ->where('property.object_id', $user->id)
                  ->where('property.name', 'oaiApiKey')
                  ->value('property_i18n.value');
            @endphp

            <div class="mb-3">
              <label for="restApiKey" class="form-label">
                REST API access key
                @if($restApiKey)
                  <code class="ms-2">{{ $restApiKey }}</code>
                @endif
              </label>
              <select name="restApiKey" id="restApiKey" class="form-select">
                <option value="">-- Select action --</option>
                <option value="generate">(Re)generate API key</option>
                <option value="delete">Delete API key</option>
              </select>
              @if(!$restApiKey)
                <div class="form-text">Not generated yet.</div>
              @endif
            </div>

            <div class="mb-3">
              <label for="oaiApiKey" class="form-label">
                OAI-PMH API access key
                @if($oaiApiKey)
                  <code class="ms-2">{{ $oaiApiKey }}</code>
                @endif
              </label>
              <select name="oaiApiKey" id="oaiApiKey" class="form-select">
                <option value="">-- Select action --</option>
                <option value="generate">(Re)generate API key</option>
                <option value="delete">Delete API key</option>
              </select>
              @if(!$oaiApiKey)
                <div class="form-text">Not generated yet.</div>
              @endif
            </div>
          </div>
        </div>
      </div>
      @endif

    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($user)
        <li><a href="{{ route('user.show', $user->slug) }}" class="btn atom-btn-white">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a href="{{ route('user.browse') }}" class="btn atom-btn-white">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
      @endif
    </ul>
  </form>

  <script>
  (function() {
    var pw = document.getElementById('password');
    var cpw = document.getElementById('confirm_password');
    var strengthFill = document.getElementById('passwordStrengthFill');
    var strengthText = document.getElementById('passwordStrengthText');
    var matchText = document.getElementById('passwordMatchText');

    function checkStrength(val) {
      if (val.length === 0) {
        strengthFill.style.width = '0%';
        strengthFill.className = 'progress-bar';
        return;
      }
      var score = 0;
      if (val.length >= 8) score++;
      if (val.length >= 12) score++;
      if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score++;
      if (/\d/.test(val)) score++;
      if (/[^a-zA-Z0-9]/.test(val)) score++;

      var pct, cls, label;
      if (score <= 1) { pct = '20%'; cls = 'progress-bar bg-danger'; label = 'Weak'; }
      else if (score === 2) { pct = '40%'; cls = 'progress-bar bg-warning'; label = 'Fair'; }
      else if (score === 3) { pct = '60%'; cls = 'progress-bar bg-info'; label = 'Good'; }
      else if (score === 4) { pct = '80%'; cls = 'progress-bar bg-primary'; label = 'Strong'; }
      else { pct = '100%'; cls = 'progress-bar bg-success'; label = 'Very strong'; }
      strengthFill.style.width = pct;
      strengthFill.className = cls;
      strengthText.textContent = label;
    }

    function checkMatch() {
      if (cpw.value.length === 0) { matchText.textContent = ''; cpw.classList.remove('is-valid', 'is-invalid'); return; }
      if (pw.value === cpw.value) {
        matchText.textContent = 'Passwords match.';
        matchText.className = 'form-text text-success';
        cpw.classList.add('is-valid'); cpw.classList.remove('is-invalid');
      } else {
        matchText.textContent = 'Passwords do not match.';
        matchText.className = 'form-text text-danger';
        cpw.classList.add('is-invalid'); cpw.classList.remove('is-valid');
      }
    }

    pw.addEventListener('input', function() { checkStrength(this.value); checkMatch(); });
    cpw.addEventListener('input', checkMatch);
  })();
  </script>
@endsection
