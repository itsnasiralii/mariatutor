@extends('layouts.base')

@section('title', 'Sign In — Maria Tutor')

@section('public_content')
<div class="auth-page">
  <div class="auth-card" style="max-width: 480px;">

    <!-- Portal Switch Tabs -->
    <div style="display:flex; gap:6px; margin-bottom: 20px; background: var(--bg, #f1f5f9); padding: 4px; border-radius: 8px; font-size: 0.82rem;">
      <a href="{{ route('login') }}" style="flex:1; text-align:center; padding: 6px 4px; border-radius: 6px; text-decoration:none; font-weight:600; {{ !$portal ? 'background:white; color:#0284c7; box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'color:#64748b;' }}">
        🌐 All
      </a>
      <a href="{{ route('auth.tutor_login') }}" style="flex:1; text-align:center; padding: 6px 4px; border-radius: 6px; text-decoration:none; font-weight:600; {{ $portal == 'tutor' ? 'background:white; color:#059669; box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'color:#64748b;' }}">
        🎓 Tutor
      </a>
      <a href="{{ route('auth.parent_login') }}" style="flex:1; text-align:center; padding: 6px 4px; border-radius: 6px; text-decoration:none; font-weight:600; {{ $portal == 'parent' ? 'background:white; color:#d97706; box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'color:#64748b;' }}">
        👨‍👩‍👧 Parent
      </a>
      <a href="{{ route('auth.admin_login') }}" style="flex:1; text-align:center; padding: 6px 4px; border-radius: 6px; text-decoration:none; font-weight:600; {{ $portal == 'admin' ? 'background:white; color:#dc2626; box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'color:#64748b;' }}">
        🛡️ Admin
      </a>
    </div>

    <div class="auth-logo">
      <div class="logo-icon">
        @if($portal == 'admin') 🛡️
        @elseif($portal == 'tutor') 🎓
        @elseif($portal == 'parent') 👨‍👩‍👧
        @else 🎓 @endif
      </div>
      <h1>
        @if($portal == 'admin') Admin &amp; Staff Portal
        @elseif($portal == 'tutor') Tutor Portal Login
        @elseif($portal == 'parent') Parent Portal Login
        @else Welcome Back @endif
      </h1>
      <p>
        @if($portal == 'admin') Burewala operations, lead matching &amp; governance
        @elseif($portal == 'tutor') Tuition opportunities in Burewala's 12 sectors
        @elseif($portal == 'parent') Submit &amp; track tuition requests for your children
        @else Log in to your Maria Tutor account @endif
      </p>
    </div>

    @if(session('error'))
      <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
      <div class="alert alert-warning" style="margin-bottom: 16px;">{{ session('warning') }}</div>
    @endif

    <form method="POST" action="{{ route('login.post') }}">
      @csrf

      <div class="form-group">
        <label for="identifier">
          @if($portal == 'tutor') CNIC / Tutor ID / Email
          @elseif($portal == 'admin') Admin Email Address
          @elseif($portal == 'parent') Parent CNIC / Email Address
          @else CNIC / Email / Tutor ID @endif
        </label>
        <input id="identifier" name="identifier" value="{{ old('identifier') }}" autocomplete="username" required
               placeholder="@if($portal == 'tutor') 36402-1234567-1, T-BW001, or tutor@mariatutor.com @elseif($portal == 'admin') nasir@mariatutor.com or staff@mariatutor.com @elseif($portal == 'parent') 36402-7654321-1 or parent@mariatutor.com @else 36402-XXXXXXX-X, ID, or email @endif">
        @if($portal != 'admin')
        <small id="cnic-login-hint" style="display:block; color:var(--muted, #64748b); font-size:0.75rem; margin-top:4px;">
          Enter 13-digit Pakistani CNIC (dashes are added automatically) or email address.
        </small>
        @endif
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Enter your password">
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;font-size:0.95rem;">
        @if($portal == 'admin') Log in to Admin Center →
        @elseif($portal == 'tutor') Log in to Tutor Portal →
        @elseif($portal == 'parent') Log in to Parent Portal →
        @else Log in → @endif
      </button>
    </form>

    <div class="auth-links" style="margin-top:16px;">
      <a href="{{ route('auth.register_parent') }}">Parent signup</a>
      &nbsp;·&nbsp;
      <a href="{{ route('auth.register_tutor') }}">Tutor signup</a>
    </div>

    <div style="margin-top: 22px; padding-top: 16px; border-top: 1px solid var(--border, #e2e8f0); text-align: center;">
      <a href="{{ route('home') }}" class="back-public-btn" style="text-decoration:none; font-weight:700; color:#3b82f6;">
        ← Back to Maria Tutor Home
      </a>
    </div>

  </div>
</div>
@endsection
