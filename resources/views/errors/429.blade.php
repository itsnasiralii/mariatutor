@extends('layouts.base')
@section('title', '429 – Too Many Requests | Maria Tutor')
@section('robots', 'noindex,nofollow')

@section('content')
<div class="error-page" style="min-height: 65vh;">
  <div class="card" style="max-width: 480px; padding: 40px 32px; text-align: center;">
    <div class="error-code">429</div>
    <h1 style="font-size: 1.5rem; font-weight: 800; margin: 14px 0 8px;">Too Many Attempts</h1>
    <p class="muted" style="margin-bottom: 24px;">Please wait a minute before trying again. Rate limiting protects accounts from automated abuse.</p>
    <a href="{{ route('home') }}" class="btn btn-primary">Return Home</a>
  </div>
</div>
@endsection
