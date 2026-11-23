@extends('layouts.base')
@section('title', 'Tutor Review ' . $tutor->tutor_code)

@section('content')
<div class="page-head" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
  <div>
    <p class="eyebrow" style="color: #6366f1; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; margin: 0 0 4px 0;">TUTOR {{ $tutor->tutor_code }}</p>
    <h1 style="font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">{{ $tutor->full_name }}</h1>
    <span class="badge {{ $tutor->verification_status == 'Approved' ? 'badge-success' : ($tutor->verification_status == 'Rejected' ? 'badge-danger' : 'badge-warning') }}" style="padding: 4px 10px; border-radius: 999px; font-size: 0.85rem; font-weight: 700;">
      {{ $tutor->verification_status }}
    </span>
  </div>
  <div style="display: flex; gap: 8px;">
    <a class="btn btn-secondary" href="{{ route('admin.tutors') }}">← Back to Tutors</a>
  </div>
</div>

<div class="cards two" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
  <article class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 24px;">
    <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 16px;">Tutor Profile</h2>
    <p><b>Phone:</b> {{ $tutor->user->phone ?? '—' }}</p>
    <p><b>Email:</b> {{ $tutor->user->email ?? '—' }}</p>
    <p><b>Qualification:</b> {{ $tutor->qualification ?? '—' }}</p>
    <p><b>Experience:</b> {{ $tutor->experience ?? '—' }}</p>
    <p><b>Availability:</b> {{ $tutor->availability ?? '—' }}</p>
    <p><b>Gender:</b> {{ $tutor->gender }} · <b>CNIC Advisory Hint:</b> {{ $tutor->cnic_gender_hint ?? '—' }}</p>
    <p><b>Selected Catalog Items:</b> 
      @if($tutor->catalog && count($tutor->catalog) > 0)
        {{ $tutor->catalog->pluck('name')->join(', ') }}
      @else
        <span class="muted">None selected</span>
      @endif
    </p>
    <p><b>Expected Fee:</b> {{ $tutor->expected_fee_min ?? '—' }}–{{ $tutor->expected_fee_max ?? '—' }} PKR/month</p>
    <p style="font-size: 0.85rem; color: #64748b; margin-top: 12px; background: #f8fafc; padding: 10px; border-radius: 8px;">
      🔒 <b>CNIC Security:</b> Raw CNIC is never persisted in plaintext; stored as keyed HMAC-SHA256 fingerprint.
    </p>
  </article>

  <article class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 24px;">
    <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 16px;">Verification Decision</h2>
    <form method="POST" action="{{ route('admin.tutor_verify', $tutor->id) }}">
      @csrf
      <label style="display: block; margin-bottom: 8px; font-weight: 600;">Verification Status
        <select name="status" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 6px;">
          @foreach(['Pending', 'Under Review', 'Approved', 'Rejected'] as $s)
            <option value="{{ $s }}" {{ $tutor->verification_status == $s ? 'selected' : '' }}>{{ $s }}</option>
          @endforeach
        </select>
      </label>

      <label style="display: block; margin-top: 14px; margin-bottom: 8px; font-weight: 600;">Private Decision Note
        <textarea name="note" maxlength="500" rows="4" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 6px;">{{ $tutor->verification_note }}</textarea>
      </label>

      <button class="btn btn-primary" type="submit" style="margin-top: 16px; width: 100%; padding: 12px; font-weight: 700; background: #4f46e5; color: #fff; border: none; border-radius: 8px; cursor: pointer;">Save Verification Decision</button>
    </form>
  </article>
</div>

<section class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 24px; margin-bottom: 24px;">
  <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 16px;">Private Document Vault</h2>
  @if($tutor->documents && count($tutor->documents) > 0)
    <div class="table-wrap">
      <table style="width: 100%; border-collapse: collapse;">
        <thead>
          <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
            <th style="padding: 10px;">Document Type</th>
            <th style="padding: 10px;">Original Filename</th>
            <th style="padding: 10px;">Uploaded</th>
            <th style="padding: 10px;">Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach($tutor->documents as $doc)
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 10px;">{{ $doc->document_type }}</td>
              <td style="padding: 10px;">{{ $doc->original_filename }}</td>
              <td style="padding: 10px;">{{ $doc->created_at->format('M d, Y') }}</td>
              <td style="padding: 10px;"><span class="badge badge-info">{{ $doc->review_status }}</span></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <p class="muted" style="color: #64748b;">No documents uploaded yet.</p>
  @endif
</section>
@endsection
