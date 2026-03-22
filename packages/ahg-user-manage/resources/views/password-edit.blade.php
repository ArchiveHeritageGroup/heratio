@php decorate_with('layout_1col.php'); @endphp

@php slot('title'); @endphp
  <h1>{{ __('User %1%', ['%1%' => render_title($resource)]) }}</h1>
@php end_slot(); @endphp

@php slot('content'); @endphp

  @php echo $form->renderGlobalErrors(); @endphp

  @php echo $form->renderFormTag(url_for([$resource, 'module' => 'user', 'action' => 'passwordEdit']), ['id' => 'editForm']); @endphp

    @php echo $form->renderHiddenFields(); @endphp

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="password-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#password-collapse" aria-expanded="false" aria-controls="password-collapse">
            {{ __('Reset password') }}
          </button>
        </h2>
        <div id="password-collapse" class="accordion-collapse collapse" aria-labelledby="password-heading">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-6">
                <div
                    hidden
                    class="password-strength-settings"
                    data-not-strong="{{ __('Your password is not strong enough.') }}"
                    data-strength-title="{{ __('Password strength:') }}"
                    data-require-strong-password="@php echo sfConfig::get('app_require_strong_passwords', false); @endphp"
                    data-too-short="{{ __('Make it at least six characters') }}"
                    data-add-lower-case="{{ __('Add lowercase letters') }}"
                    data-add-upper-case="{{ __('Add uppercase letters') }}"
                    data-add-numbers="{{ __('Add numbers') }}"
                    data-add-punctuation="{{ __('Add punctuation') }}"
                    data-username="@php echo $resource->username; @endphp"
                    data-same-as-username="{{ __('Make it different from your username') }}"
                    data-confirm-failure="{{ __('Your password confirmation did not match your password.') }}"
                  >
                </div>
                @php echo render_field($form->password->label(__('New password')), null, ['class' => 'password-strength', 'required' => 'required']); @endphp
                @php echo render_field($form->confirmPassword->label(__('Confirm password')), null, ['class' => 'password-confirm', 'required' => 'required']); @endphp
              </div>
              <div class="col-md-6 template" hidden>
                <div class="mb-3 bg-light p-3 rounded border-start border-4">
                  <label class="form-label">{{ __('Password strength:') }}</label>
		  <div class="progress mb-3">
                    <div class="progress-bar w-0" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li>@php echo link_to(__('Cancel'), [$resource, 'module' => 'user'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
    </ul>

  </form>

@php end_slot(); @endphp
