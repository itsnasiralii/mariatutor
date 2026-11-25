@extends('layouts.base')
@section('title', '419 – Session Expired | Maria Tutor')
@section('robots', 'noindex,nofollow')

@section('content')
<div class="error-page" style="min-height: 65vh;">
  <div class="card" style="max-width: 480px; padding: 40px 32px; text-align: center;">
    <div class="error-code">419</div>
    <h1 style="font-size: 1.5rem; font-weight: 800; margin: 14px 0 8px;">Session Expired</h1>
    <p class="muted" style="margin-bottom: 24px;">Your security session expired due to inactivity. Please refresh and submit again.</p>
    <a href="{{ route('login') }}" class="btn btn-primary">Sign In Again</a>
  </div>
</div>
@endsection
