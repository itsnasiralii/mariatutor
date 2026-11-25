@extends('layouts.base')

@section('title', 'Verified Home Tutors in Burewala | Maria Tutor')
@section('page_title', 'Explore Verified Tutors')
@section('meta_description', 'Browse approved male and female home and online tutors in Burewala by sector, subject, and gender. Request a coordinated demo class through Maria Tutor.')
@section('canonical', route('explore.tutors'))

@section('public_content')
<div class="home-layout">
  @include('partials.nav_home')
  <main style="max-width: 1200px; margin: 0 auto; padding: 20px 16px 40px 16px;">
    @include('partials.explore_tutors_content')
  </main>
</div>
@endsection

@section('content')
<div style="max-width: 1200px; margin: 0 auto; padding: 10px 0 40px 0;">
  @include('partials.explore_tutors_content')
</div>
@endsection
