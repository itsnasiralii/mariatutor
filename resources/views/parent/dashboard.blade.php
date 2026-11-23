@extends('layouts.base')

@section('title', 'Parent Dashboard — Maria Tutor')
@section('page_title', 'Parent Dashboard')

@section('content')
<div class="page-header" style="margin-bottom: 24px;">
  <div>
    <div class="breadcrumb">Parent Portal / <span>Dashboard</span></div>
    <h1>Parent Dashboard</h1>
    <p class="muted">Manage your tuition requests, track tutor matching progress, and view verified teacher introductions.</p>
  </div>
  <div class="actions">
    <a class="btn btn-primary" href="{{ route('parent.request_new') }}" style="display: inline-flex; align-items: center; gap: 6px;">
      <span>➕</span> Request a New Tutor
    </a>
  </div>
</div>

<!-- Parent Stats Grid -->
<div class="stats-grid" style="margin-bottom: 28px;">
  <div class="stat-card">
    <div class="stat-icon blue">📋</div>
    <div class="stat-info">
      <div class="stat-value">{{ $requests->count() }}</div>
      <div class="stat-label">Total Requests</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange">⏳</div>
    <div class="stat-info">
      <div class="stat-value">
        {{ $requests->whereIn('status', ['New request', 'Matching', 'Tutor interested'])->count() }}
      </div>
      <div class="stat-label">Matching in Progress</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">🤝</div>
    <div class="stat-info">
      <div class="stat-value">
        {{ $requests->whereIn('status', ['Demo scheduled', 'Confirmed', 'Placed'])->count() }}
      </div>
      <div class="stat-label">Active / Placed</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">✅</div>
    <div class="stat-info">
      <div class="stat-value">
        {{ $requests->where('status', 'Completed')->count() }}
      </div>
      <div class="stat-label">Completed</div>
    </div>
  </div>
</div>

<!-- Requests Table Section -->
<section class="panel">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
    <div>
      <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 4px;">Your Tuition Requests</h2>
      <p class="muted" style="font-size: 0.85rem;">Contact details and home locations are kept private until our team authorizes an introduction.</p>
    </div>
    <a href="{{ route('parent.request_new') }}" class="btn btn-sm btn-secondary">
      + New Request
    </a>
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
              <span class="badge blue font-mono" style="font-size: 0.82rem;">{{ $req->public_ref }}</span>
            </td>
            <td>
              <strong>{{ $req->subject->name ?? 'General' }}</strong>
              <div class="muted" style="font-size: 0.8rem;">{{ $req->schoolClass->name ?? '' }} · {{ $req->mode->name ?? 'Home' }}</div>
            </td>
            <td>
              <span>📍 {{ $req->area->name ?? 'Burewala' }}</span>
              @if($req->subarea_text)
                <div class="muted" style="font-size: 0.8rem;">{{ $req->subarea_text }}</div>
              @endif
            </td>
            <td>
              <span class="badge {{ in_array($req->status, ['Placed', 'Demo scheduled']) ? 'green' : 'orange' }}">
                {{ $req->status }}
              </span>
            </td>
            <td>
              @if($req->selectedTutor)
                <strong>{{ $req->selectedTutor->full_name }}</strong> ({{ $req->selectedTutor->tutor_code }})
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
            <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
              You have not submitted any tuition requests yet.
              <div style="margin-top: 12px;">
                <a href="{{ route('parent.request_new') }}" class="btn btn-primary btn-sm">➕ Request a Tutor Now</a>
              </div>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection
