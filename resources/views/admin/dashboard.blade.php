@extends('layouts.base')

@section('title', 'Admin Dashboard — Maria Tutor')
@section('page_title', 'Admin Control Center')

@section('content')
<div class="page-header" style="margin-bottom: 24px;">
  <div>
    <div class="breadcrumb">Operations / <span>Dashboard</span></div>
    <h1>Admin Control Center</h1>
    <p class="muted">Operational visibility and matching governance for Maria Tutor Platform.</p>
  </div>
  <div class="actions">
    <a href="{{ route('admin.tutors') }}" class="btn btn-secondary">🎓 Verify Tutors</a>
    <a href="{{ route('admin.requests') }}" class="btn btn-primary">📋 Requests Queue</a>
  </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid" style="margin-bottom: 28px;">
  <div class="stat-card">
    <div class="stat-icon purple">👥</div>
    <div class="stat-info">
      <div class="stat-value">{{ $stats['total_users'] }}</div>
      <div class="stat-label">Total Users</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">✅</div>
    <div class="stat-info">
      <div class="stat-value">{{ $stats['approved_tutors'] }}</div>
      <div class="stat-label">Approved Tutors</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange">⏳</div>
    <div class="stat-info">
      <div class="stat-value">{{ $stats['pending_verifications'] }}</div>
      <div class="stat-label">Pending Verification</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue">📋</div>
    <div class="stat-info">
      <div class="stat-value">{{ $stats['total_requests'] }}</div>
      <div class="stat-label">Total Requests</div>
    </div>
  </div>
</div>

<div class="grid-two">
  <!-- Pending Tutors Panel -->
  <div class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0; font-size:1.1rem;">⏳ Tutors Awaiting Verification</h3>
      <a href="{{ route('admin.tutors', ['status' => 'pending']) }}" class="btn btn-sm btn-secondary">View All</a>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Tutor Code</th>
            <th>Name &amp; Gender</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($pendingTutors as $tutor)
            <tr>
              <td><span class="badge blue font-mono">{{ $tutor->tutor_code }}</span></td>
              <td>
                <strong>{{ $tutor->full_name }}</strong>
                <div class="muted" style="font-size:0.78rem;">{{ $tutor->gender }} · {{ $tutor->qualification }}</div>
              </td>
              <td>
                <a href="{{ route('admin.tutor_detail', $tutor->id) }}" class="btn btn-sm btn-primary">Review</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="3" style="text-align:center; padding:20px; color:#64748b;">
                No tutors pending verification right now.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Requests Panel -->
  <div class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0; font-size:1.1rem;">📋 Recent Tuition Requests</h3>
      <a href="{{ route('admin.requests') }}" class="btn btn-sm btn-secondary">View All</a>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Ref</th>
            <th>Subject &amp; Area</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recentRequests as $req)
            <tr>
              <td><span class="badge blue font-mono">{{ $req->public_ref }}</span></td>
              <td>
                <strong>{{ $req->subject->name ?? 'General' }}</strong>
                <div class="muted" style="font-size:0.78rem;">📍 {{ $req->area->name ?? '' }}</div>
              </td>
              <td>
                <a href="{{ route('admin.request_detail', $req->id) }}" class="badge {{ in_array($req->status, ['Placed', 'Demo scheduled']) ? 'green' : 'orange' }}" style="text-decoration:none;">
                  {{ $req->status }} →
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="3" style="text-align:center; padding:20px; color:#64748b;">
                No tuition requests submitted yet.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
