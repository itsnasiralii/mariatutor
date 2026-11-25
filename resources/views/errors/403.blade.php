@extends('layouts.base')
@section('title', '403 – Access Denied | Maria Tutor')
@section('robots', 'noindex,nofollow')

@section('content')
<div class="error-page" style="min-height: 65vh;">
  <div class="card" style="max-width: 480px; padding: 40px 32px; text-align: center;">
    <div class="error-code">403</div>
    <h1 style="font-size: 1.5rem; font-weight: 800; margin: 14px 0 8px;">Access Denied</h1>
    <p class="muted" style="margin-bottom: 24px;">You do not have permission to access this portal or resource with your current account role.</p>
    <a href="{{ route('home') }}" class="btn btn-primary">← Back to Maria Tutor Home</a>
  </div>
</div>
@endsection
