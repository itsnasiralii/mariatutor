@extends('layouts.base')

@section('title', 'Join as a Home Tutor in Burewala | Maria Tutor')
@section('meta_description', 'Apply as a home or online tutor in Burewala, Punjab. Create your Maria Tutor profile, upload degree documents, and receive student leads near you.')

@section('public_content')
<div class="home-layout">
  @include('partials.nav_home')

  <div style="max-width: 720px; margin: 30px auto 60px; padding: 0 16px;">
    <!-- Hero Card -->
    <div style="background: linear-gradient(135deg, #14532d 0%, #166534 60%, #16a34a 100%); border-radius: 16px; padding: 32px 28px; color: #fff; margin-bottom: 24px; box-shadow: 0 10px 25px -5px rgba(22, 163, 74, 0.3);">
      <div style="display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,0.2); padding:4px 12px; border-radius:99px; font-size:0.78rem; font-weight:700; margin-bottom:10px;">
        <span aria-hidden="true">🎓</span> TEACHER &amp; TUTOR REGISTRATION
      </div>
      <h1 style="font-size: 1.8rem; font-weight: 800; line-height: 1.2; margin-bottom: 8px; color: #fff;">
        Teach and Earn Across Burewala
      </h1>
      <p style="color: #bbf7d0; font-size: 0.92rem; margin: 0; line-height: 1.5;">
        Join Burewala's home and online tuition marketplace. Build your verified tutor portfolio and receive targeted student leads in your nearby sectors.
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

      <form method="POST" action="{{ route('auth.register_tutor.post') }}">
        @csrf

        <h2 style="font-size:1.1rem; color:#166534; margin-bottom:14px; border-bottom:2px solid #dcfce7; padding-bottom:8px;">1. Personal &amp; Identity Credentials</h2>

        <div class="form-row-2" style="margin-bottom:18px;">
          <div>
            <label for="tut-name">Full Name *</label>
            <input id="tut-name" name="full_name" value="{{ old('full_name') }}" required maxlength="120" placeholder="e.g. Prof. Bilal / Ms. Ayesha">
          </div>

          <div>
            <label for="tut-gender">Gender *</label>
            <select id="tut-gender" name="gender" required>
              <option value="Female" {{ old('gender') === 'Female' ? 'selected' : '' }}>Female</option>
              <option value="Male" {{ old('gender') === 'Male' ? 'selected' : '' }}>Male</option>
              <option value="Prefer not to say" {{ old('gender') === 'Prefer not to say' ? 'selected' : '' }}>Prefer not to say</option>
            </select>
          </div>
        </div>

        <div class="form-row-2" style="margin-bottom:18px;">
          <div>
            <label for="tut-cnic">Pakistani CNIC Number *</label>
            <input id="tut-cnic" name="cnic" value="{{ old('cnic') }}" required placeholder="36402-XXXXXXX-X" maxlength="15">
            <small>13 digits. Hashed cryptographically; raw CNIC is never stored.</small>
          </div>

          <div>
            <label for="tut-phone">Phone / WhatsApp *</label>
            <input id="tut-phone" name="phone" value="{{ old('phone') }}" required maxlength="32" placeholder="0300-1234567">
          </div>
        </div>

        <div class="form-row-2" style="margin-bottom:24px;">
          <div>
            <label for="tut-email">Email (Optional)</label>
            <input id="tut-email" type="email" name="email" value="{{ old('email') }}" maxlength="254" placeholder="tutor@example.com">
          </div>

          <div>
            <label for="tut-pass">Password *</label>
            <input id="tut-pass" type="password" name="password" required minlength="10" autocomplete="new-password" placeholder="Min. 10 chars (Aa + number)">
            <small>At least 10 characters with uppercase, lowercase, and a number.</small>
          </div>
        </div>

        <h2 style="font-size:1.1rem; color:#166534; margin-bottom:14px; border-bottom:2px solid #dcfce7; padding-bottom:8px;">2. Qualifications &amp; Experience</h2>

        <div class="form-group">
          <label for="tut-qual">Highest Qualification</label>
          <input id="tut-qual" name="qualification" value="{{ old('qualification') }}" maxlength="200" placeholder="e.g. M.Sc Physics (Govt College Burewala) or BS Computer Science">
        </div>

        <div class="form-group">
          <label for="tut-exp">Teaching Experience</label>
          <textarea id="tut-exp" name="experience" rows="3" maxlength="500" placeholder="e.g. 5 years teaching FSc Physics and Mathematics.">{{ old('experience') }}</textarea>
        </div>

        <div class="form-row-2" style="margin-bottom:24px;">
          <div>
            <label for="tut-fmin">Min Expected Monthly Fee (PKR)</label>
            <input id="tut-fmin" type="number" name="expected_fee_min" value="{{ old('expected_fee_min', 12000) }}" min="0" max="500000" step="500">
          </div>

          <div>
            <label for="tut-fmax">Max Expected Monthly Fee (PKR)</label>
            <input id="tut-fmax" type="number" name="expected_fee_max" value="{{ old('expected_fee_max', 25000) }}" min="0" max="500000" step="500">
          </div>
        </div>

        <h2 style="font-size:1.1rem; color:#166534; margin-bottom:14px; border-bottom:2px solid #dcfce7; padding-bottom:8px;">3. Preferred Burewala Sectors &amp; Subjects</h2>

        <div style="margin-bottom:18px;">
          <label style="margin-bottom:8px;"><span aria-hidden="true">📍</span> Burewala Sectors You Can Visit:</label>
          <div class="check-grid">
            @foreach($areas as $area)
              <label style="background:#f8fafc; padding:6px 10px; border-radius:6px; border:1px solid #e2e8f0;">
                <input type="checkbox" name="catalog_ids[]" value="{{ $area->id }}" {{ in_array($area->id, (array) old('catalog_ids', [])) ? 'checked' : '' }}>
                {{ $area->name }}
              </label>
            @endforeach
          </div>
        </div>

        <div style="margin-bottom:24px;">
          <label style="margin-bottom:8px;"><span aria-hidden="true">📖</span> Subjects You Teach:</label>
          <div class="check-grid">
            @foreach($subjects as $subj)
              <label style="background:#f8fafc; padding:6px 10px; border-radius:6px; border:1px solid #e2e8f0;">
                <input type="checkbox" name="catalog_ids[]" value="{{ $subj->id }}" {{ in_array($subj->id, (array) old('catalog_ids', [])) ? 'checked' : '' }}>
                {{ $subj->name }}
              </label>
            @endforeach
          </div>
        </div>

        <button type="submit" class="btn" style="width:100%; justify-content:center; background:#16a34a; color:#fff; font-weight:700; padding:12px; font-size:1rem;">
          Complete Registration &amp; Get Tutor Code →
        </button>
      </form>

      <div class="auth-links">
        Already registered? <a href="{{ route('auth.tutor_login') }}" style="font-weight:700; color:#16a34a;">Sign in to Tutor Portal</a>
      </div>
    </div>
  </div>
</div>
@endsection
