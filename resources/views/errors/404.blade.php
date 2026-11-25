@extends('layouts.base')
@section('title', '404 – Page Not Found | Maria Tutor')
@section('robots', 'noindex,nofollow')

@section('content')
<div class="error-page" style="min-height: 65vh;">
  <div class="card" style="max-width: 480px; padding: 40px 32px; text-align: center;">
    <div class="error-code">404</div>
    <h1 style="font-size: 1.5rem; font-weight: 800; margin: 14px 0 8px;">Page Not Found</h1>
    <p class="muted" style="margin-bottom: 24px;">The page or resource you requested could not be found or may have moved.</p>
    <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
      <a href="{{ route('home') }}" class="btn btn-primary">Go Home</a>
      <a href="{{ route('explore.tutors') }}" class="btn btn-secondary">Explore Tutors</a>
    </div>
  </div>
</div>
@endsection
