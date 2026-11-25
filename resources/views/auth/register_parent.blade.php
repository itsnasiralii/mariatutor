@extends('layouts.base')

@section('title', 'Request a Home Tutor in Burewala | Parent Signup')
@section('meta_description', 'Register free as a parent on Maria Tutor Burewala to request CNIC and degree-verified male or female home and online tutors for your children.')

@section('public_content')
<div class="home-layout">
  @include('partials.nav_home')

  <div style="max-width: 620px; margin: 30px auto 60px; padding: 0 16px;">
    <!-- Hero Card -->
    <div style="background: linear-gradient(135deg, #7c2d12 0%, #c2410c 60%, #ea580c 100%); border-radius: 16px; padding: 32px 28px; color: #fff; margin-bottom: 24px; box-shadow: 0 10px 25px -5px rgba(234, 88, 12, 0.3);">
      <div style="display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,0.2); padding:4px 12px; border-radius:99px; font-size:0.78rem; font-weight:700; margin-bottom:10px;">
        <span aria-hidden="true">👨‍👩‍👧</span> PARENT &amp; GUARDIAN ENROLLMENT
      </div>
      <h1 style="font-size: 1.8rem; font-weight: 800; line-height: 1.2; margin-bottom: 8px; color: #fff;">
        Find a Verified Home or Online Tutor in Burewala
      </h1>
      <p style="color: #fed7aa; font-size: 0.92rem; margin: 0; line-height: 1.5;">
        Register in 1 minute to request subject-specialist tutors for your child. Your phone number and home address remain confidential.
      </p>
    </div>

    <!-- Form Card -->
    <div class="card" style="padding: 32px 28px;">
      @if(session('error'))
        <div class="alert alert-error" role="alert">{{ session('error') }}</div>
      @endif
      @if($errors->any())
        <div class="alert alert-error" role="alert">
          <strong>Please correct the following:</strong>
          <ul>
            @foreach($errors->all() as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ route('auth.register_parent.post') }}">
        @csrf

        <div class="form-group">
          <label for="parent-name">Parent / Guardian Full Name *</label>
          <input id="parent-name" name="full_name" value="{{ old('full_name') }}" required maxlength="120" placeholder="e.g. Chaudhry Tariq / Dr. Muhammad Asif">
          <small>Your name or family contact person.</small>
        </div>

        <div class="form-group">
          <label for="parent-cnic">Pakistani CNIC Number (Optional)</label>
          <input id="parent-cnic" name="cnic" value="{{ old('cnic') }}" maxlength="15" placeholder="36402-XXXXXXX-X">
          <small>Optional 13-digit CNIC for direct CNIC login (stored only as a one-way cryptographic hash).</small>
        </div>

        <div class="form-group">
          <label for="parent-phone">Phone / WhatsApp Number *</label>
          <input id="parent-phone" name="phone" value="{{ old('phone') }}" required maxlength="32" placeholder="0300-7654321">
          <small>Protected. Shared only by Maria Tutor coordination staff when authorized.</small>
        </div>

        <div class="form-group">
          <label for="parent-email">Email Address (Optional)</label>
          <input id="parent-email" type="email" name="email" value="{{ old('email') }}" maxlength="254" placeholder="parent@example.com">
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
          <label for="parent-password">Create Password *</label>
          <input id="parent-password" type="password" name="password" required minlength="10" autocomplete="new-password" placeholder="Min. 10 chars with uppercase, lowercase &amp; number">
          <small>Must be at least 10 characters and include uppercase, lowercase, and at least one number.</small>
        </div>

        <button type="submit" class="btn" style="width:100%; justify-content:center; background:#ea580c; color:#fff; font-weight:700; padding:12px; font-size:1rem;">
          Create Parent Account &amp; Proceed →
        </button>
      </form>

      <div class="auth-links">
        Already have an account? <a href="{{ route('auth.parent_login') }}" style="font-weight:700; color:#ea580c;">Sign in to Parent Portal</a>
      </div>
    </div>
  </div>
</div>
@endsection
