@extends('layouts.base')

@section('title', 'Tutor Dashboard — Maria Tutor')
@section('page_title', 'Tutor Dashboard')

@section('content')
<div class="page-header" style="margin-bottom: 24px;">
  <div>
    <div class="breadcrumb">Tutor Portal / <span>{{ $profile->tutor_code }}</span></div>
    <h1>Assalam-o-alaikum, {{ $profile->full_name }}</h1>
    <p class="muted">Review matched tuition opportunities, update your teaching availability, and track introduced students.</p>
  </div>
  <div class="actions">
    <a class="btn btn-secondary" href="{{ route('tutor.profile') }}">✏️ Edit Profile &amp; Subjects</a>
  </div>
</div>

<!-- Tutor Stats Grid -->
<div class="stats-grid" style="margin-bottom: 28px;">
  <div class="stat-card">
    <div class="stat-icon {{ $profile->verification_status == 'Approved' ? 'green' : 'orange' }}">
      {{ $profile->verification_status == 'Approved' ? '✅' : '⏳' }}
    </div>
    <div class="stat-info">
      <div class="stat-value" style="font-size: 1.15rem;">{{ $profile->verification_status }}</div>
      <div class="stat-label">Profile Status</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">💼</div>
    <div class="stat-info">
      <div class="stat-value">{{ $exposures->count() }}</div>
      <div class="stat-label">Matched Leads</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue">🤝</div>
    <div class="stat-info">
      <div class="stat-value">{{ $exposures->whereNotNull('interested_at')->count() }}</div>
      <div class="stat-label">Interests Expressed</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">🎓</div>
    <div class="stat-info">
      <div class="stat-value">{{ $profile->tutor_code }}</div>
      <div class="stat-label">Tutor Code</div>
    </div>
  </div>
</div>

@if($profile->verification_status != 'Approved')
  <div style="background: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid var(--warning, #f59e0b); border-radius: 8px; padding: 18px 20px; margin-bottom: 28px;">
    <div style="display: flex; align-items: flex-start; gap: 12px;">
      <span style="font-size: 1.5rem;">⚠️</span>
      <div>
        <h3 style="font-size: 1rem; font-weight: 700; color: #92400e; margin: 0 0 4px 0;">Identity &amp; Degree Verification Pending ({{ $profile->verification_status }})</h3>
        <p style="font-size: 0.88rem; color: #b45309; margin: 0 0 12px 0;">
          Upload your CNIC copy and educational degrees. Once our verification desk approves your documents, you will receive notifications for nearby tuition assignments.
        </p>
        <form method="POST" action="{{ route('tutor.document_upload') }}" enctype="multipart/form-data" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
          @csrf
          <select name="document_type" required style="padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.86rem; background:#fff;">
            <option value="CNIC Front & Back">CNIC Front &amp; Back</option>
            <option value="Degree / Transcript">University Degree / Transcript</option>
            <option value="Experience Certificate">Experience Certificate</option>
          </select>
          <input type="file" name="document_file" required style="font-size:0.86rem;">
          <button type="submit" class="btn btn-sm btn-primary">Upload Document</button>
        </form>
      </div>
    </div>
  </div>
@endif

<!-- Matched Opportunities Section -->
<section class="panel">
  <div style="margin-bottom: 16px;">
    <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 4px;">Matched Tuition Opportunities</h2>
    <p class="muted" style="font-size: 0.85rem;">Anonymized student requirements in your selected Burewala areas. Express interest to let our team coordinate a demo class.</p>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Reference ID</th>
          <th>Subject &amp; Class</th>
          <th>Sector / Area</th>
          <th>Timing &amp; Mode</th>
          <th>Budget</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($exposures as $ex)
          @php $req = $ex->request; @endphp
          <tr>
            <td>
              <span class="badge blue font-mono" style="font-size: 0.82rem;">{{ $req->public_ref }}</span>
            </td>
            <td>
              <strong>{{ $req->subject->name ?? 'General' }}</strong>
              <div class="muted" style="font-size: 0.8rem;">{{ $req->schoolClass->name ?? '' }}</div>
            </td>
            <td>
              <span>📍 {{ $req->area->name ?? 'Burewala' }}</span>
            </td>
            <td>
              <div>{{ $req->preferred_time }}</div>
              <div class="muted" style="font-size: 0.8rem;">{{ $req->mode->name ?? 'Home Tuition' }}</div>
            </td>
            <td>
              @if($req->budget_min)
                PKR {{ number_format($req->budget_min) }} - {{ number_format($req->budget_max) }}
              @else
                <span class="muted">Market rate</span>
              @endif
            </td>
            <td>
              @if($ex->interested_at)
                <span class="badge green">✓ Interest Sent</span>
              @else
                <form method="POST" action="{{ route('tutor.express_interest', $ex->id) }}" style="margin:0;">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-primary">
                    Express Interest
                  </button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
              No new tuition leads matching your profile right now.
              <p style="font-size: 0.85rem; margin-top: 6px;">Make sure you have selected multiple Burewala sectors in your profile.</p>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection
