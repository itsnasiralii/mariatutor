@extends('layouts.base')

@section('title', 'Request a Home or Online Tutor — Maria Tutor')
@section('page_title', 'New Tuition Request')

@section('content')
<div style="max-width: 760px; margin: 0 auto; padding-bottom: 40px;">

  <div class="page-header" style="margin-bottom: 24px;">
    <div>
      <div class="breadcrumb">Parent Portal / <span>New Request</span></div>
      <h1>Request a Home or Online Tutor</h1>
      <p class="muted">Tell us your student's learning targets. Maria Tutor verifies credentials, checks spatial proximity in Burewala, and coordinates a free demo class.</p>
    </div>
  </div>

  @if($preferredTutor)
    <div style="background:#f0fdf4; border:1.5px solid #bbf7d0; border-radius:12px; padding:16px 20px; margin-bottom:24px; display:flex; align-items:center; gap:14px;">
      <div style="font-size:2rem;">🎓</div>
      <div>
        <strong style="color:#166534; font-size:1.05rem;">Booking Preferred Tutor: {{ $preferredTutor->full_name }} ({{ $preferredTutor->tutor_code }})</strong>
        <p style="margin:4px 0 0 0; color:#15803d; font-size:0.86rem;">Our administration will prioritize connecting your request directly with this teacher.</p>
      </div>
    </div>
  @endif

  <div class="panel" style="padding: 28px; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;">
    <form method="POST" action="{{ route('parent.request_submit') }}">
      @csrf

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px;">
        <div>
          <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">📚 Subject *</label>
          <select name="subject_id" required style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
            <option value="">Select Subject</option>
            @foreach($subjects as $s)
              <option value="{{ $s->id }}">{{ $s->name }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">🎓 Class / Grade Level *</label>
          <select name="class_id" required style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
            <option value="">Select Grade</option>
            @foreach($classes as $c)
              <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px;">
        <div>
          <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">📍 Burewala Sector / Area *</label>
          <select name="area_id" required style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
            <option value="">Select Area</option>
            @foreach($areas as $a)
              <option value="{{ $a->id }}">{{ $a->name }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">🏠 Sub-area / Landmark (Optional)</label>
          <input name="subarea_text" placeholder="e.g. Near Masjid Bilal, Block B" style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px;">
        <div>
          <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">🚗 Tuition Mode *</label>
          <select name="mode_id" required style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
            @foreach($modes as $m)
              <option value="{{ $m->id }}">{{ $m->name }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">👤 Tutor Gender Preference *</label>
          <select name="tutor_gender_preference" required style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
            <option value="Any">Any Gender (Best Match)</option>
            <option value="Female">Female Teacher Only</option>
            <option value="Male">Male Teacher Only</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px;">
        <div>
          <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">⏰ Preferred Daily Time *</label>
          <input name="preferred_time" required placeholder="e.g. 4:30 PM - 6:00 PM" style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
        </div>

        <div>
          <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">💰 Expected Monthly Budget (PKR)</label>
          <div style="display:flex; gap:8px;">
            <input type="number" name="budget_min" placeholder="Min (e.g. 10000)" step="500" style="width:50%; padding:10px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
            <input type="number" name="budget_max" placeholder="Max (e.g. 18000)" step="500" style="width:50%; padding:10px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
          </div>
        </div>
      </div>

      <div style="margin-bottom: 24px;">
        <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">📝 Special Requirements or Notes</label>
        <textarea name="requirements" rows="3" placeholder="e.g. Student needs extra attention in Math trigonometry and Physics numericals. Board exam prep needed." style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;"></textarea>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px; font-size:1rem; font-weight:700;">
        Submit Tuition Request →
      </button>
    </form>
  </div>
</div>
@endsection
