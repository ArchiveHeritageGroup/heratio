@php decorate_with('layout_1col'); @endphp

@php slot('title'); @endphp

  @if(isset($preview))
    <div class="copyright-statement-preview alert alert-info">
      {{ __('Copyright statement preview') }}
    </div>
  @endforeach

  <h1>@php echo render_title($resource); @endphp</h1>

@php end_slot(); @endphp

<div class="page">

  <div class="p-3">
    @php echo render_value_html($sf_data->getRaw('copyrightStatement')); @endphp
  </div>

</div>

@php slot('after-content'); @endphp
  <form method="get">
    <input type="hidden" name="token" value="@php echo $accessToken; @endphp">
    @if(isset($preview))
      <ul class="actions mb-3 nav gap-2">
        <li><button class="btn atom-btn-outline-success" type="submit" disabled="disabled">{{ __('Agree') }}</button></li>
        <li>@php echo link_to(__('Close'), ['module' => 'settings', 'action' => 'permissions'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
      </ul>
    @php } else { @endphp
      <section class="actions mb-3">
        <button class="btn atom-btn-outline-success" type="submit">{{ __('Agree') }}</button>
      </section>
    @endforeach
  </form>
@php end_slot(); @endphp
