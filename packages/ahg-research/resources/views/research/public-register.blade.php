{{-- Public Researcher Registration (no login required) - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'profile'])
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <h1 class="mb-4"><i class="fas fa-user-plus me-2"></i>Researcher Registration</h1>
  <p class="text-muted mb-4">Create an account and register as a researcher to access the reading rooms and research tools.</p>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ e($error) }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('research.publicRegister.store') }}" method="POST">
    @csrf

    {{-- Account Creation --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-key me-2"></i>Account Details</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" id="username" class="form-control" value="{{ old('username') }}" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" required>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" id="password" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
          </div>
        </div>
      </div>
    </div>

    {{-- Personal Information --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-user me-2"></i>Personal Information</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-2 mb-3">
            <label for="title" class="form-label">Title</label>
            <select name="title" id="title" class="form-select">
              <option value="">-- Select --</option>
              <option value="Mr" {{ old('title') === 'Mr' ? 'selected' : '' }}>Mr</option>
              <option value="Mrs" {{ old('title') === 'Mrs' ? 'selected' : '' }}>Mrs</option>
              <option value="Ms" {{ old('title') === 'Ms' ? 'selected' : '' }}>Ms</option>
              <option value="Dr" {{ old('title') === 'Dr' ? 'selected' : '' }}>Dr</option>
              <option value="Prof" {{ old('title') === 'Prof' ? 'selected' : '' }}>Prof</option>
            </select>
          </div>
          <div class="col-md-5 mb-3">
            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
            <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name') }}" required>
          </div>
          <div class="col-md-5 mb-3">
            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
            <input type="text" name="last_name" id="last_name" class="form-control" value="{{ old('last_name') }}" required>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="phone" class="form-label">Phone</label>
            <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone') }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Identification --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-id-card me-2"></i>Identification</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="id_type" class="form-label">ID Type <span class="text-danger">*</span></label>
            <select name="id_type" id="id_type" class="form-select" required>
              <option value="">-- Select --</option>
              <option value="sa_id" {{ old('id_type') === 'sa_id' ? 'selected' : '' }}>SA ID</option>
              <option value="passport" {{ old('id_type') === 'passport' ? 'selected' : '' }}>Passport</option>
              <option value="drivers_license" {{ old('id_type') === 'drivers_license' ? 'selected' : '' }}>Driver's License</option>
              <option value="student_id" {{ old('id_type') === 'student_id' ? 'selected' : '' }}>Student ID</option>
              <option value="other" {{ old('id_type') === 'other' ? 'selected' : '' }}>Other</option>
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label for="id_number" class="form-label">ID Number <span class="text-danger">*</span></label>
            <input type="text" name="id_number" id="id_number" class="form-control" value="{{ old('id_number') }}" required>
          </div>
          <div class="col-md-4 mb-3">
            <label for="student_id" class="form-label">Student ID</label>
            <input type="text" name="student_id" id="student_id" class="form-control" value="{{ old('student_id') }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Affiliation --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-university me-2"></i>Affiliation</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="affiliation_type" class="form-label">Affiliation Type</label>
            <select name="affiliation_type" id="affiliation_type" class="form-select">
              <option value="">-- Select --</option>
              <option value="academic" {{ old('affiliation_type') === 'academic' ? 'selected' : '' }}>Academic</option>
              <option value="government" {{ old('affiliation_type') === 'government' ? 'selected' : '' }}>Government</option>
              <option value="independent" {{ old('affiliation_type') === 'independent' ? 'selected' : '' }}>Independent</option>
              <option value="corporate" {{ old('affiliation_type') === 'corporate' ? 'selected' : '' }}>Corporate</option>
              <option value="student" {{ old('affiliation_type') === 'student' ? 'selected' : '' }}>Student</option>
              <option value="other" {{ old('affiliation_type') === 'other' ? 'selected' : '' }}>Other</option>
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label for="institution" class="form-label">Institution</label>
            <input type="text" name="institution" id="institution" class="form-control" value="{{ old('institution') }}">
          </div>
          <div class="col-md-4 mb-3">
            <label for="department" class="form-label">Department</label>
            <input type="text" name="department" id="department" class="form-control" value="{{ old('department') }}">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="position" class="form-label">Position</label>
            <input type="text" name="position" id="position" class="form-control" value="{{ old('position') }}">
          </div>
          <div class="col-md-6 mb-3">
            <label for="orcid_id" class="form-label">ORCID iD</label>
            <input type="text" name="orcid_id" id="orcid_id" class="form-control" value="{{ old('orcid_id') }}" placeholder="0000-0000-0000-0000">
          </div>
        </div>
      </div>
    </div>

    {{-- Research --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-microscope me-2"></i>Research</div>
      <div class="card-body">
        <div class="mb-3">
          <label for="research_interests" class="form-label">Research Interests</label>
          <textarea name="research_interests" id="research_interests" class="form-control" rows="4">{{ old('research_interests') }}</textarea>
        </div>
        <div class="mb-3">
          <label for="current_project" class="form-label">Current Project</label>
          <textarea name="current_project" id="current_project" class="form-control" rows="4">{{ old('current_project') }}</textarea>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between">
      <a href="{{ route('login') }}" class="btn btn-link">Already have an account? Log in</a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-paper-plane me-1"></i>Create Account &amp; Register
      </button>
    </div>
  </form>
@endsection
