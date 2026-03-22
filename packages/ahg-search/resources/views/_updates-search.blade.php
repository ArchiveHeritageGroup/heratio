{{-- Partial: Updates search form --}}
<div class="updates-search mb-3"><form method="GET" action="{{ route('search.descriptionUpdates') }}"><div class="input-group"><input type="text" class="form-control" name="query" value="{{ request('query') }}" placeholder="Search recent updates..."><button type="submit" class="btn atom-btn-white"><i class="fas fa-search"></i></button></div></form></div>
