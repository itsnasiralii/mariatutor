@extends('layouts.base')

@section('title', 'Explore Verified Tutors — Maria Tutor')

@section('public_content')
<div class="home-layout">
  @include('partials.nav_home')
  <div style="max-width: 1200px; margin: 0 auto; padding: 20px 16px 40px 16px;">
    @include('partials.explore_tutors_content')
  </div>
</div>
@endsection

@section('content')
<div style="max-width: 1200px; margin: 0 auto; padding: 10px 0 40px 0;">
  @include('partials.explore_tutors_content')
</div>
@endsection
