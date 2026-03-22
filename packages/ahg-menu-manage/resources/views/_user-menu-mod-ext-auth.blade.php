@if($showLogin)
  <div class="dropdown my-2">
    <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
      @php echo $menuLabels['login']; @endphp
    </button>
    <div class="dropdown-menu dropdown-menu-lg-end mt-2" aria-labelledby="user-menu">
      <div>
        <h6 class="dropdown-header">
          {{ __('Have an account?') }}
        </h6>
      </div>
      @if($sf_context->getConfiguration()->isPluginEnabled('arCasPlugin'))
        @php echo $form->renderFormTag(route('cas.login'), ['class' => 'mx-3 my-2']); @endphp
      @php } elseif ($sf_context->getConfiguration()->isPluginEnabled('arOidcPlugin')) { @endphp
        @php echo $form->renderFormTag(route('oidc.login'), ['class' => 'mx-3 my-2']); @endphp
      @endforeach
        @php echo $form->renderHiddenFields(); @endphp
        <button class="btn btn-sm atom-btn-secondary" type="submit">
          @if($sf_context->getConfiguration()->isPluginEnabled('arCasPlugin'))
            {{ __('Log in with CAS') }}
          @php } elseif ($sf_context->getConfiguration()->isPluginEnabled('arOidcPlugin')) { @endphp
            {{ __('Log in with SSO') }}
          @endforeach
        </button>
      </form>
    </div>
  </div>
@php } elseif ($sf_user->isAuthenticated()) { @endphp
  <div class="dropdown my-2">
    <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu" data-bs-toggle="dropdown" aria-expanded="false">
      @php echo $sf_user->user->username; @endphp
    </button>
    <ul class="dropdown-menu dropdown-menu-lg-end mt-2" aria-labelledby="user-menu">
      <li>
        <h6 class="dropdown-header">
          @php echo image_tag($gravatar, ['alt' => '']); @endphp&nbsp;
          {{ __('Hi, %1%', ['%1%' => $sf_user->user->username]) }}
        </h6>
      </li>
      <li>@php echo link_to($menuLabels['myProfile'], [$sf_user->user, 'module' => 'user'], ['class' => 'dropdown-item']); @endphp</li>
      <li>
        @if($sf_context->getConfiguration()->isPluginEnabled('arCasPlugin'))
          @php echo link_to($menuLabels['logout'], ['module' => 'cas', 'action' => 'logout'], ['class' => 'dropdown-item']); @endphp
        @php } elseif ($sf_context->getConfiguration()->isPluginEnabled('arOidcPlugin')) { @endphp
          @php echo link_to($menuLabels['logout'], ['module' => 'oidc', 'action' => 'logout'], ['class' => 'dropdown-item']); @endphp
        @endforeach
      </li>
    </ul>
  </div>
@endforeach
