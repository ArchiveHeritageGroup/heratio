@php slot('google_analytics'); @endphp
  ga('set', 'dimension@php echo $dimensionIndex; @endphp', '@php echo $repository->getAuthorizedFormOfName(['sourceCulture' => true]); @endphp');
@php end_slot(); @endphp
