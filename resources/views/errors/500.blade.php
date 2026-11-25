@extends('layouts.base')
@section('title', '500 – Server Error | Maria Tutor')
@section('robots', 'noindex,nofollow')

@section('content')
<div class="error-page" style="min-height: 65vh;">
  <div class="card" style="max-width: 480px; padding: 40px 32px; text-align: center;">
    <div class="error-code">500</div>
    <h1 style="font-size: 1.5rem; font-weight: 800; margin: 14px 0 8px;">Unexpected Error</h1>
    <p class="muted" style="margin-bottom: 24px;">Something went wrong while processing your request. Please try again shortly.</p>
    <a href="{{ route('home') }}" class="btn btn-primary">Return Home</a>
  </div>
</div>
@endsection
