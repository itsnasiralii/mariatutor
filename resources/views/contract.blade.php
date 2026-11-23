@extends('layouts.base')

@section('title', 'Safeguarding, Child Protection & Code of Conduct — Maria Tutor')
@section('page_title', 'Safeguarding & Code of Conduct Contract')

@section('content')
<div class="contract-container" style="max-width: 1040px; margin: 0 auto; padding: 20px 0;">
  @include('partials.contract_body')
</div>
@endsection

@section('public_content')
<div class="home-layout">
  @include('partials.nav_home')
  <main style="min-height:80vh; padding: 24px clamp(14px, 4vw, 50px); background: #f8fafc;">
    <div class="contract-container" style="max-width: 1040px; margin: 0 auto;">
      @include('partials.contract_body')
    </div>
  </main>
</div>
@endsection
