@extends('layouts.base')

@section('title', 'Parent & Guardian Registration — Maria Tutor')

@section('public_content')
<div class="home-layout">
  @include('partials.nav_home')

  <div style="max-width: 620px; margin: 30px auto 60px; padding: 0 16px;">
    <!-- Hero Card -->
    <div style="background: linear-gradient(135deg, #7c2d12 0%, #c2410c 60%, #ea580c 100%); border-radius: 16px; padding: 32px 28px; color: #fff; margin-bottom: 24px; box-shadow: 0 10px 25px -5px rgba(234, 88, 12, 0.3);">
      <div style="display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,0.2); padding:4px 12px; border-radius:99px; font-size:0.78rem; font-weight:700; margin-bottom:10px;">
        👨‍👩‍👧 PARENT &amp; GUARDIAN ENROLLMENT
      </div>
      <h1 style="font-size: 1.8rem; font-weight: 800; line-height: 1.2; margin-bottom: 8px;">
        Find the Best Home or Online Tutor in Burewala
      </h1>
      <p style="color: #fed7aa; font-size: 0.92rem; margin: 0; line-height: 1.5;">
        Register in 1 minute to request verified, subject-specialist tutors for your child. Your phone number and exact address remain strictly confidential.
      </p>
    </div>

    <!-- Form Card -->
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:32px 28px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.04);">

      @if(session('error'))
        <div class="alert alert-error" style="margin-bottom: 20px;">{{ session('error') }}</div>
      @endif

      <form method="POST" action="{{ route('auth.register_parent.post') }}">
        @csrf

        <div style="display:grid; grid-template-columns: 1fr; gap:18px; margin-bottom:24px;">

          <div>
            <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">
              Parent / Guardian Full Name *
            </label>
            <input name="full_name" value="{{ old('full_name') }}" required maxlength="120" placeholder="e.g. Chaudhry Tariq / Dr. Muhammad Asif"
                   style="width:100%; padding:11px 14px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.92rem;">
            <small style="color:#64748b; font-size:0.78rem; margin-top:4px; display:block;">Your name or family contact person.</small>
          </div>

          <div>
            <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">
              Pakistani CNIC Number (Optional)
            </label>
            <input name="cnic" value="{{ old('cnic') }}" maxlength="15" placeholder="36402-XXXXXXX-X"
                   style="width:100%; padding:11px 14px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.92rem;">
            <small style="color:#64748b; font-size:0.78rem; margin-top:4px; display:block;">Used for verified direct CNIC login.</small>
          </div>

          <div>
            <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">
              Phone / WhatsApp Number *
            </label>
            <input name="phone" value="{{ old('phone') }}" required placeholder="0300-7654321"
                   style="width:100%; padding:11px 14px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.92rem;">
            <small style="color:#64748b; font-size:0.78rem; margin-top:4px; display:block;">Strictly protected. Used only by Maria Tutor coordination team.</small>
          </div>

          <div>
            <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">
              Email Address (Optional)
            </label>
            <input type="email" name="email" value="{{ old('email') }}" placeholder="parent@example.com"
                   style="width:100%; padding:11px 14px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.92rem;">
          </div>

          <div>
            <label style="display:block; font-size:0.88rem; font-weight:600; color:#334155; margin-bottom:6px;">
              Create Password *
            </label>
            <input type="password" name="password" required minlength="6" placeholder="Choose a secure password"
                   style="width:100%; padding:11px 14px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.92rem;">
          </div>

        </div>

        <button type="submit" class="btn" style="width:100%; justify-content:center; background:#ea580c; color:#fff; font-weight:700; padding:12px; font-size:1rem; border-radius:8px;">
          Create Parent Account &amp; Proceed →
        </button>
      </form>

      <div style="margin-top:20px; text-align:center; font-size:0.88rem; color:#64748b;">
        Already have an account? <a href="{{ route('auth.parent_login') }}" style="color:#ea580c; font-weight:700;">Log in here</a>
      </div>
    </div>
  </div>
</div>
@endsection
