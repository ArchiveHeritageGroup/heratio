<section class="contact-info">
  @if(!empty($contactInformation->contactPerson))
    <div class="field @php echo render_b5_show_field_css_classes(); @endphp">
      @php echo render_b5_show_label(''); @endphp
      <div class="agent @php echo render_b5_show_value_css_classes(); @endphp">
        <span class="text-primary">
          @php echo render_value_inline($contactInformation->contactPerson); @endphp
        </span>
        @if($contactInformation->primaryContact)
          <span class="primary-contact">
            {{ __('Primary contact') }}
          </span>
        @endforeach
      </div>
    </div>
  @endforeach

  @php echo render_show(__('Type'), render_value_inline($contactInformation->getContactType(['cultureFallback' => true])), ['valueClass' => 'type']); @endphp

  <div class="field adr @php echo render_b5_show_field_css_classes(); @endphp">
    @php echo render_b5_show_label(__('Address')); @endphp
    <div class="@php echo render_b5_show_value_css_classes(); @endphp">

      @php echo render_show(__('Street address'), render_value_inline($contactInformation->streetAddress), ['isSubField' => true]); @endphp

      @php echo render_show(__('Locality'), render_value_inline($contactInformation->getCity(['cultureFallback' => true])), ['isSubField' => true]); @endphp

      @php echo render_show(__('Region'), render_value_inline($contactInformation->getRegion(['cultureFallback' => true])), ['isSubField' => true]); @endphp

      @php echo render_show(__('Country name'), format_country($contactInformation->countryCode), ['isSubField' => true]); @endphp

      @php echo render_show(__('Postal code'), render_value_inline($contactInformation->postalCode), ['isSubField' => true]); @endphp

    </div>

  </div>

  @php echo render_show(__('Telephone'), render_value_inline($contactInformation->telephone), ['valueClass' => 'tel']); @endphp

  @php echo render_show(__('Fax'), render_value_inline($contactInformation->fax), ['valueClass' => 'fax']); @endphp

  @php echo render_show(__('Email'), render_value_inline($contactInformation->email), ['valueClass' => 'email']); @endphp

  @php echo render_show(__('URL'), render_value_inline($contactInformation->website), ['valueClass' => 'url']); @endphp

  @php echo render_show(__('Note'), render_value($contactInformation->getNote(['cultureFallback' => true])), ['valueClass' => 'note']); @endphp
</section>
