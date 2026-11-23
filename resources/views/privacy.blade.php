@extends('layouts.base')

@section('title', 'Privacy Policy — Maria Tutor')

@section('public_content')
<div class="home-layout">
  @include('partials.nav_home')
  <main style="max-width: 900px; margin: 40px auto; padding: 0 20px;">
    <div class="card" style="padding: 32px; background: #fff; border-radius: 12px; border: 1.5px solid #e2e8f0;">
      <h1 style="margin-bottom: 8px;">Zero-Leakage Privacy Policy</h1>
      <p class="muted" style="margin-bottom: 24px;">Effective: 2026 · Maria Tutor Burewala</p>

      <h3>1. Pakistani CNIC Zero-Plaintext Security</h3>
      <p style="margin-bottom: 16px;">We never store Pakistani National Identity Card (CNIC) numbers in plaintext. CNICs are normalized and transformed into a keyed HMAC-SHA256 cryptographic digest before touching our database.</p>

      <h3>2. Privacy Protected Contact Matching</h3>
      <p style="margin-bottom: 16px;">Student house addresses and parent phone numbers are never publicly broadcast. Tutors receive anonymized opportunities identifying only the broad Burewala area, class, and subjects.</p>

      <h3>3. Physical Verification & Safety</h3>
      <p style="margin-bottom: 16px;">All home tuition assignments are supervised under our platform charter. Tutors must provide genuine academic degrees and undergo verification before placement.</p>
    </div>
  </main>
</div>
@endsection
