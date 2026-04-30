@extends('theme::layout')

@section('title', $definition ? 'Edit Field: ' . $definition->field_label : 'Add Custom Field')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>
            <i class="bi bi-input-cursor-text"></i>
            {{ $definition ? 'Edit Field: ' . $definition->field_label : 'Add Custom Field' }}
        </h2>
        <a href="{{ route('customFields.index') }}" class="atom-btn-white"><i class="bi bi-arrow-left"></i> {{ __('Back to List') }}</a>
    </div>

    <div class="card">
        <div class="card-body">
            @include('ahg-custom-fields::admin._field-form', [
                'definition' => $definition,
                'entityTypes' => $entityTypes,
                'fieldTypes' => $fieldTypes,
            ])
        </div>
    </div>
</div>
@endsection
