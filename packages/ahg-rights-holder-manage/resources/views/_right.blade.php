<div class="field @php echo render_b5_show_field_css_classes(); @endphp">
  @php echo render_b5_show_label(__('Related right')); @endphp
  <div class="@php echo render_b5_show_value_css_classes(); @endphp">
    @if(QubitAcl::check($relatedObject, 'update'))
      <div>
        <a href="@php echo url_for(['module' => 'right', 'action' => 'edit', 'slug' => $resource->slug]); @endphp">{{ __('Edit') }}</a> |
        <a href="@php echo url_for(['module' => 'right', 'action' => 'delete', 'slug' => $resource->slug]); @endphp">{{ __('Delete') }}</a>
      </div>
    @endforeach

    <div>
      @if(isset($inherit))
        @php echo link_to(render_title($inherit), [$inherit, 'module' => 'informationobject'], ['title' => __('Inherited from %1%', ['%1%' => $inherit])]); @endphp
      @endforeach

      @php echo render_show(__('Basis'), render_value_inline($resource->basis), ['isSubField' => true]); @endphp

      @php echo render_show(__('Start date'), render_value_inline(Qubit::renderDate($resource->startDate)), ['isSubField' => true]); @endphp

      @php echo render_show(__('End date'), render_value_inline(Qubit::renderDate($resource->endDate)), ['isSubField' => true]); @endphp

      @php echo render_show(__('Documentation Identifier Type'), render_value_inline($resource->identifierType), ['isSubField' => true]); @endphp

      @php echo render_show(__('Documentation Identifier Value'), render_value_inline($resource->identifierValue), ['isSubField' => true]); @endphp

      @php echo render_show(__('Documentation Identifier Role'), render_value_inline($resource->identifierRole), ['isSubField' => true]); @endphp

      @if(isset($resource->rightsHolder))
        @php echo render_show(__('Rights holder'), link_to(render_value_inline($resource->rightsHolder), [$resource->rightsHolder, 'module' => 'rightsholder']), ['isSubField' => true]); @endphp
      @endforeach

      @php echo render_show(__('Rights note(s)'), render_value_inline($resource->getRightsNote(['cultureFallback' => true])), ['isSubField' => true]); @endphp

      @if(QubitTerm::RIGHT_BASIS_COPYRIGHT_ID == $resource->basisId)

        @php echo render_show(__('Copyright status'), render_value_inline($resource->copyrightStatus), ['isSubField' => true]); @endphp

        @php echo render_show(__('Copyright status determination date'), render_value_inline($resource->copyrightStatusDate), ['isSubField' => true]); @endphp

        @php echo render_show(__('Copyright jurisdiction'), render_value_inline($resource->copyrightJurisdiction), ['isSubField' => true]); @endphp

        @php echo render_show(__('Copyright note'), render_value_inline($resource->getCopyrightNote(['cultureFallback' => true])), ['isSubField' => true]); @endphp

      @php } elseif (QubitTerm::RIGHT_BASIS_LICENSE_ID == $resource->basisId) { @endphp

        @php echo render_show(__('License identifier'), render_value_inline($resource->getIdentifierValue(['cultureFallback' => true])), ['isSubField' => true]); @endphp

        @php echo render_show(__('License terms'), render_value_inline($resource->getLicenseTerms(['cultureFallback' => true])), ['isSubField' => true]); @endphp

        @php echo render_show(__('License note'), render_value_inline($resource->getLicenseNote(['cultureFallback' => true])), ['isSubField' => true]); @endphp

      @php } elseif (QubitTerm::RIGHT_BASIS_STATUTE_ID == $resource->basisId) { @endphp

        @php echo render_show(__('Statute jurisdiction'), render_value_inline($resource->getStatuteJurisdiction(['cultureFallback' => true])), ['isSubField' => true]); @endphp

        @if(null !== $statuteCitation = $resource->statuteCitation)
          @php echo render_show(__('Statute citation'), render_value_inline($statuteCitation->getName(['cultureFallback' => true])), ['isSubField' => true]); @endphp
        @endforeach

        @php echo render_show(__('Statute determination date'), render_value_inline($resource->statuteDeterminationDate), ['isSubField' => true]); @endphp

        @php echo render_show(__('Statute note'), render_value_inline($resource->getStatuteNote(['cultureFallback' => true])), ['isSubField' => true]); @endphp

      @endforeach

      <blockquote class="border-bottom m-0 mt-1">
        @foreach($resource->grantedRights as $grantedRight)
          <div class="border border-bottom-0 px-2 py-1">
            @php echo render_show(__('Act'), render_value_inline($grantedRight->act), ['isSubField' => true]); @endphp
            @php echo render_show(__('Restriction'), render_value_inline(QubitGrantedRight::getRestrictionString($grantedRight->restriction)), ['isSubField' => true]); @endphp
            @php echo render_show(__('Start date'), render_value_inline(Qubit::renderDate($grantedRight->startDate)), ['isSubField' => true]); @endphp
            @php echo render_show(__('End date'), render_value_inline(Qubit::renderDate($grantedRight->endDate)), ['isSubField' => true]); @endphp
            @php echo render_show(__('Notes'), render_value_inline($grantedRight->notes), ['isSubField' => true]); @endphp
          </div>
        @endforeach
      </blockquote>
    </div>
  </div>
</div>
