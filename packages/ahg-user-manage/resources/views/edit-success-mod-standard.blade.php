@php decorate_with('layout_1col.php'); @endphp

@php slot('title'); @endphp
  <h1>{{ __('User %1%', ['%1%' => render_title($resource)]) }}</h1>
@php end_slot(); @endphp

@php slot('content'); @endphp

  @php echo $form->renderGlobalErrors(); @endphp

  @if(isset($sf_request->getAttribute('sf_route')->resource))
    @php echo $form->renderFormTag(url_for([$resource, 'module' => 'user', 'action' => 'edit']), ['id' => 'editForm']); @endphp
  @php } else { @endphp
    @php echo $form->renderFormTag(route('user.add'), ['id' => 'editForm']); @endphp
  @endforeach

    @php echo $form->renderHiddenFields(); @endphp

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="basic-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#basic-collapse" aria-expanded="false" aria-controls="basic-collapse">
            {{ __('Basic info') }}
          </button>
        </h2>
        <div id="basic-collapse" class="accordion-collapse collapse" aria-labelledby="basic-heading">
          <div class="accordion-body">
            @php echo render_field($form->username); @endphp

            @php echo render_field($form->email, null, ['type' => 'email']); @endphp

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
                @if(isset($sf_request->getAttribute('sf_route')->resource))
                  @php echo render_field($form->password->label(__('Change password')), null, ['class' => 'password-strength']); @endphp
                @php } else { @endphp
                  @php echo render_field($form->password->label(__('Password')), null, ['class' => 'password-strength']); @endphp
                @endforeach
                @php echo render_field($form->confirmPassword->label(__('Confirm password')), null, ['class' => 'password-confirm']); @endphp
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

			@if($sf_user->user != $resource)
			  @php echo $form->active
			    ->label(__('Active'))
			    ->renderRow(); @endphp
			@endforeach

            @if($sf_user->user != $resource)
              @php echo render_field($form->active->label(__('Active'))); @endphp
            @endforeach
          </div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="access-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#access-collapse" aria-expanded="false" aria-controls="access-collapse">
            {{ __('Access control') }}
          </button>
        </h2>
        <div id="access-collapse" class="accordion-collapse collapse" aria-labelledby="access-heading">
          <div class="accordion-body">
            @php echo render_field(
                $form->groups->label(__('User groups')),
                null,
                ['class' => 'form-autocomplete']
            ); @endphp

            @php echo render_field(
                $form->translate->label(__('Allowed languages for translation')),
                null,
                ['class' => 'form-autocomplete']
            ); @endphp

            @if($restEnabled)
              @php echo render_field($form->restApiKey->label(
                  __('REST API access key'.((isset($restApiKey)) ? ': <code class="ms-2">'.$restApiKey.'</code>' : ''))
              )); @endphp
            @endforeach

            @if($oaiEnabled)
              @php echo render_field($form->oaiApiKey->label(
                  __('OAI-PMH API access key'.((isset($oaiApiKey)) ? ': <code class="ms-2">'.$oaiApiKey.'</code>' : ''))
              )); @endphp
            @endforeach
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      @if(isset($sf_request->getAttribute('sf_route')->resource))
        <li>@php echo link_to(__('Cancel'), [$resource, 'module' => 'user'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
      @php } else { @endphp
        <li>@php echo link_to(__('Cancel'), ['module' => 'user', 'action' => 'list'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Create') }}"></li>
      @endforeach
    </ul>

  </form>

@php end_slot(); @endphp
