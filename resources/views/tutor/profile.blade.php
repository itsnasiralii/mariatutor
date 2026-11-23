@extends('layouts.base')

@section('title', 'My Profile & Subjects — Maria Tutor')
@section('page_title', 'Update Teaching Profile')

@section('content')
<div style="max-width: 800px; margin: 0 auto; padding-bottom: 40px;">

  <div class="page-header" style="margin-bottom: 24px;">
    <div>
      <div class="breadcrumb">Tutor Portal / <span>Profile</span></div>
      <h1>Update Teaching Profile</h1>
      <p class="muted">Manage your qualifications, fee range, teaching subjects, and Burewala coverage areas.</p>
    </div>
  </div>

  <div class="panel" style="padding: 28px; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;">
    <form method="POST" action="{{ route('tutor.profile_update') }}">
      @csrf

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:18px;">
        <div>
          <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">Full Name *</label>
          <input name="full_name" value="{{ old('full_name', $profile->full_name) }}" required
                 style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
        </div>

        <div>
          <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">Tutor Code (Fixed)</label>
          <input value="{{ $profile->tutor_code }}" disabled
                 style="width:100%; padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.9rem; background:#f1f5f9; color:#64748b;">
        </div>
      </div>

      <div style="margin-bottom:18px;">
        <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">Highest Academic Qualification</label>
        <input name="qualification" value="{{ old('qualification', $profile->qualification) }}"
               style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
      </div>

      <div style="margin-bottom:18px;">
        <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">Teaching Experience</label>
        <textarea name="experience" rows="3"
                  style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">{{ old('experience', $profile->experience) }}</textarea>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:20px;">
        <div>
          <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">Min Expected Monthly Fee (PKR)</label>
          <input type="number" name="expected_fee_min" value="{{ old('expected_fee_min', $profile->expected_fee_min) }}" step="1000"
                 style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
        </div>

        <div>
          <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">Max Expected Monthly Fee (PKR)</label>
          <input type="number" name="expected_fee_max" value="{{ old('expected_fee_max', $profile->expected_fee_max) }}" step="1000"
                 style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
        </div>
      </div>

      <div style="margin-bottom:24px;">
        <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:6px;">Daily Time Availability</label>
        <input name="availability" value="{{ old('availability', $profile->availability) }}" placeholder="e.g. 4:00 PM - 8:00 PM"
               style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem;">
      </div>

      <div style="margin-bottom:24px;">
        <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:8px;">📍 Burewala Teaching Sectors:</label>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:8px;">
          @foreach($areas as $area)
            <label style="font-size:0.85rem; display:flex; align-items:center; gap:6px; background:#f8fafc; padding:6px 10px; border-radius:6px; border:1px solid #e2e8f0;">
              <input type="checkbox" name="catalog_ids[]" value="{{ $area->id }}" {{ in_array($area->id, $selectedCatalogIds) ? 'checked' : '' }}>
              {{ $area->name }}
            </label>
          @endforeach
        </div>
      </div>

      <div style="margin-bottom:28px;">
        <label style="display:block; font-weight:700; font-size:0.88rem; margin-bottom:8px;">📖 Teaching Subjects:</label>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap:8px;">
          @foreach($subjects as $subj)
            <label style="font-size:0.85rem; display:flex; align-items:center; gap:6px; background:#f8fafc; padding:6px 10px; border-radius:6px; border:1px solid #e2e8f0;">
              <input type="checkbox" name="catalog_ids[]" value="{{ $subj->id }}" {{ in_array($subj->id, $selectedCatalogIds) ? 'checked' : '' }}>
              {{ $subj->name }}
            </label>
          @endforeach
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px; font-weight:700;">
        Save Profile Changes
      </button>
    </form>
  </div>
</div>
@endsection
