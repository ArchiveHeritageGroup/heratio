@php decorate_with('layout_1col.php'); @endphp
@php use_helper('Javascript'); @endphp

@php slot('title'); @endphp
  <h1>{{ __('Reset Your Password') }}</h1>
@php end_slot(); @endphp

@php slot('content'); @endphp

  @php echo $form->renderGlobalErrors(); @endphp

  @php echo $form->renderFormTag(url_for(['module' => 'user', 'action' => 'passwordResetConfirm', 'token' => $sf_request->getParameter('token')]), ['id' => 'passwordResetConfirmForm']); @endphp

    @php echo $form->renderHiddenFields(); @endphp

    <section id="content">
      <fieldset class="collapsible">
        <legend>{{ __('Enter your new password') }}</legend>

        @php $settings = json_encode([
            'password' => [
                'strengthTitle' => __('Password strength:'),
                'hasWeaknesses' => __('To make your password stronger:'),
                'tooShort' => __('Make it at least six characters'),
                'addLowerCase' => __('Add lowercase letters'),
                'addUpperCase' => __('Add uppercase letters'),
                'addNumbers' => __('Add numbers'),
                'addPunctuation' => __('Add punctuation'),
                'confirmSuccess' => __('Yes'),
                'confirmFailure' => __('No'),
                'confirmTitle' => __('Passwords match:'),
            ], ]); @endphp

        @php echo javascript_tag(<<<EOF
jQuery.extend(Drupal.settings, {$settings});
EOF
); @endphp

        @php echo $form->password->renderError(); @endphp

        <div class="form-item password-parent">
          @php echo $form->password
              ->label(__('New Password'))
              ->renderLabel(); @endphp
          @php echo $form->password->render(['class' => 'password-field']); @endphp
        </div>

        <div class="form-item confirm-parent">
          @php echo $form->confirmPassword
              ->label(__('Confirm Password'))
              ->renderLabel(); @endphp
          @php echo $form->confirmPassword->render(['class' => 'password-confirm']); @endphp
        </div>

      </fieldset>
    </section>

    <section class="actions">
      <ul>
        @php echo link_to(__('Cancel'), ['module' => 'user', 'action' => 'login'], ['class' => 'c-btn']); @endphp
        <input class="c-btn c-btn-submit" type="submit" value="{{ __('Reset Password') }}"/>
      </ul>
    </section>

  </form>

@php end_slot(); @endphp