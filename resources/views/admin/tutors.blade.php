@extends('layouts.base')

@section('title', 'Tutors Verification Queue — Maria Tutor')
@section('page_title', 'Tutors Verification')

@section('content')
<div class="page-header" style="margin-bottom: 24px;">
  <div>
    <div class="breadcrumb">Operations / <span>Tutors</span></div>
    <h1>Tutors Directory &amp; Verification</h1>
    <p class="muted">Review background verification, CNIC validation, and approve tutors for student matching.</p>
  </div>
  <div class="actions">
    <a href="{{ route('admin.tutors', ['status' => 'all']) }}" class="btn btn-sm {{ $status == 'all' ? 'btn-primary' : 'btn-secondary' }}">All</a>
    <a href="{{ route('admin.tutors', ['status' => 'pending']) }}" class="btn btn-sm {{ $status == 'pending' ? 'btn-primary' : 'btn-secondary' }}">Pending</a>
    <a href="{{ route('admin.tutors', ['status' => 'approved']) }}" class="btn btn-sm {{ $status == 'approved' ? 'btn-primary' : 'btn-secondary' }}">Approved</a>
    <a href="{{ route('admin.tutors', ['status' => 'rejected']) }}" class="btn btn-sm {{ $status == 'rejected' ? 'btn-primary' : 'btn-secondary' }}">Rejected</a>
  </div>
</div>

<div class="panel">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tutor Code</th>
          <th>Name &amp; Gender</th>
          <th>Contact &amp; CNIC</th>
          <th>Qualification</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($tutors as $tutor)
          <tr>
            <td><span class="badge blue font-mono">{{ $tutor->tutor_code }}</span></td>
            <td>
              <strong>{{ $tutor->full_name }}</strong>
              <div class="muted" style="font-size:0.8rem;">{{ $tutor->gender }}</div>
            </td>
            <td>
              <div>📞 {{ $tutor->user->phone ?? 'N/A' }}</div>
              <div class="muted" style="font-size:0.8rem;">✉️ {{ $tutor->user->email ?? 'N/A' }}</div>
            </td>
            <td>
              <div>{{ $tutor->qualification ?: 'Not provided' }}</div>
              <div class="muted" style="font-size:0.8rem;">{{ Str::limit($tutor->experience, 50) }}</div>
            </td>
            <td>
              <span class="badge {{ $tutor->verification_status == 'Approved' ? 'green' : ($tutor->verification_status == 'Rejected' ? 'red' : 'orange') }}">
                {{ $tutor->verification_status }}
              </span>
            </td>
            <td>
              <a href="{{ route('admin.tutor_detail', $tutor->id) }}" class="btn btn-sm btn-primary">
                Review &amp; Verify →
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" style="text-align:center; padding:30px; color:#64748b;">
              No tutors found in this status category.
            </td>
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
