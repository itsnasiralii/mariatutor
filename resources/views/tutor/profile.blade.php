@extends('layouts.base')

@section('title', 'My Profile & Subjects | Maria Tutor')
@section('page_title', 'Update Teaching Profile')

@section('content')
<div style="max-width: 800px; margin: 0 auto; padding-bottom: 40px;">

  <div class="page-header">
    <div>
      <div class="breadcrumb">Tutor Portal / <span>Profile</span></div>
      <h1>Update Teaching Profile</h1>
      <p class="muted">Manage your qualifications, fee range, teaching subjects, and Burewala coverage areas.</p>
    </div>
  </div>

  <div class="panel">
    <form method="POST" action="{{ route('tutor.profile_update') }}">
      @csrf

      <div class="form-row-2" style="margin-bottom:18px;">
        <div>
          <label for="prof-name">Full Name *</label>
          <input id="prof-name" name="full_name" value="{{ old('full_name', $profile->full_name) }}" required maxlength="120">
        </div>

        <div>
          <label for="prof-code">Tutor Code (Fixed)</label>
          <input id="prof-code" value="{{ $profile->tutor_code }}" disabled style="background:#f1f5f9; color:#64748b;">
        </div>
      </div>

      <div class="form-group">
        <label for="prof-qual">Highest Academic Qualification</label>
        <input id="prof-qual" name="qualification" value="{{ old('qualification', $profile->qualification) }}" maxlength="200">
      </div>

      <div class="form-group">
        <label for="prof-exp">Teaching Experience</label>
        <textarea id="prof-exp" name="experience" rows="3" maxlength="500">{{ old('experience', $profile->experience) }}</textarea>
      </div>

      <div class="form-row-2" style="margin-bottom:20px;">
        <div>
          <label for="prof-fee-min">Min Expected Monthly Fee (PKR)</label>
          <input id="prof-fee-min" type="number" name="expected_fee_min" value="{{ old('expected_fee_min', $profile->expected_fee_min) }}" min="0" max="500000" step="500">
        </div>

        <div>
          <label for="prof-fee-max">Max Expected Monthly Fee (PKR)</label>
          <input id="prof-fee-max" type="number" name="expected_fee_max" value="{{ old('expected_fee_max', $profile->expected_fee_max) }}" min="0" max="500000" step="500">
        </div>
      </div>

      <div class="form-group" style="margin-bottom:24px;">
        <label for="prof-avail">Daily Time Availability</label>
        <input id="prof-avail" name="availability" value="{{ old('availability', $profile->availability) }}" maxlength="300" placeholder="e.g. 4:00 PM - 8:00 PM">
      </div>

      <div style="margin-bottom:24px;">
        <label style="margin-bottom:8px;"><span aria-hidden="true">📍</span> Burewala Teaching Sectors:</label>
        <div class="check-grid">
          @foreach($areas as $area)
            <label style="background:#f8fafc; padding:6px 10px; border-radius:6px; border:1px solid #e2e8f0;">
              <input type="checkbox" name="catalog_ids[]" value="{{ $area->id }}" {{ in_array($area->id, old('catalog_ids', $selectedCatalogIds)) ? 'checked' : '' }}>
              {{ $area->name }}
            </label>
          @endforeach
        </div>
      </div>

      <div style="margin-bottom:28px;">
        <label style="margin-bottom:8px;"><span aria-hidden="true">📖</span> Teaching Subjects:</label>
        <div class="check-grid">
          @foreach($subjects as $subj)
            <label style="background:#f8fafc; padding:6px 10px; border-radius:6px; border:1px solid #e2e8f0;">
              <input type="checkbox" name="catalog_ids[]" value="{{ $subj->id }}" {{ in_array($subj->id, old('catalog_ids', $selectedCatalogIds)) ? 'checked' : '' }}>
              {{ $subj->name }}
            </label>
          @endforeach
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px;">
        Save Profile Changes
      </button>
    </form>
  </div>
</div>
@endsection
