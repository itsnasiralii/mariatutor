@extends('layouts.base')

@section('title', 'Request a Home or Online Tutor | Maria Tutor')
@section('page_title', 'New Tuition Request')

@section('content')
<div style="max-width: 760px; margin: 0 auto; padding-bottom: 40px;">

  <div class="page-header">
    <div>
      <div class="breadcrumb">Parent Portal / <span>New Request</span></div>
      <h1>Request a Home or Online Tutor</h1>
      <p class="muted">Tell us your student's learning targets. Maria Tutor checks tutor credentials, spatial proximity in Burewala, and coordinates a free demo class.</p>
    </div>
  </div>

  @if($preferredTutor)
    <div class="notice privacy" style="display:flex; align-items:center; gap:14px; margin-bottom:24px;">
      <div style="font-size:2rem;" aria-hidden="true">🎓</div>
      <div>
        <strong style="font-size:1.02rem;">Booking Preferred Tutor: {{ $preferredTutor->full_name }} ({{ $preferredTutor->tutor_code }})</strong>
        <p style="margin:4px 0 0 0; font-size:0.86rem;">Our administration will prioritize connecting your request directly with this teacher.</p>
      </div>
    </div>
  @endif

  <div class="panel">
    <form method="POST" action="{{ route('parent.request_submit') }}">
      @csrf

      <div class="form-row-2" style="margin-bottom: 20px;">
        <div>
          <label for="req-subject"><span aria-hidden="true">📚</span> Subject *</label>
          <select id="req-subject" name="subject_id" required>
            <option value="">Select Subject</option>
            @foreach($subjects as $s)
              <option value="{{ $s->id }}" {{ (int) old('subject_id') === $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label for="req-class"><span aria-hidden="true">🎓</span> Class / Grade Level *</label>
          <select id="req-class" name="class_id" required>
            <option value="">Select Grade</option>
            @foreach($classes as $c)
              <option value="{{ $c->id }}" {{ (int) old('class_id') === $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="form-row-2" style="margin-bottom: 20px;">
        <div>
          <label for="req-area"><span aria-hidden="true">📍</span> Burewala Sector / Area *</label>
          <select id="req-area" name="area_id" required>
            <option value="">Select Area</option>
            @foreach($areas as $a)
              <option value="{{ $a->id }}" {{ (int) old('area_id') === $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label for="req-subarea"><span aria-hidden="true">🏠</span> Sub-area / Landmark (Optional)</label>
          <input id="req-subarea" name="subarea_text" value="{{ old('subarea_text') }}" maxlength="120" placeholder="e.g. Near Masjid Bilal, Block B">
        </div>
      </div>

      <div class="form-row-2" style="margin-bottom: 20px;">
        <div>
          <label for="req-mode"><span aria-hidden="true">🚗</span> Tuition Mode *</label>
          <select id="req-mode" name="mode_id" required>
            @foreach($modes as $m)
              <option value="{{ $m->id }}" {{ (int) old('mode_id') === $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label for="req-gender"><span aria-hidden="true">👤</span> Tutor Gender Preference *</label>
          <select id="req-gender" name="tutor_gender_preference" required>
            <option value="Any" {{ old('tutor_gender_preference') === 'Any' ? 'selected' : '' }}>Any Gender (Best Match)</option>
            <option value="Female" {{ old('tutor_gender_preference') === 'Female' ? 'selected' : '' }}>Female Teacher Only</option>
            <option value="Male" {{ old('tutor_gender_preference') === 'Male' ? 'selected' : '' }}>Male Teacher Only</option>
          </select>
        </div>
      </div>

      <div class="form-row-2" style="margin-bottom: 20px;">
        <div>
          <label for="req-time"><span aria-hidden="true">⏰</span> Preferred Daily Time *</label>
          <input id="req-time" name="preferred_time" value="{{ old('preferred_time') }}" required maxlength="120" placeholder="e.g. 4:30 PM - 6:00 PM">
        </div>

        <div>
          <label><span aria-hidden="true">💰</span> Expected Monthly Budget (PKR)</label>
          <div style="display:flex; gap:8px;">
            <input type="number" name="budget_min" value="{{ old('budget_min') }}" min="0" max="500000" step="500" placeholder="Min (e.g. 10000)" aria-label="Minimum monthly budget in PKR">
            <input type="number" name="budget_max" value="{{ old('budget_max') }}" min="0" max="500000" step="500" placeholder="Max (e.g. 18000)" aria-label="Maximum monthly budget in PKR">
          </div>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 24px;">
        <label for="req-notes"><span aria-hidden="true">📝</span> Special Requirements or Notes</label>
        <textarea id="req-notes" name="requirements" rows="3" maxlength="500" placeholder="e.g. Student needs extra attention in Math trigonometry and Physics numericals.">{{ old('requirements') }}</textarea>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px; font-size:1rem;">
        Submit Confidential Request →
      </button>
    </form>
  </div>
</div>
@endsection
