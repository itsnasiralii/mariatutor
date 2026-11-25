@extends('layouts.base')

@section('title', 'Parent Dashboard | Maria Tutor')
@section('page_title', 'Parent Dashboard')

@section('content')
<div class="page-header">
  <div>
    <div class="breadcrumb">Parent Portal / <span>Dashboard</span></div>
    <h1>Parent Dashboard</h1>
    <p class="muted">Manage your tuition requests, track tutor matching progress, and view verified teacher introductions.</p>
  </div>
  <div class="actions">
    <a class="btn btn-primary" href="{{ route('parent.request_new') }}">
      <span aria-hidden="true">➕</span> Request a New Tutor
    </a>
  </div>
</div>

<!-- Parent Stats Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue" aria-hidden="true">📋</div>
    <div class="stat-info">
      <div class="stat-value">{{ $stats['total'] ?? $requests->count() }}</div>
      <div class="stat-label">Total Requests</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange" aria-hidden="true">⏳</div>
    <div class="stat-info">
      <div class="stat-value">{{ $stats['matching'] ?? 0 }}</div>
      <div class="stat-label">Matching in Progress</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple" aria-hidden="true">🤝</div>
    <div class="stat-info">
      <div class="stat-value">{{ $stats['active'] ?? 0 }}</div>
      <div class="stat-label">Active / Placed</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green" aria-hidden="true">✅</div>
    <div class="stat-info">
      <div class="stat-value">{{ $stats['completed'] ?? 0 }}</div>
      <div class="stat-label">Completed</div>
    </div>
  </div>
</div>

<!-- Requests Table Section -->
<section class="panel">
  <div class="section-heading">
    <div>
      <h2 style="margin-bottom: 4px;">Your Tuition Requests</h2>
      <p class="muted" style="font-size: 0.85rem;">Contact details and home locations are kept private until our team authorizes an introduction.</p>
    </div>
    <a href="{{ route('parent.request_new') }}" class="btn btn-sm btn-secondary">+ New Request</a>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Reference ID</th>
          <th>Subject &amp; Class</th>
          <th>Burewala Area</th>
          <th>Matching Status</th>
          <th>Assigned Tutor</th>
          <th>Submitted</th>
        </tr>
      </thead>
      <tbody>
        @forelse($requests as $req)
          <tr>
            <td>
              <span class="badge blue font-mono">{{ $req->public_ref }}</span>
            </td>
            <td>
              <strong>{{ $req->subject->name ?? 'General' }}</strong>
              <div class="muted" style="font-size: 0.8rem;">{{ $req->schoolClass->name ?? '' }} · {{ $req->mode->name ?? 'Home' }}</div>
            </td>
            <td>
              <span><span aria-hidden="true">📍</span> {{ $req->area->name ?? 'Burewala' }}</span>
              @if($req->subarea_text)
                <div class="muted" style="font-size: 0.8rem;">{{ $req->subarea_text }}</div>
              @endif
            </td>
            <td>
              <span class="badge {{ in_array($req->status, ['Placed', 'Demo scheduled', 'Confirmed']) ? 'green' : 'orange' }}">
                {{ $req->status }}
              </span>
            </td>
            <td>
              @if($req->selectedTutor)
                <strong>{{ $req->selectedTutor->full_name }}</strong> ({{ $req->selectedTutor->tutor_code }})
                @if($req->contact_released_at && $req->selectedTutor->user?->phone)
                  <div style="font-size: 0.82rem; color: #065f46; font-weight: 600; margin-top: 2px;">
                    <span aria-hidden="true">📞</span> {{ $req->selectedTutor->user->phone }}
                  </div>
                @endif
              @else
                <span class="muted">Matching nearby tutors...</span>
              @endif
            </td>
            <td class="muted" style="font-size: 0.82rem;">
              {{ $req->created_at->format('M d, Y') }}
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="empty">
              You have not submitted any tuition requests yet.
              <div style="margin-top: 12px;">
                <a href="{{ route('parent.request_new') }}" class="btn btn-primary btn-sm"><span aria-hidden="true">➕</span> Request a Tutor Now</a>
              </div>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if(method_exists($requests, 'links'))
    <div style="margin-top: 20px;">
      {{ $requests->links() }}
    </div>
  @endif
</section>
@endsection
