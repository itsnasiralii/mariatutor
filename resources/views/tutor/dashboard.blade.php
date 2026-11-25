@extends('layouts.base')

@section('title', 'Tutor Dashboard | Maria Tutor')
@section('page_title', 'Tutor Dashboard')

@section('content')
<div class="page-header">
  <div>
    <div class="breadcrumb">Tutor Portal / <span>{{ $profile->tutor_code }}</span></div>
    <h1>Assalam-o-alaikum, {{ $profile->full_name }}</h1>
    <p class="muted">Review matched tuition opportunities, upload verification documents, and manage teaching availability.</p>
  </div>
  <div class="actions">
    <a class="btn btn-secondary" href="{{ route('tutor.profile') }}"><span aria-hidden="true">✏️</span> Edit Profile &amp; Subjects</a>
  </div>
</div>

<!-- Tutor Stats Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon {{ $profile->verification_status === 'Approved' ? 'green' : 'orange' }}" aria-hidden="true">
      {{ $profile->verification_status === 'Approved' ? '✅' : '⏳' }}
    </div>
    <div class="stat-info">
      <div class="stat-value" style="font-size: 1.15rem;">{{ $profile->verification_status }}</div>
      <div class="stat-label">Profile Status</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple" aria-hidden="true">💼</div>
    <div class="stat-info">
      <div class="stat-value">{{ $stats['total_exposures'] ?? $exposures->count() }}</div>
      <div class="stat-label">Matched Leads</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue" aria-hidden="true">🤝</div>
    <div class="stat-info">
      <div class="stat-value">{{ $stats['interested_count'] ?? 0 }}</div>
      <div class="stat-label">Interests Expressed</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green" aria-hidden="true">🎓</div>
    <div class="stat-info">
      <div class="stat-value">{{ $profile->tutor_code }}</div>
      <div class="stat-label">Tutor Code</div>
    </div>
  </div>
</div>

<!-- Document Verification Section -->
<section class="panel" style="margin-bottom: 28px;">
  <div class="section-heading">
    <div>
      <h2 style="margin-bottom: 4px;">Identity &amp; Degree Verification Vault ({{ $documents->count() }} / {{ \App\Http\Controllers\TutorController::MAX_DOCUMENTS_PER_TUTOR }})</h2>
      <p class="muted" style="font-size: 0.85rem;">Upload clear PDF, JPG, or PNG copies (max 8 MB each) of your CNIC and educational degrees for admin review.</p>
    </div>
  </div>

  @if($documents->count() < \App\Http\Controllers\TutorController::MAX_DOCUMENTS_PER_TUTOR)
    <form method="POST" action="{{ route('tutor.document_upload') }}" enctype="multipart/form-data" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom: {{ $documents->count() > 0 ? '18px' : '0' }};">
      @csrf
      <select name="document_type" required style="width: auto; min-width: 220px;" aria-label="Document type">
        <option value="CNIC Front & Back">CNIC Front &amp; Back</option>
        <option value="Degree / Transcript">University Degree / Transcript</option>
        <option value="Experience Certificate">Experience Certificate</option>
        <option value="Other">Other Supporting Document</option>
      </select>
      <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" required style="width: auto; max-width: 320px;" aria-label="Document file">
      <button type="submit" class="btn btn-sm btn-primary">Upload Document</button>
    </form>
  @else
    <div class="alert alert-warning" style="margin-bottom: 16px;">Maximum limit of {{ \App\Http\Controllers\TutorController::MAX_DOCUMENTS_PER_TUTOR }} documents reached.</div>
  @endif

  @if($documents->count() > 0)
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Document Type</th>
            <th>Filename</th>
            <th>Uploaded</th>
            <th>Review Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach($documents as $doc)
            <tr>
              <td>{{ $doc->document_type }}</td>
              <td>{{ $doc->original_name }}</td>
              <td>{{ ($doc->uploaded_at ?? $doc->created_at)?->format('M d, Y') }}</td>
              <td><span class="badge badge-info">{{ $doc->review_status }}</span></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

<!-- Matched Opportunities Section -->
<section class="panel">
  <div style="margin-bottom: 16px;">
    <h2 style="margin-bottom: 4px;">Matched Tuition Opportunities</h2>
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
              <span class="badge blue font-mono">{{ $req->public_ref }}</span>
            </td>
            <td>
              <strong>{{ $req->subject->name ?? 'General' }}</strong>
              <div class="muted" style="font-size: 0.8rem;">{{ $req->schoolClass->name ?? '' }}</div>
            </td>
            <td>
              <span><span aria-hidden="true">📍</span> {{ $req->area->name ?? 'Burewala' }}</span>
            </td>
            <td>
              <div>{{ $req->preferred_time }}</div>
              <div class="muted" style="font-size: 0.8rem;">{{ $req->mode->name ?? 'Home Tuition' }}</div>
            </td>
            <td>
              @if($req->budget_min || $req->budget_max)
                PKR {{ number_format($req->budget_min ?? 0) }} – {{ number_format($req->budget_max ?? 0) }}
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
                  <button type="submit" class="btn btn-sm btn-primary">Express Interest</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="empty">
              No new tuition leads matching your profile right now.
              <p style="font-size: 0.85rem; margin-top: 6px;">Make sure you have selected multiple Burewala sectors and subjects in your profile.</p>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if(method_exists($exposures, 'links'))
    <div style="margin-top: 20px;">
      {{ $exposures->links() }}
    </div>
  @endif
</section>
@endsection
