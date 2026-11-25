@extends('layouts.base')

@php
  $portalTitle = match($portal) {
      'admin' => 'Admin & Staff Portal',
      'tutor' => 'Tutor Portal Login',
      'parent' => 'Parent Portal Login',
      default => 'Sign In to Maria Tutor',
  };
  $portalSubtitle = match($portal) {
      'admin' => 'Burewala operations, lead matching & governance',
      'tutor' => 'Tuition opportunities across Burewala sectors',
      'parent' => 'Submit & track tuition requests for your children',
      default => 'Log in to your Maria Tutor account',
  };
  $portalIcon = match($portal) {
      'admin' => '🛡️',
      'tutor' => '🎓',
      'parent' => '👨‍👩‍👧',
      default => '🎓',
  };
@endphp

@section('title', $portalTitle . ' | Maria Tutor')
@section('meta_description', 'Sign in to the Maria Tutor portal for parents and verified tutors in Burewala.')
@section('robots', 'noindex,nofollow')
@section('canonical', route('login'))

@section('public_content')
<div class="auth-page">
  <div class="auth-card" style="max-width: 480px;">

    @if($portal !== 'admin')
    <!-- Portal Switch Tabs -->
    <div style="display:flex; gap:6px; margin-bottom: 20px; background: var(--bg, #f1f5f9); padding: 4px; border-radius: 8px; font-size: 0.85rem;">
      <a href="{{ route('auth.parent_login') }}" style="flex:1; text-align:center; padding: 8px 6px; border-radius: 6px; text-decoration:none; font-weight:700; {{ $portal !== 'tutor' ? 'background:white; color:#d97706; box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'color:#64748b;' }}">
        <span aria-hidden="true">👨‍👩‍👧</span> Parent Portal
      </a>
      <a href="{{ route('auth.tutor_login') }}" style="flex:1; text-align:center; padding: 8px 6px; border-radius: 6px; text-decoration:none; font-weight:700; {{ $portal === 'tutor' ? 'background:white; color:#059669; box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'color:#64748b;' }}">
        <span aria-hidden="true">🎓</span> Tutor Portal
      </a>
    </div>
    @endif

    <div class="auth-logo">
      <div class="logo-icon" aria-hidden="true">{{ $portalIcon }}</div>
      <h1>{{ $portalTitle }}</h1>
      <p>{{ $portalSubtitle }}</p>
    </div>

    @if(session('success'))
      <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-error" role="alert">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
      <div class="alert alert-warning" role="status">{{ session('warning') }}</div>
    @endif
    @if($errors->any())
      <div class="alert alert-error" role="alert">
        <ul>
          @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('login.post') }}">
      @csrf

      <div class="form-group">
        <label for="identifier">
          @if($portal === 'tutor') CNIC / Tutor ID / Email
          @elseif($portal === 'admin') Staff Email Address
          @elseif($portal === 'parent') Parent CNIC / Phone / Email
          @else CNIC / Email / Phone / Tutor ID @endif
        </label>
        <input id="identifier" name="identifier" value="{{ old('identifier') }}" autocomplete="username" required
               placeholder="{{ $portal === 'admin' ? 'admin@mariatutor.pk' : ($portal === 'tutor' ? '36402-XXXXXXX-X, T-BW001, or email' : '36402-XXXXXXX-X, phone, or email') }}">
        @if($portal !== 'admin')
        <small id="cnic-login-hint" style="display:block; color:var(--muted, #64748b); font-size:0.75rem; margin-top:4px;">
          Enter 13-digit Pakistani CNIC, registered phone number, or email address.
        </small>
        @endif
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Enter your password">
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;font-size:0.95rem;">
        @if($portal === 'admin') Log in to Admin Center →
        @elseif($portal === 'tutor') Log in to Tutor Portal →
        @elseif($portal === 'parent') Log in to Parent Portal →
        @else Log in → @endif
      </button>
    </form>

    @if($portal !== 'admin')
    <div class="auth-links">
      <a href="{{ route('auth.register_parent') }}">Parent signup</a>
      &nbsp;·&nbsp;
      <a href="{{ route('auth.register_tutor') }}">Tutor signup</a>
    </div>
    @endif

    <div style="margin-top: 22px; padding-top: 16px; border-top: 1px solid var(--border, #e2e8f0); text-align: center;">
      <a href="{{ route('home') }}" class="btn btn-secondary" style="width: 100%; justify-content: center;">
        ← Back to Maria Tutor Home
      </a>
    </div>
  </div>
</div>
@endsection
