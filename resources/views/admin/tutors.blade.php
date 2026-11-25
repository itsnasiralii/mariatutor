@extends('layouts.base')

@section('title', 'Tutors Verification Queue | Maria Tutor')
@section('page_title', 'Tutors Verification')

@section('content')
@php
  $normalizedFilter = strtolower(str_replace(['_', '-'], ' ', $status ?? 'all'));
@endphp
<div class="page-header">
  <div>
    <div class="breadcrumb">Operations / <span>Tutors</span></div>
    <h1>Tutors Directory &amp; Verification</h1>
    <p class="muted">Review uploaded degrees and CNIC documents, and update verification status for student matching.</p>
  </div>
  <div class="actions">
    <a href="{{ route('admin.tutors', ['status' => 'all']) }}" class="btn btn-sm {{ $normalizedFilter === 'all' ? 'btn-primary' : 'btn-secondary' }}">All</a>
    <a href="{{ route('admin.tutors', ['status' => 'pending']) }}" class="btn btn-sm {{ $normalizedFilter === 'pending' ? 'btn-primary' : 'btn-secondary' }}">Pending</a>
    <a href="{{ route('admin.tutors', ['status' => 'under-review']) }}" class="btn btn-sm {{ $normalizedFilter === 'under review' ? 'btn-primary' : 'btn-secondary' }}">Under Review</a>
    <a href="{{ route('admin.tutors', ['status' => 'approved']) }}" class="btn btn-sm {{ $normalizedFilter === 'approved' ? 'btn-primary' : 'btn-secondary' }}">Approved</a>
    <a href="{{ route('admin.tutors', ['status' => 'rejected']) }}" class="btn btn-sm {{ $normalizedFilter === 'rejected' ? 'btn-primary' : 'btn-secondary' }}">Rejected</a>
  </div>
</div>

<div class="panel">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tutor Code</th>
          <th>Name &amp; Gender</th>
          <th>Contact</th>
          <th>Qualification</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($tutors as $tutor)
          @php
            $statusEnum = \App\Enums\VerificationStatus::tryFrom($tutor->verification_status);
            $badgeClass = $statusEnum ? $statusEnum->badgeClass() : 'badge-warning';
          @endphp
          <tr>
            <td><span class="badge blue font-mono">{{ $tutor->tutor_code }}</span></td>
            <td>
              <strong>{{ $tutor->full_name }}</strong>
              <div class="muted" style="font-size:0.8rem;">{{ $tutor->gender }}</div>
            </td>
            <td>
              <div><span aria-hidden="true">📞</span> {{ $tutor->user->phone ?? 'N/A' }}</div>
              <div class="muted" style="font-size:0.8rem;"><span aria-hidden="true">✉️</span> {{ $tutor->user->email ?? 'N/A' }}</div>
            </td>
            <td>
              <div>{{ $tutor->qualification ?: 'Not provided' }}</div>
              <div class="muted" style="font-size:0.8rem;">{{ \Illuminate\Support\Str::limit($tutor->experience, 50) }}</div>
            </td>
            <td>
              <span class="badge {{ $badgeClass }}">{{ $tutor->verification_status }}</span>
            </td>
            <td>
              <a href="{{ route('admin.tutor_detail', $tutor->id) }}" class="btn btn-sm btn-primary">
                Review &amp; Verify →
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="empty">No tutors found in this status category.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:20px;">
    {{ $tutors->links() }}
  </div>
</div>
@endsection
