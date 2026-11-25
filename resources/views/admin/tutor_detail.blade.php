@extends('layouts.base')
@section('title', 'Tutor Review ' . $tutor->tutor_code . ' | Maria Tutor')
@section('page_title', 'Tutor Verification: ' . $tutor->tutor_code)

@section('content')
@php
  $statusEnum = \App\Enums\VerificationStatus::tryFrom($tutor->verification_status);
  $badgeClass = $statusEnum ? $statusEnum->badgeClass() : 'badge-warning';
@endphp
<div class="page-head">
  <div>
    <p class="eyebrow">TUTOR {{ $tutor->tutor_code }}</p>
    <h1 style="font-size: 1.75rem; font-weight: 800; margin: 4px 0 8px 0;">{{ $tutor->full_name }}</h1>
    <span class="badge {{ $badgeClass }}">{{ $tutor->verification_status }}</span>
  </div>
  <div class="actions">
    <a class="btn btn-secondary" href="{{ route('admin.tutors') }}">← Back to Tutors</a>
  </div>
</div>

<div class="cards two" style="margin-bottom: 24px;">
  <article class="card">
    <h2>Tutor Profile</h2>
    <p><b>Phone:</b> {{ $tutor->user->phone ?? '—' }}</p>
    <p><b>Email:</b> {{ $tutor->user->email ?? '—' }}</p>
    <p><b>Qualification:</b> {{ $tutor->qualification ?: '—' }}</p>
    <p><b>Experience:</b> {{ $tutor->experience ?: '—' }}</p>
    <p><b>Availability:</b> {{ $tutor->availability ?: '—' }}</p>
    <p><b>Gender:</b> {{ $tutor->gender }} · <b>CNIC Advisory Hint:</b> {{ $tutor->cnic_gender_hint ?? '—' }}</p>
    <p><b>Selected Catalog Items:</b>
      @if($tutor->catalog && count($tutor->catalog) > 0)
        {{ $tutor->catalog->pluck('name')->join(', ') }}
      @else
        <span class="muted">None selected</span>
      @endif
    </p>
    <p><b>Expected Fee:</b> {{ $tutor->expected_fee_min ? number_format($tutor->expected_fee_min) : '—' }} – {{ $tutor->expected_fee_max ? number_format($tutor->expected_fee_max) : '—' }} PKR/month</p>
    <p class="notice" style="margin-top: 14px; font-size: 0.84rem;">
      <span aria-hidden="true">🔒</span> <b>CNIC Security:</b> Raw CNIC is never persisted in plaintext; stored as a keyed HMAC-SHA256 digest.
    </p>
  </article>

  <article class="card">
    <h2>Verification Decision</h2>
    <form method="POST" action="{{ route('admin.tutor_verify', $tutor->id) }}">
      @csrf
      <div class="form-group">
        <label for="verify-status">Verification Status</label>
        <select id="verify-status" name="status">
          @foreach($statuses ?? \App\Enums\VerificationStatus::values() as $s)
            <option value="{{ $s }}" {{ $tutor->verification_status === $s ? 'selected' : '' }}>{{ $s }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label for="verify-note">Private Decision Note</label>
        <textarea id="verify-note" name="note" maxlength="500" rows="4">{{ old('note', $tutor->verification_note) }}</textarea>
      </div>

      <button class="btn btn-primary" type="submit" style="width: 100%; justify-content: center; padding: 12px;">Save Verification Decision</button>
    </form>
  </article>
</div>

<section class="card">
  <h2>Private Document Vault ({{ $tutor->documents ? count($tutor->documents) : 0 }} / {{ \App\Http\Controllers\TutorController::MAX_DOCUMENTS_PER_TUTOR }})</h2>
  @if($tutor->documents && count($tutor->documents) > 0)
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Document Type</th>
            <th>Original Filename</th>
            <th>Uploaded</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach($tutor->documents as $doc)
            <tr>
              <td>{{ $doc->document_type }}</td>
              <td>{{ $doc->original_name }}</td>
              <td>{{ ($doc->uploaded_at ?? $doc->created_at)?->format('M d, Y') }}</td>
              <td><span class="badge badge-info">{{ $doc->review_status }}</span></td>
              <td>
                <a href="{{ route('admin.document_download', $doc->id) }}" class="btn btn-sm btn-primary">
                  Download
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <p class="muted">No verification documents uploaded yet.</p>
  @endif
</section>
@endsection
