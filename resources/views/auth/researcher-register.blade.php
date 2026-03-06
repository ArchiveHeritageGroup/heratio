@extends('theme::layouts.1col')

@section('title', 'Researcher Registration')
@section('body-class', 'user register researcher')

@section('content')

<h1><i class="fas fa-user-plus text-primary me-2"></i>Researcher Registration</h1>

<div class="row justify-content-center">
  <div class="col-lg-10">
    <div class="card">
      <div class="card-header bg-primary text-white">
        <i class="fas fa-clipboard-list me-2"></i>Create Your Research Account
      </div>
      <div class="card-body">
        <div class="alert alert-info">
          <i class="fas fa-info-circle me-2"></i>
          Register to access the reading room, request materials, and save your research.
          Your account will be reviewed and activated within 1-2 business days.
        </div>

        @if($errors->any())
          <div class="alert alert-danger">
            @foreach($errors->all() as $error)
              <div>{{ $error }}</div>
            @endforeach
          </div>
        @endif

        <form method="POST" action="{{ route('researcher.register') }}" id="researcherRegisterForm">
          @csrf

          <div class="row">
            {{-- Account Information --}}
            <div class="col-md-6">
              <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-key me-2"></i>Account Information</h5>

              <div class="mb-3">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control @error('username') is-invalid @enderror"
                       value="{{ old('username') }}" required minlength="3" placeholder="Choose a username">
                <small class="text-muted">At least 3 characters, letters and numbers only</small>
                @error('username')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="mb-3">
                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" required placeholder="your.email@example.com">
                @error('email')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="mb-3">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                       required minlength="8" id="password">
                <small class="text-muted">At least 8 characters</small>
                @error('password')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="mb-3">
                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" name="password_confirmation" class="form-control" required minlength="8">
              </div>

              <h5 class="mb-3 mt-4 border-bottom pb-2"><i class="fas fa-id-card me-2"></i>Identification</h5>

              <div class="row mb-3">
                <div class="col-md-5">
                  <label class="form-label">ID Type</label>
                  <select name="id_type" class="form-select">
                    <option value="">--</option>
                    <option value="passport" {{ old('id_type') == 'passport' ? 'selected' : '' }}>Passport</option>
                    <option value="national_id" {{ old('id_type') == 'national_id' ? 'selected' : '' }}>National ID</option>
                    <option value="drivers_license" {{ old('id_type') == 'drivers_license' ? 'selected' : '' }}>Driver's License</option>
                    <option value="student_card" {{ old('id_type') == 'student_card' ? 'selected' : '' }}>Student Card</option>
                  </select>
                </div>
                <div class="col-md-7">
                  <label class="form-label">ID Number</label>
                  <input type="text" name="id_number" class="form-control" value="{{ old('id_number') }}">
                </div>
              </div>
            </div>

            {{-- Personal Information --}}
            <div class="col-md-6">
              <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-user me-2"></i>Personal Information</h5>

              <div class="row mb-3">
                <div class="col-md-3">
                  <label class="form-label">Title</label>
                  <select name="title" class="form-select">
                    <option value="">--</option>
                    <option value="Mr" {{ old('title') == 'Mr' ? 'selected' : '' }}>Mr</option>
                    <option value="Mrs" {{ old('title') == 'Mrs' ? 'selected' : '' }}>Mrs</option>
                    <option value="Ms" {{ old('title') == 'Ms' ? 'selected' : '' }}>Ms</option>
                    <option value="Dr" {{ old('title') == 'Dr' ? 'selected' : '' }}>Dr</option>
                    <option value="Prof" {{ old('title') == 'Prof' ? 'selected' : '' }}>Prof</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">First Name <span class="text-danger">*</span></label>
                  <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror"
                         value="{{ old('first_name') }}" required>
                  @error('first_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <div class="col-md-5">
                  <label class="form-label">Last Name <span class="text-danger">*</span></label>
                  <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror"
                         value="{{ old('last_name') }}" required>
                  @error('last_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="tel" name="phone" class="form-control" value="{{ old('phone') }}">
              </div>

              <h5 class="mb-3 mt-4 border-bottom pb-2"><i class="fas fa-university me-2"></i>Affiliation</h5>

              <div class="mb-3">
                <label class="form-label">Affiliation Type <span class="text-danger">*</span></label>
                <select name="affiliation_type" class="form-select" required>
                  <option value="independent" {{ old('affiliation_type', 'independent') == 'independent' ? 'selected' : '' }}>Independent Researcher</option>
                  <option value="academic" {{ old('affiliation_type') == 'academic' ? 'selected' : '' }}>Academic Institution</option>
                  <option value="government" {{ old('affiliation_type') == 'government' ? 'selected' : '' }}>Government</option>
                  <option value="private" {{ old('affiliation_type') == 'private' ? 'selected' : '' }}>Private Organization</option>
                  <option value="student" {{ old('affiliation_type') == 'student' ? 'selected' : '' }}>Student</option>
                  <option value="other" {{ old('affiliation_type') == 'other' ? 'selected' : '' }}>Other</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Institution</label>
                <input type="text" name="institution" class="form-control"
                       value="{{ old('institution') }}" placeholder="University, Organization, etc.">
              </div>

              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Department</label>
                  <input type="text" name="department" class="form-control" value="{{ old('department') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Position</label>
                  <input type="text" name="position" class="form-control" value="{{ old('position') }}">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">ORCID ID</label>
                <input type="text" name="orcid_id" class="form-control"
                       value="{{ old('orcid_id') }}" placeholder="0000-0000-0000-0000">
              </div>
            </div>
          </div>

          {{-- Research Information --}}
          <div class="row mt-3">
            <div class="col-12">
              <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-flask me-2"></i>Research Information</h5>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Research Interests</label>
                <textarea name="research_interests" class="form-control" rows="3"
                          placeholder="Describe your research interests...">{{ old('research_interests') }}</textarea>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Current Project</label>
                <textarea name="current_project" class="form-control" rows="3"
                          placeholder="Describe your current research project...">{{ old('current_project') }}</textarea>
              </div>
            </div>
          </div>

          <hr class="my-4">

          <div class="d-flex justify-content-between align-items-center">
            <div>
              <span class="text-muted">Already have an account?</span>
              <a href="{{ route('login') }}">Login here</a>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="fas fa-paper-plane me-2"></i>Submit Registration
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
