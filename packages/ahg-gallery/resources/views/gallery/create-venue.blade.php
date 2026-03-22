@extends('theme::layouts.1col')
@section('title', 'Create Venue')
@section('body-class', 'gallery create-venue')
@section('title-block')<h1 class="mb-0">Create Venue</h1>@endsection
@section('content')
<form method="post" action="{{ route('gallery.venues.store') }}">@csrf
<div class="card mb-4"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Venue Details</h5></div>
<div class="card-body"><div class="row">
  <div class="col-md-6 mb-3"><label for="name" class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="name" id="name" class="form-control" required></div>
  <div class="col-md-6 mb-3"><label for="venue_type" class="form-label">Type</label><select name="venue_type" id="venue_type" class="form-select"><option value="gallery">Gallery</option><option value="museum">Museum</option><option value="university">University</option><option value="private">Private</option><option value="other">Other</option></select></div>
  <div class="col-md-6 mb-3"><label for="address" class="form-label">Address</label><input type="text" name="address" id="address" class="form-control"></div>
  <div class="col-md-6 mb-3"><label for="city" class="form-label">City</label><input type="text" name="city" id="city" class="form-control"></div>
  <div class="col-md-4 mb-3"><label for="country" class="form-label">Country</label><input type="text" name="country" id="country" class="form-control"></div>
  <div class="col-md-4 mb-3"><label for="contact_person" class="form-label">Contact Person</label><input type="text" name="contact_person" id="contact_person" class="form-control"></div>
  <div class="col-md-4 mb-3"><label for="email" class="form-label">Email</label><input type="email" name="email" id="email" class="form-control"></div>
  <div class="col-12 mb-3"><label for="notes" class="form-label">Notes</label><textarea name="notes" id="notes" class="form-control" rows="3"></textarea></div>
</div></div></div>
<section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;"><a href="{{ route('gallery.venues') }}" class="btn atom-btn-outline-light">Cancel</a><button type="submit" class="btn atom-btn-outline-light">Save</button></section>
</form>
@endsection
