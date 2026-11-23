@extends('layouts.base')

@section('title', 'Tutor & Teacher Registration — Maria Tutor')

@section('public_content')
<div class="home-layout">
  @include('partials.nav_home')

  <div style="max-width: 720px; margin: 30px auto 60px; padding: 0 16px;">
    <!-- Hero Card -->
    <div style="background: linear-gradient(135deg, #14532d 0%, #166534 60%, #16a34a 100%); border-radius: 16px; padding: 32px 28px; color: #fff; margin-bottom: 24px; box-shadow: 0 10px 25px -5px rgba(22, 163, 74, 0.3);">
      <div style="display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,0.2); padding:4px 12px; border-radius:99px; font-size:0.78rem; font-weight:700; margin-bottom:10px;">
        🎓 TEACHER &amp; TUTOR REGISTRATION
      </div>
      <h1 style="font-size: 1.8rem; font-weight: 800; line-height: 1.2; margin-bottom: 8px;">
        Teach and Earn Across Burewala
      </h1>
      <p style="color: #bbf7d0; font-size: 0.92rem; margin: 0; line-height: 1.5;">
        Join Burewala's official home and online tuition marketplace. Build your verified tutor portfolio and receive targeted student leads in your nearby sectors.
      </p>
    </div>

    <!-- Form Card -->
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:32px 28px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.04);">

      @if(session('error'))
        <div class="alert alert-error" style="margin-bottom: 20px;">{{ session('error') }}</div>
      @endif

      <form method="POST" action="{{ route('auth.register_tutor.post') }}">
        @csrf

        <h3 style="font-size:1.1rem; color:#166534; margin-bottom:14px; border-bottom:2px solid #dcfce7; padding-bottom:8px;">1. Personal &amp; Identity Credentials</h3>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:18px;">
          <div>
            <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">Full Name *</label>
            <input name="full_name" value="{{ old('full_name') }}" required placeholder="e.g. Prof. Bilal / Ms. Ayesha"
                   style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
          </div>

          <div>
            <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">Gender *</label>
            <select name="gender" required style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
              <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
              <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
              <option value="Prefer not to say">Prefer not to say</option>
            </select>
          </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:18px;">
          <div>
            <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">Pakistani CNIC Number *</label>
            <input name="cnic" value="{{ old('cnic') }}" required placeholder="36402-XXXXXXX-X" maxlength="15"
                   style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
            <small style="color:#64748b; font-size:0.75rem;">13 digits. Kept strictly confidential.</small>
          </div>

          <div>
            <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">Phone / WhatsApp *</label>
            <input name="phone" value="{{ old('phone') }}" required placeholder="0300-1234567"
                   style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
          </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:24px;">
          <div>
            <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">Email (Optional)</label>
            <input type="email" name="email" value="{{ old('email') }}" placeholder="tutor@example.com"
                   style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
          </div>

          <div>
            <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">Password *</label>
            <input type="password" name="password" required minlength="6" placeholder="Choose a password"
                   style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
          </div>
        </div>

        <h3 style="font-size:1.1rem; color:#166534; margin-bottom:14px; border-bottom:2px solid #dcfce7; padding-bottom:8px;">2. Qualifications &amp; Experience</h3>

        <div style="margin-bottom:18px;">
          <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">Highest Qualification</label>
          <input name="qualification" value="{{ old('qualification') }}" placeholder="e.g. M.Sc Physics (Govt College Burewala) or BS Computer Science"
                 style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
        </div>

        <div style="margin-bottom:18px;">
          <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">Teaching Experience</label>
          <textarea name="experience" rows="3" placeholder="e.g. 5 years teaching FSc Physics at Aspire / KIPS. Mentored students to achieve A+ board grades."
                    style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">{{ old('experience') }}</textarea>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:24px;">
          <div>
            <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">Min Expected Monthly Fee (PKR)</label>
            <input type="number" name="expected_fee_min" value="{{ old('expected_fee_min', 12000) }}" step="1000"
                   style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
          </div>

          <div>
            <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">Max Expected Monthly Fee (PKR)</label>
            <input type="number" name="expected_fee_max" value="{{ old('expected_fee_max', 25000) }}" step="1000"
                   style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
          </div>
        </div>

        <h3 style="font-size:1.1rem; color:#166534; margin-bottom:14px; border-bottom:2px solid #dcfce7; padding-bottom:8px;">3. Preferred Burewala Sectors &amp; Subjects</h3>

        <div style="margin-bottom:18px;">
          <label style="display:block; font-size:0.88rem; font-weight:700; color:#334155; margin-bottom:8px;">📍 Burewala Sectors You Can Visit:</label>
          <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:8px;">
            @foreach($areas as $area)
              <label style="font-size:0.85rem; display:flex; align-items:center; gap:6px; background:#f8fafc; padding:6px 10px; border-radius:6px; border:1px solid #e2e8f0;">
                <input type="checkbox" name="catalog_ids[]" value="{{ $area->id }}">
                {{ $area->name }}
              </label>
            @endforeach
          </div>
        </div>

        <div style="margin-bottom:24px;">
          <label style="display:block; font-size:0.88rem; font-weight:700; color:#334155; margin-bottom:8px;">📖 Subjects You Teach:</label>
          <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap:8px;">
            @foreach($subjects as $subj)
              <label style="font-size:0.85rem; display:flex; align-items:center; gap:6px; background:#f8fafc; padding:6px 10px; border-radius:6px; border:1px solid #e2e8f0;">
                <input type="checkbox" name="catalog_ids[]" value="{{ $subj->id }}">
                {{ $subj->name }}
              </label>
            @endforeach
          </div>
        </div>

        <button type="submit" class="btn" style="width:100%; justify-content:center; background:#16a34a; color:#fff; font-weight:700; padding:12px; font-size:1rem; border-radius:8px;">
          Complete Registration &amp; Get Tutor Code →
        </button>
      </form>

      <div style="margin-top:20px; text-align:center; font-size:0.88rem; color:#64748b;">
        Already registered? <a href="{{ route('auth.tutor_login') }}" style="color:#16a34a; font-weight:700;">Log in here</a>
      </div>
    </div>
  </div>
</div>
@endsection
