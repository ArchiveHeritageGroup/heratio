<article class="search-result row g-0 p-3 border-bottom">
  @if(!empty($doc['hasDigitalObject']))
    <div class="col-12 col-lg-3 pb-2 pb-lg-0 pe-lg-3">
      <a href="@php echo url_for(
          ['module' => 'actor', 'slug' => $doc['slug']]
      ); @endphp">
        @php echo image_tag(
            $doc['digitalObject']['thumbnailPath']
            ?: QubitDigitalObject::getGenericIconPathByMediaTypeId(
                $doc['digitalObject']['mediaTypeId'] ?: null
            ),
            [
                'alt' => $doc['digitalObject']['digitalObjectAltText'] ?: strip_markdown(
                    get_search_i18n(
                        $doc,
                        'authorizedFormOfName',
                        ['allowEmpty' => false, 'culture' => $culture]
                    )
                ),
                'class' => 'img-thumbnail',
            ]
        ); @endphp
      </a>
    </div>
  @endforeach

  <div class="col-12@php echo empty($doc['hasDigitalObject']) ? '' : ' col-lg-9'; @endphp d-flex flex-column gap-1">
    <div class="d-flex align-items-center gap-2 mw-100">
      @php echo link_to(
          render_title(get_search_i18n(
              $doc,
              'authorizedFormOfName',
              ['allowEmpty' => false, 'culture' => $culture]
          )),
          ['module' => 'actor', 'slug' => $doc['slug']],
          ['class' => 'h5 mb-0 text-truncate'],
      ); @endphp

      @php echo get_component('clipboard', 'button', [
          'slug' => $doc['slug'],
          'type' => $clipboardType,
          'wide' => false,
      ]); @endphp
    </div>

    <div class="d-flex flex-column gap-2">
      <div class="d-flex flex-wrap">
        @php $showDash = false; @endphp
        @if(!empty($doc['descriptionIdentifier']))
          <span class="text-primary">
            @php echo $doc['descriptionIdentifier']; @endphp
          </span>
          @php $showDash = true; @endphp
        @endforeach

        @if(
            !empty($doc['entityTypeId'])
            && null !== $termName = term_name($doc['entityTypeId'])
        )
          @if($showDash)
            <span class="text-muted mx-2"> · </span>
          @endforeach
          <span class="text-muted">
            @php echo $termName; @endphp
          </span>
          @php $showDash = true; @endphp
        @endforeach

        @if(strlen($dates = get_search_i18n(
            $doc,
            'datesOfExistence',
            ['culture' => $culture])) > 0
        )
          @if($showDash)
            <span class="text-muted mx-2"> · </span>
          @endforeach
          <span class="text-muted">
            @php echo render_value_inline($dates); @endphp
          </span>
        @endforeach
      </div>

      @if(strlen($history = get_search_i18n(
          $doc,
          'history',
          ['culture' => $culture])) > 0
      )
        <span class="text-block d-none">
          @php echo render_value($history); @endphp
        </span>
      @endforeach
    </div>
  </div>
</article>
