@extends('layouts.base')

@section('title', 'Privacy Policy & CNIC Security | Maria Tutor')
@section('page_title', 'Privacy Policy')
@section('meta_description', 'Learn how Maria Tutor protects parent contact numbers, home addresses, and Pakistani CNIC numbers using keyed HMAC-SHA256 cryptographic hashing.')
@section('canonical', route('privacy'))

@section('content')
<div style="max-width: 900px; margin: 12px auto 40px;">
  <div class="card" style="padding: 32px;">
    <h1 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 8px;">Zero-Leakage Privacy Policy</h1>
    <p class="muted" style="margin-bottom: 24px;">Effective: 2026 · Maria Tutor Burewala</p>

    <h2>1. Pakistani CNIC Zero-Plaintext Security</h2>
    <p style="margin-bottom: 18px;">We never store Pakistani National Identity Card (CNIC) numbers in plaintext. CNICs are normalized and transformed into a keyed HMAC-SHA256 cryptographic digest using an isolated secret key before touching our database.</p>

    <h2>2. Privacy-Protected Contact Matching</h2>
    <p style="margin-bottom: 18px;">Student house addresses and parent phone numbers are never publicly broadcast. Tutors receive anonymized opportunities identifying only the Burewala sector, class level, and subjects until administrative coordination authorizes contact release.</p>

    <h2>3. Private Document Vault &amp; Audit Logging</h2>
    <p style="margin-bottom: 0;">Uploaded academic transcripts and identity documents are stored outside the public web root and can only be accessed by authorized verification staff with audit trail logging.</p>
  </div>
</div>
@endsection

@section('public_content')
<div class="home-layout">
  @include('partials.nav_home')
  <main style="max-width: 900px; margin: 40px auto; padding: 0 20px;">
    <div class="card" style="padding: 32px;">
      <h1 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 8px;">Zero-Leakage Privacy Policy</h1>
      <p class="muted" style="margin-bottom: 24px;">Effective: 2026 · Maria Tutor Burewala</p>

      <h2>1. Pakistani CNIC Zero-Plaintext Security</h2>
      <p style="margin-bottom: 18px;">We never store Pakistani National Identity Card (CNIC) numbers in plaintext. CNICs are normalized and transformed into a keyed HMAC-SHA256 cryptographic digest using an isolated secret key before touching our database.</p>

      <h2>2. Privacy-Protected Contact Matching</h2>
      <p style="margin-bottom: 18px;">Student house addresses and parent phone numbers are never publicly broadcast. Tutors receive anonymized opportunities identifying only the Burewala sector, class level, and subjects until administrative coordination authorizes contact release.</p>

      <h2>3. Private Document Vault &amp; Audit Logging</h2>
      <p style="margin-bottom: 0;">Uploaded academic transcripts and identity documents are stored outside the public web root and can only be accessed by authorized verification staff with audit trail logging.</p>
    </div>
  </main>
</div>
@endsection
