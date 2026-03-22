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
      @php echo $form->renderFormTag(route('cas.login'), ['class' => 'mx-3 my-2']); @endphp
        @php echo $form->renderHiddenFields(); @endphp
        <button class="btn btn-sm atom-btn-secondary" type="submit">
            {{ __('Log in with CAS') }}
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
      <li>@php echo link_to($menuLabels['logout'], ['module' => 'cas', 'action' => 'logout'], ['class' => 'dropdown-item']); @endphp</li>
    </ul>
  </div>
@endforeach
