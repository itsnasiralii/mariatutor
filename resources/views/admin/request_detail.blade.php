@extends('layouts.base')
@section('title', 'Case ' . $req->public_ref . ' | Maria Tutor')
@section('page_title', 'Lead Case ' . $req->public_ref)

@section('content')
<div class="page-head">
  <div>
    <p class="eyebrow">CASE {{ $req->public_ref }}</p>
    <h1 style="font-size: 1.75rem; font-weight: 800; margin: 4px 0 8px 0;">{{ $req->subject->name ?? 'Subject' }} · {{ $req->schoolClass->name ?? 'Class' }}</h1>
    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
      <span class="badge {{ $req->status === 'Placed' ? 'badge-success' : ($req->status === 'Cancelled' ? 'badge-danger' : 'badge-info') }}">
        {{ $req->status }}
      </span>
      @if($req->commission_snapshot_type)
        <span class="badge badge-primary">
          Locked Commission: {{ $req->commission_snapshot_value }}{{ $req->commission_snapshot_type === 'percent' ? '%' : ' PKR' }}
        </span>
      @endif
      @if($req->contact_released_at)
        <span class="badge badge-success">Contact Released</span>
      @endif
    </div>
  </div>
  <div class="actions">
    <a class="btn btn-secondary" href="{{ route('admin.requests') }}">← Back to Requests</a>
  </div>
</div>

<div class="cards two" style="margin-bottom: 24px;">
  <article class="card">
    <h2>Lead Case Details</h2>
    <p><b>Parent Name:</b> {{ $req->parent->parentProfile->full_name ?? $req->parent->name ?? $req->parent->email }}</p>
    <p><b>Parent Phone:</b> {{ $req->parent->phone ?? '—' }}</p>
    <p><b>Location:</b> {{ $req->area->name ?? 'Burewala' }} @if($req->subarea_text) ({{ $req->subarea_text }}) @endif</p>
    <p><b>Subject:</b> {{ $req->subject->name ?? '—' }}</p>
    <p><b>Class Level:</b> {{ $req->schoolClass->name ?? '—' }}</p>
    <p><b>Preferred Mode:</b> {{ $req->mode->name ?? 'Home' }}</p>
    <p><b>Gender Preference:</b> {{ $req->tutor_gender_preference ?? 'Any' }}</p>
    <p><b>Preferred Timing:</b> {{ $req->preferred_time ?? 'Flexible' }}</p>
    <p><b>Monthly Budget:</b>
      @if($req->budget_min || $req->budget_max)
        PKR {{ number_format($req->budget_min ?? 0) }} – {{ number_format($req->budget_max ?? 0) }} / month
      @else
        Market rate
      @endif
    </p>
    <p><b>Notes / Instructions:</b> {{ $req->requirements ?: 'None provided' }}</p>

    @if($req->selectedTutor)
      <div class="notice privacy" style="margin-top: 18px;">
        <h4 style="margin: 0 0 6px 0;"><span aria-hidden="true">✅</span> Assigned Tutor</h4>
        <p style="margin: 0; font-weight: 700;">{{ $req->selectedTutor->full_name }} ({{ $req->selectedTutor->tutor_code }})</p>
        <p style="margin: 4px 0 0 0; font-size: 0.85rem;">Phone: {{ $req->selectedTutor->user->phone ?? '—' }}</p>
      </div>
    @endif
  </article>

  <article class="card">
    <h2>Assign Verified Tutor</h2>
    <form method="POST" action="{{ route('admin.request_assign', $req->id) }}">
      @csrf
      <div class="form-group">
        <label for="assign-tutor">Select Approved Tutor</label>
        <select id="assign-tutor" name="tutor_id" required>
          <option value="">-- Choose an Approved Tutor --</option>
          @foreach($approvedTutors as $t)
            <option value="{{ $t->id }}" {{ (int) old('tutor_id', $req->selected_tutor_id) === $t->id ? 'selected' : '' }}>
              {{ $t->full_name }} ({{ $t->tutor_code }}) — {{ $t->qualification ?: 'Verified' }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label for="assign-status">Lead Status</label>
        <select id="assign-status" name="status" required>
          @foreach($requestStatuses ?? \App\Http\Controllers\AdminController::REQUEST_STATUSES as $st)
            <option value="{{ $st }}" {{ old('status', $req->status === 'New request' ? 'Placed' : $req->status) === $st ? 'selected' : '' }}>
              {{ $st }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
          <input type="checkbox" name="release_contact" value="1" {{ old('release_contact', (bool) $req->contact_released_at) ? 'checked' : '' }}>
          <span>Authorize contact release to Parent portal</span>
        </label>
      </div>

      <button class="btn btn-primary" type="submit" style="width: 100%; justify-content: center; padding: 12px;">
        Save Tutor Assignment
      </button>
    </form>

    <div style="margin-top: 24px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
      <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 10px;">Matched Tutors ({{ $req->exposures->count() }})</h3>
      @if($req->exposures->count() > 0)
        <ul>
          @foreach($req->exposures as $exp)
            <li style="padding: 8px 0; border-bottom: 1px solid #f8fafc; display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 0.9rem;">
              <div>
                <b>{{ $exp->tutor->full_name ?? 'Tutor' }}</b> ({{ $exp->tutor->tutor_code ?? '' }})
                @if($exp->interest_note)
                  <div class="muted" style="font-size: 0.78rem;">{{ $exp->interest_note }}</div>
                @endif
              </div>
              <span class="badge {{ $exp->interested_at ? 'badge-success' : 'badge-gray' }}">
                {{ $exp->interested_at ? '✓ Interested' : 'Matched' }}
              </span>
            </li>
          @endforeach
        </ul>
      @else
        <p class="muted" style="font-size: 0.85rem;">No tutors matched yet.</p>
      @endif
    </div>
  </article>
</div>
@endsection
