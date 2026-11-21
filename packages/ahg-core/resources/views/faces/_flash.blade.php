@if(session('notice'))<div class="alert alert-success">{{ session('notice') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
