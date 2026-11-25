@extends('layouts.base')

@php
  $isArea = $matchedItem->kind === 'area';
  $pageHeading = $isArea
      ? "Home Tutors in {$matchedItem->name}, Burewala"
      : "{$matchedItem->name} Tutors in Burewala";
  $seoTitle = \Illuminate\Support\Str::limit("{$pageHeading} | Maria Tutor", 58, '');
  $seoDesc = $isArea
      ? "Find approved male and female home tutors in {$matchedItem->name}, Burewala. Browse qualifications, subjects, and monthly fee ranges on Maria Tutor."
      : "Hire verified {$matchedItem->name} home and online tutors in Burewala, Punjab. Compare educator qualifications and book a free trial demo class.";
@endphp

@section('title', $seoTitle)
@section('page_title', $pageHeading)
@section('meta_description', \Illuminate\Support\Str::limit($seoDesc, 158, ''))
@section('canonical', route('tutors.landing', \Illuminate\Support\Str::slug($matchedItem->name)))

@section('public_content')
<div class="home-layout">
  @include('partials.nav_home')
  <main style="max-width: 1200px; margin: 0 auto; padding: 24px 16px 48px 16px;">
    @include('partials.tutors_landing_body')
  </main>
</div>
@endsection

@section('content')
<div style="max-width: 1200px; margin: 0 auto; padding: 10px 0 40px 0;">
  @include('partials.tutors_landing_body')
</div>
@endsection
