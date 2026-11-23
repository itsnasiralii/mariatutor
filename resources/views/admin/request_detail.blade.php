@extends('layouts.base')
@section('title', 'Case ' . $req->public_ref . ' — Admin Pipeline')

@section('content')
<div class="page-head" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
  <div>
    <p class="eyebrow" style="color: #6366f1; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; margin: 0 0 4px 0;">CASE {{ $req->public_ref }}</p>
    <h1 style="font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">{{ $req->subject->name ?? 'Subject' }} · {{ $req->schoolClass->name ?? 'Class' }}</h1>
    <div style="display: flex; gap: 8px; align-items: center;">
      <span class="badge {{ $req->status == 'Placed' ? 'badge-success' : ($req->status == 'Open' ? 'badge-warning' : 'badge-info') }}" style="padding: 4px 10px; border-radius: 999px; font-size: 0.85rem; font-weight: 700;">
        {{ $req->status }}
      </span>
      @if($req->commission_snapshot_type)
        <span class="badge badge-primary" style="padding: 4px 10px; border-radius: 999px; font-size: 0.85rem; font-weight: 700; background: #e0e7ff; color: #4338ca;">
          Locked Commission: {{ $req->commission_snapshot_value }}{{ $req->commission_snapshot_type == 'percent' ? '%' : ' PKR' }}
        </span>
      @endif
    </div>
  </div>
  <div style="display: flex; gap: 8px;">
    <a class="btn btn-secondary" href="{{ route('admin.requests') }}">← Back to Requests</a>
  </div>
</div>

<div class="cards two" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
  <article class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 24px;">
    <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 16px;">Lead Case Details</h2>
    <p><b>Parent Name:</b> {{ $req->parent->parentProfile->full_name ?? $req->parent->email }}</p>
    <p><b>Parent Phone:</b> {{ $req->parent->phone ?? '—' }}</p>
    <p><b>Location:</b> {{ $req->area->name ?? 'Burewala' }} @if($req->subarea_text) ({{ $req->subarea_text }}) @endif</p>
    <p><b>Subject:</b> {{ $req->subject->name ?? '—' }}</p>
    <p><b>Class Level:</b> {{ $req->schoolClass->name ?? '—' }}</p>
    <p><b>Preferred Mode:</b> {{ $req->mode->name ?? 'Home' }}</p>
    <p><b>Preferred Timing:</b> {{ $req->preferred_time ?? 'Flexible' }}</p>
    <p><b>Budget Offer:</b> {{ $req->budget_offered ? number_format($req->budget_offered) . ' PKR/month' : 'Standard' }}</p>
    <p><b>Notes / Instructions:</b> {{ $req->special_notes ?? 'None provided' }}</p>
    
    @if($req->selectedTutor)
      <div style="margin-top: 18px; padding: 14px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;">
        <h4 style="margin: 0 0 6px 0; color: #166534;">✅ Assigned Tutor</h4>
        <p style="margin: 0; font-weight: 700; color: #14532d;">{{ $req->selectedTutor->full_name }} ({{ $req->selectedTutor->tutor_code }})</p>
        <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: #166534;">Phone: {{ $req->selectedTutor->user->phone ?? '—' }}</p>
      </div>
    @endif
  </article>

  <article class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 24px;">
    <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 16px;">Assign Verified Tutor</h2>
    <form method="POST" action="{{ route('admin.request_assign', $req->id) }}">
      @csrf
      <label style="display: block; margin-bottom: 8px; font-weight: 600;">Select Tutor to Assign
        <select name="tutor_id" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 6px;" required>
          <option value="">-- Choose an Approved Tutor --</option>
          @foreach($approvedTutors as $t)
            <option value="{{ $t->id }}" {{ $req->selected_tutor_id == $t->id ? 'selected' : '' }}>
              {{ $t->full_name }} ({{ $t->tutor_code }}) — {{ $t->qualification }}
            </option>
          @endforeach
        </select>
      </label>

      <button class="btn btn-primary" type="submit" style="margin-top: 18px; width: 100%; padding: 12px; font-weight: 700; background: #4f46e5; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
        Assign Tutor & Close Lead
      </button>
    </form>

    <div style="margin-top: 24px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
      <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 10px;">Tutors Who Viewed / Exposed ({{ $req->exposures->count() }})</h3>
      @if($req->exposures->count() > 0)
        <ul style="list-style: none; padding: 0; margin: 0;">
          @foreach($req->exposures as $exp)
            <li style="padding: 8px 0; border-bottom: 1px solid #f8fafc; display: flex; justify-content: space-between; font-size: 0.9rem;">
              <span><b>{{ $exp->tutor->full_name ?? 'Tutor' }}</b> ({{ $exp->tutor->tutor_code ?? '' }})</span>
              <span class="badge badge-info" style="font-size: 0.75rem;">{{ $exp->channel }}</span>
            </li>
          @endforeach
        </ul>
      @else
        <p class="muted" style="font-size: 0.85rem; color: #64748b;">No tutors matched yet.</p>
      @endif
    </div>
  </article>
</div>
@endsection
