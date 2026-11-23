@if(session('success'))
    <div class="alert alert-success" role="alert"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning" role="alert"><i class="fas fa-exclamation-circle me-2"></i>{{ session('warning') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger" role="alert"><i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
